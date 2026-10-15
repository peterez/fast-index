<?php
/**
 * Routes a notification to the channels that should get it.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides which channel gets a notification and sends it there: Google (Fast_Index_Retry, with the History entry
 * and the retry queue) and IndexNow (Fast_Index_IndexNow, when it is turned on).
 *
 * Google is left out, without a History entry, when
 * - IndexNow is on and no Google key is stored: the site uses IndexNow only;
 * - "Send to Google only pages it supports" is on and the post has no JobPosting or BroadcastEvent data
 *   (Fast_Index_Eligibility).
 */
class Fast_Index_Notifier {

	/**
	 * Sends one queued notification to its channels.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    URL_UPDATED or URL_DELETED.
	 * @param int    $post_id Post ID, 0 when unknown.
	 * @return array Keys: google (result of Fast_Index_Retry::deliver(), null when Google was left out), indexnow
	 *               (result of Fast_Index_IndexNow::submit(), null when IndexNow is off).
	 */
	public static function deliver( $url, $type, $post_id ) {
		$settings = Fast_Index_Settings::get();
		$result   = array(
			'google'   => null,
			'indexnow' => null,
		);

		if ( self::wants_google( $settings, $post_id ) ) {
			$result['google'] = Fast_Index_Retry::deliver( $url, $type, $post_id );
		}

		if ( Fast_Index_IndexNow::is_enabled( $settings ) ) {
			$result['indexnow'] = Fast_Index_IndexNow::submit(
				array(
					array(
						'url'     => $url,
						'type'    => $type,
						'post_id' => $post_id,
					),
				)
			);
		}

		return $result;
	}

	/**
	 * Whether a notification goes to Google.
	 *
	 * @param array $settings Clean settings.
	 * @param int   $post_id  Post ID, 0 when unknown.
	 * @return bool
	 */
	public static function wants_google( array $settings, $post_id ) {
		if ( Fast_Index_IndexNow::is_enabled( $settings ) && array() === Fast_Index_Accounts::all() ) {
			return false;
		}

		/**
		 * Filters whether a notification goes to the Google Indexing API.
		 *
		 * @param bool  $google   Whether Google gets the notification.
		 * @param int   $post_id  Post ID, 0 when unknown.
		 * @param array $settings Clean settings.
		 */
		return (bool) apply_filters( 'fast_index_send_to_google', true, absint( $post_id ), $settings );
	}
}
