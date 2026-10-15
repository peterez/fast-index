<?php
/**
 * Sends one URL notification with the stored service accounts.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends with the main key, pauses keys that fail and logs the outcome.
 *
 * - A rejected key (401, 403, or a 4xx answer at the token endpoint) is paused for six hours, and the next key in
 *   use (a backup key of the premium plan) is tried.
 * - A quota answer (429) ends the attempt: every key is paused until Google resets the daily quota, and no other
 *   key is tried. Google counts the quota for each project and forbids using several accounts to get past it.
 * - Request errors (other 4xx) and Google or network outages (5xx, no answer) end the attempt without a pause.
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
	 * Result: accounts are stored, but every one of them is paused (rejected key or daily quota).
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
	 *               is paused, before or after this attempt, so a later attempt may succeed).
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

		// Every key that was tried was rejected, or the daily quota is used up: every key is paused now.
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
	 * Whether a result code means that Google did not answer or had a server error (5xx). The next requests
	 * would most likely wait and fail the same way, so a run stops sending to Google for now.
	 *
	 * @param int $code Result code.
	 * @return bool
	 */
	public static function is_outage( $code ) {
		$code = (int) $code;

		return self::NO_RESPONSE === $code || $code >= 500;
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
	 * Whether an error says that Google rejected the key, so a backup key may succeed.
	 *
	 * @param WP_Error $error Error returned by the client.
	 * @return bool
	 */
	public static function is_rejected_key( WP_Error $error ) {
		$status = Fast_Index_Google_Client::error_status( $error );

		if ( self::is_quota_error( $error ) ) {
			return false;
		}

		if ( 'token' === Fast_Index_Google_Client::error_context( $error ) ) {
			return $status >= 400 && $status < 500;
		}

		return in_array( $status, array( 401, 403 ), true );
	}

	/**
	 * Whether an error says that the quota is used up. No other key may be tried then.
	 *
	 * @param WP_Error $error Error returned by the client.
	 * @return bool
	 */
	public static function is_quota_error( WP_Error $error ) {
		return 429 === Fast_Index_Google_Client::error_status( $error );
	}

	/**
	 * Whether an error pauses a key: a rejected key or the quota.
	 *
	 * @param WP_Error $error Error returned by the client.
	 * @return bool
	 */
	public static function is_account_error( WP_Error $error ) {
		return self::is_rejected_key( $error ) || self::is_quota_error( $error );
	}

	/**
	 * Sends with the main key; a backup key is tried only when Google rejected the key before it.
	 *
	 * @param array[] $accounts Available accounts keyed by ID, main key first.
	 * @param string  $url      Absolute URL.
	 * @param string  $type     Notification type.
	 * @return int
	 */
	private function send_with_accounts( array $accounts, $url, $type ) {
		$code = self::NO_RESPONSE;

		foreach ( $accounts as $id => $account ) {
			$result = ( new Fast_Index_Google_Client( $account ) )->publish( $url, $type );

			if ( ! is_wp_error( $result ) ) {
				Fast_Index_Accounts::mark_working( $id );
				return 200;
			}

			$code = Fast_Index_Google_Client::error_status( $result );

			if ( self::is_quota_error( $result ) ) {
				// The quota belongs to the project, not to the key: every key waits for the reset.
				Fast_Index_Accounts::remember_status( $id, $code );
				Fast_Index_Accounts::pause_for_quota();
				return $code;
			}

			if ( ! self::is_rejected_key( $result ) ) {
				return $code;
			}

			Fast_Index_Accounts::set_status( $id, $code );
		}

		return $code;
	}
}
