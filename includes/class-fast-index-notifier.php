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
 * - "Send to Google only pages it supports" (eligible_only) is on and the post has no JobPosting or BroadcastEvent
 *   data in its saved content (Fast_Index_Eligibility). IndexNow still gets it when it is on.
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
	 * Sends several notifications (chosen by hand, or a batch of the queue). Google gets them one by one within a
	 * time budget; what is left is queued for the next scheduled-task run (both channels). When Google does not
	 * answer or answers 5xx, the rest is queued too, so one outage costs one request and not one per address.
	 * IndexNow gets the addresses handled now in one request.
	 *
	 * @param array[] $items  Each: url, type, post_id.
	 * @param int     $budget Seconds the Google requests may take.
	 * @return array Keys: google (accepted, failed: counts), queued (count), indexnow (result of
	 *               Fast_Index_IndexNow::submit(), null when IndexNow is off or nothing was handled now), outage
	 *               (bool: Google did not answer or answered 5xx).
	 */
	public static function deliver_many( array $items, $budget ) {
		$settings = Fast_Index_Settings::get();
		$deadline = time() + (int) $budget;
		$now      = array();
		$result   = array(
			'google'   => array(
				'accepted' => 0,
				'failed'   => 0,
			),
			'queued'   => 0,
			'indexnow' => null,
			'outage'   => false,
		);

		foreach ( $items as $item ) {
			if ( $result['outage'] || time() >= $deadline ) {
				Fast_Index_Queue::add( $item['url'], $item['type'], $item['post_id'] );
				++$result['queued'];
				continue;
			}

			$now[] = $item;

			if ( self::wants_google( $settings, $item['post_id'] ) ) {
				$sent = Fast_Index_Retry::deliver( $item['url'], $item['type'], $item['post_id'] );
				++$result['google'][ 200 === $sent['code'] ? 'accepted' : 'failed' ];
				$result['outage'] = Fast_Index_Submitter::is_outage( $sent['code'] );
			}
		}

		if ( array() !== $now && Fast_Index_IndexNow::is_enabled( $settings ) ) {
			$result['indexnow'] = Fast_Index_IndexNow::submit( $now );
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

		if ( ! empty( $settings['eligible_only'] ) && ( 0 === absint( $post_id ) || ! Fast_Index_Eligibility::is_eligible( absint( $post_id ) ) ) ) {
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
