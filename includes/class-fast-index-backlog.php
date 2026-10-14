<?php
/**
 * Sends old posts that were never submitted.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends a small batch of old posts within the daily limit set by the user.
 *
 * The batch size keeps the 2.x rule: a random number between 0 and a third of the daily
 * limit per run, never more than what is left of the limit for the last 24 hours.
 */
class Fast_Index_Backlog {

	/**
	 * Seconds one run may spend sending.
	 */
	const TIME_BUDGET = 20;

	/**
	 * Sends one batch.
	 *
	 * @return array[] One row per post: post_id, url, code.
	 */
	public static function run() {
		$settings = Fast_Index_Settings::get();

		if ( ! Fast_Index_Settings::is_active( $settings ) ) {
			return array();
		}

		$size = self::batch_size( (int) $settings['old_post_number'], Fast_Index_Log::count_sent_since( time() - DAY_IN_SECONDS ) );

		if ( $size <= 0 ) {
			return array();
		}

		return self::send( Fast_Index_Log::backlog_ids( $settings, $size ) );
	}

	/**
	 * Number of posts to send now.
	 *
	 * @param int $daily_limit Daily limit from the settings.
	 * @param int $sent_today  Successful submissions in the last 24 hours.
	 * @return int
	 */
	public static function batch_size( $daily_limit, $sent_today ) {
		$remaining = $daily_limit - $sent_today;

		if ( $daily_limit <= 0 || $remaining <= 0 ) {
			return 0;
		}

		return min( $remaining, wp_rand( 0, (int) ceil( $daily_limit / 3 ) ) );
	}

	/**
	 * Sends the posts until the time budget is spent or no account is left.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array[]
	 */
	private static function send( array $post_ids ) {
		$submitter = new Fast_Index_Submitter();
		$deadline  = time() + self::TIME_BUDGET;
		$results   = array();

		foreach ( $post_ids as $post_id ) {
			$url = get_permalink( $post_id );

			if ( ! Fast_Index_Post_Watcher::is_http_url( $url ) ) {
				continue;
			}

			$code      = $submitter->submit( $url, Fast_Index_Google_Client::TYPE_UPDATED, $post_id );
			$results[] = array(
				'post_id' => (int) $post_id,
				'url'     => $url,
				'code'    => $code,
			);

			if ( $code < 0 || time() >= $deadline ) {
				break;
			}
		}

		return $results;
	}
}
