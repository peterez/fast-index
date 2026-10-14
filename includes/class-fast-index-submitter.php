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
	 * Result: no account is available (none stored, or all resting).
	 */
	const NO_ACCOUNT = -1;

	/**
	 * Result: this PHP cannot sign requests (OpenSSL missing).
	 */
	const UNSUPPORTED = -2;

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
		if ( ! Fast_Index_Google_Client::is_supported() ) {
			return self::UNSUPPORTED;
		}

		$accounts = Fast_Index_Accounts::available();

		if ( array() === $accounts ) {
			return self::NO_ACCOUNT;
		}

		$code = $this->send_with_accounts( $accounts, $url, $type );

		Fast_Index_Log::add( $post_id, $url, $code, $type );

		return $code;
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
