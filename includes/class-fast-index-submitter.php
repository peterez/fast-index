<?php
/**
 * Sends one URL notification with the stored service accounts.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Picks accounts in random order, rests accounts that fail and logs the outcome.
 *
 * Account errors (quota 429, 401, 403, or a rejected key at the token endpoint) rest the
 * account for six hours and the next account is tried. Request errors (other 4xx) and
 * Google or network outages (5xx, no answer) stop the attempt without resting accounts.
 */
class Fast_Index_Submitter {

	/**
	 * Result: no account is stored.
	 */
	const NO_ACCOUNT = -1;

	/**
	 * Result: this PHP cannot sign requests (OpenSSL missing).
	 */
	const UNSUPPORTED = -2;

	/**
	 * Result: accounts are stored, but every one of them is resting after an error.
	 */
	const RESTING = -3;

	/**
	 * Result: no HTTP status (connection failure or unexpected answer).
	 */
	const NO_RESPONSE = 0;

	/**
	 * Sends a notification and logs the result.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    URL_UPDATED or URL_DELETED.
	 * @param int    $post_id Post the URL belongs to, 0 when unknown.
	 * @return int 200 on success, the last HTTP status on failure, or one of the class constants.
	 */
	public function submit( $url, $type, $post_id = 0 ) {
		$result = $this->send( $url, $type, $post_id );

		return $result['code'];
	}

	/**
	 * Sends a notification and logs the attempt. Nothing is logged when no attempt was possible.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    URL_UPDATED or URL_DELETED.
	 * @param int    $post_id Post the URL belongs to, 0 when unknown.
	 * @return array Keys: code (as submit()), log_id (history entry of the attempt, 0 when nothing was
	 *               sent) and retry (true when no key could take the notification because every key
	 *               rests, before or after this attempt, so a later attempt may succeed).
	 */
	public function send( $url, $type, $post_id = 0 ) {
		if ( ! Fast_Index_Google_Client::is_supported() ) {
			return self::result( self::UNSUPPORTED );
		}

		if ( array() === Fast_Index_Accounts::all() ) {
			return self::result( self::NO_ACCOUNT );
		}

		$accounts = Fast_Index_Accounts::available();

		if ( array() === $accounts ) {
			return self::result( self::RESTING, 0, true );
		}

		$code   = $this->send_with_accounts( $accounts, $url, $type );
		$log_id = Fast_Index_Log::add( $post_id, $url, $code, $type );

		// Every key that was tried answered with an account error and rests now.
		return self::result( $code, $log_id, 200 !== $code && array() === Fast_Index_Accounts::available() );
	}

	/**
	 * Whether a result code means that no request was made.
	 *
	 * @param int $code Result code.
	 * @return bool
	 */
	public static function is_not_sent( $code ) {
		return in_array( (int) $code, array( self::NO_ACCOUNT, self::UNSUPPORTED, self::RESTING ), true );
	}

	/**
	 * Builds the result of send().
	 *
	 * @param int  $code   Result code.
	 * @param int  $log_id History entry ID.
	 * @param bool $retry  Whether a later attempt may succeed.
	 * @return array
	 */
	private static function result( $code, $log_id = 0, $retry = false ) {
		return array(
			'code'   => (int) $code,
			'log_id' => (int) $log_id,
			'retry'  => (bool) $retry,
		);
	}

	/**
	 * Whether an error belongs to the account (so another account may succeed).
	 *
	 * @param WP_Error $error Error returned by the client.
	 * @return bool
	 */
	public static function is_account_error( WP_Error $error ) {
		$status = Fast_Index_Google_Client::error_status( $error );

		if ( 'token' === Fast_Index_Google_Client::error_context( $error ) ) {
			return $status >= 400 && $status < 500;
		}

		return in_array( $status, array( 401, 403, 429 ), true );
	}

	/**
	 * Tries the accounts in random order until one succeeds or an error is not account related.
	 *
	 * @param array[] $accounts Available accounts keyed by ID.
	 * @param string  $url      Absolute URL.
	 * @param string  $type     Notification type.
	 * @return int
	 */
	private function send_with_accounts( array $accounts, $url, $type ) {
		$ids  = array_keys( $accounts );
		$code = self::NO_RESPONSE;

		shuffle( $ids );

		foreach ( $ids as $id ) {
			$result = ( new Fast_Index_Google_Client( $accounts[ $id ] ) )->publish( $url, $type );

			if ( ! is_wp_error( $result ) ) {
				Fast_Index_Accounts::set_status( $id, 200 );
				return 200;
			}

			$code = Fast_Index_Google_Client::error_status( $result );

			if ( ! self::is_account_error( $result ) ) {
				return $code;
			}

			Fast_Index_Accounts::set_status( $id, $code );
		}

		return $code;
	}
}
