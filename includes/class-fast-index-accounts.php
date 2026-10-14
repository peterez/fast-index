<?php
/**
 * Storage of the Google service account keys.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps service account keys in a non-autoloaded option and tracks their last status.
 *
 * Keys are never written to disk. Only the client email and the key ID are ever shown;
 * the private key never leaves this option and the HTTP client.
 *
 * The free version sends with one key: the first stored key. Keys stored after it (saved by the premium
 * module, which keeps several) are kept but not used until the module uses them again.
 */
class Fast_Index_Accounts {

	/**
	 * Option holding the keys (autoload off).
	 */
	const OPTION = 'fast_index_service_accounts';

	/**
	 * Option holding the last HTTP status of each account, for display (autoload off).
	 */
	const STATUS_OPTION = 'fast_index_account_statuses';

	/**
	 * Prefix of the per-account status transient.
	 */
	const STATUS_TRANSIENT_PREFIX = 'fast_index_account_';

	/**
	 * How long a failing account is skipped, in seconds (six hours, as in 2.x).
	 */
	const STATUS_TTL = 21600;

	/**
	 * Largest accepted key file, in bytes. Google key files are about 2.4 KB.
	 */
	const MAX_KEY_BYTES = 65536;

	/**
	 * All stored accounts, keyed by account ID.
	 *
	 * @return array[]
	 */
	public static function all() {
		$stored   = get_option( self::OPTION, array() );
		$accounts = array();

		if ( ! is_array( $stored ) ) {
			return $accounts;
		}

		foreach ( $stored as $id => $account ) {
			if ( is_array( $account ) && isset( $account['client_email'], $account['private_key'] ) ) {
				$accounts[ (string) $id ] = array(
					'client_email'   => (string) $account['client_email'],
					'private_key'    => (string) $account['private_key'],
					'private_key_id' => isset( $account['private_key_id'] ) ? (string) $account['private_key_id'] : '',
				);
			}
		}

		return $accounts;
	}

	/**
	 * Whether several keys are kept and used. False in the free version: a new key replaces the key in use.
	 *
	 * @return bool
	 */
	public static function several_keys() {
		/**
		 * Filters whether Fast Index keeps and uses several service account keys.
		 *
		 * @param bool $several False: one key, the first stored one.
		 */
		return (bool) apply_filters( 'fast_index_use_several_keys', false );
	}

	/**
	 * Accounts used for sending: every stored account when several keys are used, otherwise the first one.
	 *
	 * @return array[]
	 */
	public static function in_use() {
		$accounts = self::all();

		return self::several_keys() ? $accounts : array_slice( $accounts, 0, 1, true );
	}

	/**
	 * Accounts in use that are not resting after an error.
	 *
	 * @return array[]
	 */
	public static function available() {
		$available = array();

		foreach ( self::in_use() as $id => $account ) {
			if ( 0 === self::rest_end( $id ) ) {
				$available[ $id ] = $account;
			}
		}

		return $available;
	}

	/**
	 * When the first account in use can be used again.
	 *
	 * @return int Unix time: now when an account is available, the earliest end of a rest when
	 *             every account rests, 0 when no account is stored.
	 */
	public static function next_ready_time() {
		$next = 0;

		foreach ( array_keys( self::in_use() ) as $id ) {
			$end = self::rest_end( $id );

			if ( 0 === $end ) {
				return time();
			}

			$next = 0 === $next ? $end : min( $next, $end );
		}

		return $next;
	}

	/**
	 * End of an account's rest.
	 *
	 * @param string $id Account ID.
	 * @return int Unix time, 0 when the account is not resting.
	 */
	private static function rest_end( $id ) {
		$status = get_transient( self::STATUS_TRANSIENT_PREFIX . $id );

		if ( false === $status ) {
			return 0;
		}

		// Values written before the end time was stored hold only the code; their rest ends within STATUS_TTL.
		$code = is_array( $status ) && isset( $status['code'] ) ? (int) $status['code'] : (int) $status;
		$end  = is_array( $status ) && isset( $status['until'] ) ? (int) $status['until'] : time() + self::STATUS_TTL;

		return 200 === $code || $end <= time() ? 0 : $end;
	}

	/**
	 * Builds the account ID from the client email.
	 *
	 * @param string $email Client email.
	 * @return string
	 */
	public static function id_for( $email ) {
		return md5( strtolower( trim( (string) $email ) ) );
	}

	/**
	 * Whether an account with this client email is stored.
	 *
	 * @param string $email Client email.
	 * @return bool
	 */
	public static function has_email( $email ) {
		return '' !== (string) $email && array_key_exists( self::id_for( $email ), self::all() );
	}

	/**
	 * Adds an account or replaces the key of the same client email.
	 *
	 * @param array $account Account returned by parse_key_json().
	 * @return string Account ID.
	 */
	public static function save( array $account ) {
		$accounts = self::all();
		$id       = self::id_for( $account['client_email'] );

		if ( isset( $accounts[ $id ] ) ) {
			Fast_Index_Google_Client::forget_token( $accounts[ $id ] );
		}

		$accounts[ $id ] = $account;
		self::store( $accounts );

		return $id;
	}

	/**
	 * Makes an account the key in use (the first one). The key that was in use is replaced and deleted; other
	 * stored keys stay where they are. With one stored key this is the 2.x behaviour: a new upload replaces it.
	 *
	 * @param array $account Account returned by parse_key_json().
	 * @return string Account ID.
	 */
	public static function use_key( array $account ) {
		$id    = self::id_for( $account['client_email'] );
		$first = array_key_first( self::all() );

		if ( null !== $first && $first !== $id ) {
			self::delete( $first );
		}

		$accounts = self::all();

		if ( isset( $accounts[ $id ] ) ) {
			Fast_Index_Google_Client::forget_token( $accounts[ $id ] );
			unset( $accounts[ $id ] );
		}

		self::store( array( $id => $account ) + $accounts );

		return $id;
	}

	/**
	 * Deletes one account with its cached token and status.
	 *
	 * @param string $id Account ID.
	 * @return bool False when the account does not exist.
	 */
	public static function delete( $id ) {
		$accounts = self::all();

		if ( ! isset( $accounts[ $id ] ) ) {
			return false;
		}

		Fast_Index_Google_Client::forget_token( $accounts[ $id ] );
		unset( $accounts[ $id ] );
		self::store( $accounts );

		delete_transient( self::STATUS_TRANSIENT_PREFIX . $id );

		$statuses = self::statuses();
		unset( $statuses[ $id ] );
		update_option( self::STATUS_OPTION, $statuses, false );

		return true;
	}

	/**
	 * Records the last HTTP status of an account. Any status other than 200 rests the account.
	 *
	 * @param string $id   Account ID.
	 * @param int    $code HTTP status code.
	 * @return void
	 */
	public static function set_status( $id, $code ) {
		set_transient(
			self::STATUS_TRANSIENT_PREFIX . $id,
			array(
				'code'  => (int) $code,
				'until' => time() + self::STATUS_TTL,
			),
			self::STATUS_TTL
		);
		self::remember_status( $id, $code );
	}

	/**
	 * Stores the status shown on the settings screen without resting the account.
	 *
	 * @param string $id   Account ID.
	 * @param int    $code HTTP status code.
	 * @return void
	 */
	public static function remember_status( $id, $code ) {
		$code     = (int) $code;
		$statuses = self::statuses();

		if ( isset( $statuses[ $id ] ) && $statuses[ $id ] === $code ) {
			return;
		}

		$statuses[ $id ] = $code;
		update_option( self::STATUS_OPTION, $statuses, false );
	}

	/**
	 * Last known HTTP status of every account.
	 *
	 * @return int[]
	 */
	public static function statuses() {
		$statuses = get_option( self::STATUS_OPTION, array() );

		return is_array( $statuses ) ? array_map( 'intval', $statuses ) : array();
	}

	/**
	 * Validates the content of a service account key file.
	 *
	 * @param mixed $json File content.
	 * @return array|WP_Error Account fields ( client_email, private_key, private_key_id ).
	 */
	public static function parse_key_json( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) || strlen( $json ) > self::MAX_KEY_BYTES ) {
			return new WP_Error( 'fast_index_key_size', __( 'The file is empty or too large to be a service account key.', 'fast-index' ) );
		}

		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'fast_index_key_json', __( 'The file is not valid JSON.', 'fast-index' ) );
		}

		if ( ! isset( $data['type'] ) || 'service_account' !== $data['type'] ) {
			return new WP_Error( 'fast_index_key_type', __( 'The file is not a Google service account key.', 'fast-index' ) );
		}

		$email = isset( $data['client_email'] ) && is_string( $data['client_email'] ) ? $data['client_email'] : '';

		if ( '' === $email || ! is_email( $email ) || sanitize_email( $email ) !== $email ) {
			return new WP_Error( 'fast_index_key_email', __( 'The key does not contain a valid client email.', 'fast-index' ) );
		}

		$private_key = isset( $data['private_key'] ) && is_string( $data['private_key'] ) ? trim( $data['private_key'] ) : '';

		if ( ! self::is_private_key( $private_key ) ) {
			return new WP_Error( 'fast_index_key_private', __( 'The key does not contain a valid private key.', 'fast-index' ) );
		}

		$key_id = isset( $data['private_key_id'] ) && is_string( $data['private_key_id'] ) ? $data['private_key_id'] : '';

		return array(
			'client_email'   => $email,
			'private_key'    => $private_key,
			'private_key_id' => 1 === preg_match( '/^[A-Za-z0-9_-]{1,128}$/', $key_id ) ? $key_id : '',
		);
	}

	/**
	 * Whether a string is a PEM private key. OpenSSL parses it when the extension is loaded.
	 *
	 * @param string $pem PEM text.
	 * @return bool
	 */
	private static function is_private_key( $pem ) {
		if ( 1 !== preg_match( '/^-----BEGIN (RSA )?PRIVATE KEY-----[A-Za-z0-9+\/=\s]+-----END (RSA )?PRIVATE KEY-----$/', $pem ) ) {
			return false;
		}

		if ( ! function_exists( 'openssl_pkey_get_private' ) ) {
			return true;
		}

		return false !== openssl_pkey_get_private( $pem );
	}

	/**
	 * Writes the accounts option with autoload off.
	 *
	 * @param array[] $accounts Accounts keyed by ID.
	 * @return void
	 */
	private static function store( array $accounts ) {
		update_option( self::OPTION, $accounts, false );
	}
}
