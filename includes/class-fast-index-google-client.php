<?php
/**
 * Thin client for the Google Indexing API.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * The only class that knows Google's URLs, request formats and error shapes.
 *
 * Authentication follows "Using OAuth 2.0 for Server to Server Applications": a JWT signed
 * with RS256 is exchanged for an access token at the token endpoint, then the token is sent
 * as a Bearer header to urlNotifications:publish. All calls go through the WordPress HTTP API
 * with TLS verification on and an explicit timeout.
 *
 * @link https://developers.google.com/identity/protocols/oauth2/service-account
 * @link https://developers.google.com/search/apis/indexing-api/v3/using-api
 */
class Fast_Index_Google_Client {

	/**
	 * OAuth 2.0 token endpoint. Fixed: the token_uri of an uploaded file is never used.
	 */
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Indexing API publish endpoint.
	 */
	const PUBLISH_URL = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

	/**
	 * OAuth scope of the Indexing API.
	 */
	const SCOPE = 'https://www.googleapis.com/auth/indexing';

	/**
	 * Grant type of the JWT bearer flow.
	 */
	const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

	/**
	 * Notification type: the URL is new or changed.
	 */
	const TYPE_UPDATED = 'URL_UPDATED';

	/**
	 * Notification type: the URL was removed.
	 */
	const TYPE_DELETED = 'URL_DELETED';

	/**
	 * Request timeout in seconds. Requests run in WP-Cron or after an explicit admin action.
	 */
	const TIMEOUT = 15;

	/**
	 * Lifetime of the signed JWT in seconds (Google accepts at most one hour).
	 */
	const JWT_LIFETIME = 3600;

	/**
	 * Seconds subtracted from expires_in before the token is cached.
	 */
	const TOKEN_MARGIN = 60;

	/**
	 * Prefix of the access token transient.
	 */
	const TOKEN_TRANSIENT_PREFIX = 'fast_index_token_';

	/**
	 * Largest response body read, in bytes.
	 */
	const MAX_RESPONSE_BYTES = 65536;

	/**
	 * Account fields: client_email, private_key, private_key_id.
	 *
	 * @var array
	 */
	private $account;

	/**
	 * Constructor.
	 *
	 * @param array $account Account from Fast_Index_Accounts.
	 */
	public function __construct( array $account ) {
		$this->account = $account;
	}

	/**
	 * Whether this PHP can sign requests (the OpenSSL extension is required).
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return function_exists( 'openssl_sign' ) && function_exists( 'openssl_pkey_get_private' );
	}

	/**
	 * Notification types Google accepts.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( self::TYPE_UPDATED, self::TYPE_DELETED );
	}

	/**
	 * Sends one URL notification.
	 *
	 * @param string $url  Absolute URL.
	 * @param string $type URL_UPDATED or URL_DELETED.
	 * @return array|WP_Error Decoded response on success. Errors carry data[status] (HTTP code, 0 without one) and data[context].
	 */
	public function publish( $url, $type ) {
		if ( ! in_array( $type, self::types(), true ) ) {
			return self::error( 'fast_index_bad_type', __( 'Unknown notification type.', 'fast-index' ), 0, 'publish' );
		}

		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$result = $this->post(
			self::PUBLISH_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'url'  => $url,
						'type' => $type,
					)
				),
			),
			'publish'
		);

		if ( 401 === self::error_status( $result ) ) {
			// The cached token was rejected: the next call asks for a new one.
			self::forget_token( $this->account );
		}

		return $result;
	}

	/**
	 * Returns a valid access token, from the cache when possible.
	 *
	 * @return string|WP_Error
	 */
	public function get_access_token() {
		$cache_key = self::token_cache_key( $this->account );
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$jwt = $this->build_jwt( time() );

		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$data = $this->post(
			self::TOKEN_URL,
			array(
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'grant_type' => self::GRANT_TYPE,
					'assertion'  => $jwt,
				),
			),
			'token'
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
			return self::error( 'fast_index_invalid_response', __( 'Google sent an unexpected response.', 'fast-index' ), 0, 'token' );
		}

		$ttl = isset( $data['expires_in'] ) ? (int) $data['expires_in'] - self::TOKEN_MARGIN : 0;

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $data['access_token'], $ttl );
		}

		return $data['access_token'];
	}

	/**
	 * Builds the signed JWT assertion.
	 *
	 * @param int $now Issue time (Unix timestamp).
	 * @return string|WP_Error
	 */
	public function build_jwt( $now ) {
		if ( ! self::is_supported() ) {
			return self::error( 'fast_index_openssl_missing', self::openssl_message(), 0, 'token' );
		}

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		if ( '' !== $this->account['private_key_id'] ) {
			$header['kid'] = $this->account['private_key_id'];
		}

		$claims = array(
			'iss'   => $this->account['client_email'],
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => (int) $now,
			'exp'   => (int) $now + self::JWT_LIFETIME,
		);

		$input = self::base64url( (string) wp_json_encode( $header ) ) . '.' . self::base64url( (string) wp_json_encode( $claims ) );
		$key   = openssl_pkey_get_private( $this->account['private_key'] );

		if ( false === $key ) {
			return self::error( 'fast_index_bad_private_key', __( 'The private key of the service account could not be read.', 'fast-index' ), 0, 'token' );
		}

		$signature = '';

		if ( ! openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			return self::error( 'fast_index_sign_failed', __( 'The request to Google could not be signed.', 'fast-index' ), 0, 'token' );
		}

		return $input . '.' . self::base64url( $signature );
	}

	/**
	 * Message shown when the OpenSSL extension is missing.
	 *
	 * @return string
	 */
	public static function openssl_message() {
		return __( 'The PHP OpenSSL extension is not available, so requests to Google cannot be signed. Ask your hosting provider to enable OpenSSL for PHP.', 'fast-index' );
	}

	/**
	 * Transient name of an account's access token. Bound to the key, so a new key gets a new token.
	 *
	 * @param array $account Account fields.
	 * @return string
	 */
	public static function token_cache_key( array $account ) {
		$fingerprint = $account['client_email'] . '|' . $account['private_key_id'] . '|' . md5( $account['private_key'] );

		return self::TOKEN_TRANSIENT_PREFIX . md5( $fingerprint );
	}

	/**
	 * Deletes the cached access token of an account.
	 *
	 * @param array $account Account fields.
	 * @return void
	 */
	public static function forget_token( array $account ) {
		delete_transient( self::token_cache_key( $account ) );
	}

	/**
	 * HTTP status stored in an error; 0 when the value is not an error or has no status.
	 *
	 * @param mixed $result Value returned by publish().
	 * @return int
	 */
	public static function error_status( $result ) {
		if ( ! is_wp_error( $result ) ) {
			return 0;
		}

		$data = $result->get_error_data();

		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
	}

	/**
	 * Context of an error: "token" or "publish".
	 *
	 * @param WP_Error $error Error returned by publish().
	 * @return string
	 */
	public static function error_context( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && isset( $data['context'] ) ? (string) $data['context'] : '';
	}

	/**
	 * Sends a POST request and returns the decoded JSON body.
	 *
	 * @param string $url     Endpoint.
	 * @param array  $args    Headers and body.
	 * @param string $context "token" or "publish".
	 * @return array|WP_Error
	 */
	private function post( $url, array $args, $context ) {
		$response = wp_safe_remote_post(
			$url,
			array_merge(
				array(
					'timeout'             => self::TIMEOUT,
					'redirection'         => 0,
					'limit_response_size' => self::MAX_RESPONSE_BYTES,
				),
				$args
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::error( 'fast_index_http_failed', __( 'Could not connect to Google.', 'fast-index' ), 0, $context );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$is_json = JSON_ERROR_NONE === json_last_error() && is_array( $data );

		if ( $code < 200 || $code >= 300 ) {
			return self::http_error( $code, $is_json ? $data : array(), $context );
		}

		if ( ! $is_json ) {
			return self::error( 'fast_index_invalid_response', __( 'Google sent an unexpected response.', 'fast-index' ), 0, $context );
		}

		return $data;
	}

	/**
	 * Builds the error of a non-2xx answer. Only a short machine reason is kept, never the body.
	 *
	 * @param int    $code    HTTP status code.
	 * @param array  $data    Decoded body, empty when it was not JSON.
	 * @param string $context "token" or "publish".
	 * @return WP_Error
	 */
	private static function http_error( $code, array $data, $context ) {
		$reason = '';

		if ( isset( $data['error']['status'] ) && is_string( $data['error']['status'] ) ) {
			$reason = $data['error']['status'];
		} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$reason = $data['error'];
		}

		return new WP_Error(
			'fast_index_http_error',
			/* translators: %d: HTTP status code returned by Google. */
			sprintf( __( 'Google answered with HTTP status %d.', 'fast-index' ), $code ),
			array(
				'status'  => (int) $code,
				'context' => $context,
				'reason'  => preg_replace( '/[^A-Za-z0-9_]/', '', substr( $reason, 0, 64 ) ),
			)
		);
	}

	/**
	 * Builds an error with status and context data.
	 *
	 * @param string $code    Error code.
	 * @param string $message Translated message.
	 * @param int    $status  HTTP status, 0 without one.
	 * @param string $context "token" or "publish".
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status, $context ) {
		return new WP_Error(
			$code,
			$message,
			array(
				'status'  => (int) $status,
				'context' => $context,
			)
		);
	}

	/**
	 * Base64url encoding without padding (RFC 7515, section 2).
	 *
	 * @param string $data Raw bytes.
	 * @return string
	 */
	private static function base64url( $data ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT segments must be base64url encoded (RFC 7519); no code is hidden.
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
