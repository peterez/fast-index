<?php
/**
 * IndexNow client.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends changed addresses to IndexNow and serves the key file.
 *
 * IndexNow is off until the user turns it on (setting "indexnow"); while it is off this class makes no request and
 * serves no file. When it is on:
 * - a key of 32 letters and digits is created once per site and stored in a non-autoloaded option;
 * - the key file is answered from memory at {home URL}/{key}.txt on parse_request, without a file on disk and
 *   without a rewrite rule; the keyLocation sent with every request points to it, so a site in a sub-directory
 *   of a network covers its own addresses;
 * - addresses are posted as one JSON list to https://api.indexnow.org/indexnow, which shares them with the search
 *   engines that take part in IndexNow. Each address gets a History entry in the IndexNow channel.
 *
 * Answers (indexnow.org documentation): 200 submitted, 202 received and the key is checked later, 400 bad
 * request, 403 key not valid (key file missing or different), 422 address of another host or key mismatch,
 * 429 too many requests (possible spam). 200 and 202 count as success. Nothing is retried.
 *
 * @link https://www.indexnow.org/documentation
 */
class Fast_Index_IndexNow {

	/**
	 * Global IndexNow endpoint (it shares the addresses with every search engine that takes part).
	 */
	const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Per-site option holding the key (autoload off: read only for the key file and for sending).
	 */
	const KEY_OPTION = 'fast_index_indexnow_key';

	/**
	 * Length of a created key. IndexNow accepts 8 to 128 letters, digits and dashes.
	 */
	const KEY_LENGTH = 32;

	/**
	 * Most addresses in one request (IndexNow accepts up to 10,000).
	 */
	const MAX_URLS = 10000;

	/**
	 * Request timeout in seconds.
	 */
	const TIMEOUT = 15;

	/**
	 * Registers the key file handler.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'parse_request', array( __CLASS__, 'serve_key_file' ), 0 );
	}

	/**
	 * Whether IndexNow is turned on.
	 *
	 * @param array|null $settings Clean settings; null reads them.
	 * @return bool
	 */
	public static function is_enabled( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : Fast_Index_Settings::get();

		return ! empty( $settings['indexnow'] );
	}

	/**
	 * The key of this site.
	 *
	 * @param bool $create Whether to create the key when none is stored.
	 * @return string Empty when no key is stored and none was created.
	 */
	public static function key( $create = false ) {
		$key = get_option( self::KEY_OPTION, '' );

		if ( is_string( $key ) && self::is_valid_key( $key ) ) {
			return $key;
		}

		if ( ! $create ) {
			return '';
		}

		$key = wp_generate_password( self::KEY_LENGTH, false, false );
		update_option( self::KEY_OPTION, $key, false );

		return $key;
	}

	/**
	 * Whether a string has the form IndexNow accepts for a key.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function is_valid_key( $key ) {
		return 1 === preg_match( '/^[A-Za-z0-9-]{8,128}$/', (string) $key );
	}

	/**
	 * Address of the key file.
	 *
	 * @param string $key Key; empty uses the stored key.
	 * @return string Empty when no key is stored.
	 */
	public static function key_url( $key = '' ) {
		$key = '' !== $key ? $key : self::key();

		return '' === $key ? '' : home_url( '/' . $key . '.txt' );
	}

	/**
	 * The key file body for a request path, when the path is the key file of this site and IndexNow is on.
	 *
	 * @param string $path Path of the request, without the query string.
	 * @return string|null The key, or null when the request is something else.
	 */
	public static function key_file_body( $path ) {
		$path = (string) $path;

		// Cheap test first: only a request for a .txt file named like a key reads the settings and the key.
		if ( 1 !== preg_match( '#/([A-Za-z0-9-]{8,128})\.txt$#', $path, $match ) || ! self::is_enabled() ) {
			return null;
		}

		$key  = self::key();
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( '' === $key || $match[1] !== $key || '/' . ltrim( $path, '/' ) !== trailingslashit( '/' . ltrim( $home, '/' ) ) . $key . '.txt' ) {
			return null;
		}

		return $key;
	}

	/**
	 * Answers a request for the key file with the key as plain text and stops. Runs on parse_request.
	 *
	 * @return void
	 */
	public static function serve_key_file() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$body = self::key_file_body( rawurldecode( $path ) );

		if ( null === $body ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( $body );
		exit;
	}

	/**
	 * Sends addresses to IndexNow in one request and writes one History entry per address.
	 *
	 * @param array[] $items Each: url, type (URL_UPDATED or URL_DELETED), post_id.
	 * @return array Keys: code (HTTP status, 0 without an answer, -1 when nothing was sent), sent (number of
	 *               addresses in the request), accepted (bool).
	 */
	public static function submit( array $items ) {
		$result = array(
			'code'     => -1,
			'sent'     => 0,
			'accepted' => false,
		);

		if ( ! self::is_enabled() ) {
			return $result;
		}

		$key  = self::key( true );
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$list = array();

		foreach ( $items as $item ) {
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';

			// IndexNow refuses (422) addresses of another host than the key's.
			if ( Fast_Index_Post_Watcher::is_http_url( $url ) && strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === strtolower( $host ) && ! isset( $list[ $url ] ) ) {
				$list[ $url ] = $item;
			}
		}

		if ( array() === $list ) {
			return $result;
		}

		$list     = array_slice( $list, 0, self::MAX_URLS, true );
		$response = wp_safe_remote_post(
			self::ENDPOINT,
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => 65536,
				'headers'             => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'                => wp_json_encode(
					array(
						'host'        => $host,
						'key'         => $key,
						'keyLocation' => self::key_url( $key ),
						'urlList'     => array_keys( $list ),
					)
				),
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		foreach ( $list as $url => $item ) {
			Fast_Index_Log::add_indexnow( isset( $item['post_id'] ) ? (int) $item['post_id'] : 0, $url, $code, isset( $item['type'] ) ? (string) $item['type'] : Fast_Index_Google_Client::TYPE_UPDATED );
		}

		return array(
			'code'     => $code,
			'sent'     => count( $list ),
			'accepted' => in_array( $code, array( 200, 202 ), true ),
		);
	}
}
