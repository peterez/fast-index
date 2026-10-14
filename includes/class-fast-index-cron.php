<?php
/**
 * WP-Cron events of the plugin.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers, schedules and runs the plugin's WP-Cron events.
 *
 * - fast_index_submit_url: one queued notification (single event, queued on post save).
 * - fast_index_process_backlog: hourly batch of old posts (the "daily old content" setting).
 */
class Fast_Index_Cron {

	/**
	 * Single event that sends one queued URL.
	 */
	const SUBMIT_HOOK = 'fast_index_submit_url';

	/**
	 * Recurring event that sends a batch of old posts.
	 */
	const BACKLOG_HOOK = 'fast_index_process_backlog';

	/**
	 * Recurring event of version 2.x (every ten minutes). Removed on upgrade.
	 */
	const LEGACY_HOOK = 'fiDailyCronHook';

	/**
	 * Recurrence of the backlog event (core schedule).
	 */
	const BACKLOG_RECURRENCE = 'hourly';

	/**
	 * Connects the events to their handlers. Runs on every request; never schedules.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( self::SUBMIT_HOOK, array( $this, 'run_submit' ), 10, 3 );
		add_action( self::BACKLOG_HOOK, array( $this, 'run_backlog' ) );
	}

	/**
	 * Schedules the recurring backlog event once. Called on activation and upgrade.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::BACKLOG_HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, self::BACKLOG_RECURRENCE, self::BACKLOG_HOOK );
		}
	}

	/**
	 * Removes every event of the plugin, including the 2.x event. Called on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_unschedule_hook( self::SUBMIT_HOOK );
		wp_unschedule_hook( self::BACKLOG_HOOK );
		wp_unschedule_hook( self::LEGACY_HOOK );
	}

	/**
	 * Queues one notification. Identical events within ten minutes are merged by WordPress.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    URL_UPDATED or URL_DELETED.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function queue_submission( $url, $type, $post_id ) {
		wp_schedule_single_event( time(), self::SUBMIT_HOOK, array( (string) $url, (string) $type, (int) $post_id ) );
	}

	/**
	 * Sends one queued notification.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    Notification type.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function run_submit( $url, $type, $post_id = 0 ) {
		if ( ! Fast_Index_Post_Watcher::is_http_url( $url ) || ! in_array( $type, Fast_Index_Google_Client::types(), true ) ) {
			return;
		}

		( new Fast_Index_Submitter() )->submit( $url, $type, absint( $post_id ) );
	}

	/**
	 * Sends one batch of old posts.
	 *
	 * @return void
	 */
	public function run_backlog() {
		Fast_Index_Backlog::run();
	}
}
