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
 * - fast_index_process_queue: sends the queue of notifications (single event, Fast_Index_Queue).
 * - fast_index_process_backlog: hourly upkeep. Checks the notifications that wait for a key, then fires the
 *   fast_index_hourly action (the premium module sends its batch of old posts there). The event name is the
 *   one of the 2.x old-post batch, so existing schedules keep working.
 * - fast_index_retry: notifications that wait for a resting key (single event, Fast_Index_Retry).
 */
class Fast_Index_Cron {

	/**
	 * Single event of the 3.0.0 development builds that sent one queued URL. No longer scheduled: an event that is
	 * still planned is moved into Fast_Index_Queue when it runs, on upgrade and by the hourly check.
	 */
	const SUBMIT_HOOK = 'fast_index_submit_url';

	/**
	 * Recurring hourly event.
	 */
	const HOURLY_HOOK = 'fast_index_process_backlog';

	/**
	 * Recurring event of version 2.x (every ten minutes). Removed on upgrade.
	 */
	const LEGACY_HOOK = 'fiDailyCronHook';

	/**
	 * Weekly event of the premium module's email report. Removed with the other events on deactivation.
	 */
	const REPORT_HOOK = 'fast_index_weekly_report';

	/**
	 * Recurrence of the hourly event (core schedule).
	 */
	const HOURLY_RECURRENCE = 'hourly';

	/**
	 * Connects the events to their handlers. Runs on every request; never schedules.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( self::SUBMIT_HOOK, array( $this, 'run_submit' ), 10, 3 );
		add_action( Fast_Index_Queue::HOOK, array( 'Fast_Index_Queue', 'run' ) );
		add_action( self::HOURLY_HOOK, array( $this, 'run_hourly' ) );
		add_action( Fast_Index_Retry::HOOK, array( 'Fast_Index_Retry', 'run' ) );
	}

	/**
	 * Schedules the recurring hourly event once, and the notifications that were waiting for a key
	 * when the plugin was deactivated. Called on activation and upgrade.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOURLY_HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, self::HOURLY_RECURRENCE, self::HOURLY_HOOK );
		}

		Fast_Index_Retry::resume();
		Fast_Index_Queue::adopt_legacy_events();
		Fast_Index_Queue::resume();
	}

	/**
	 * Removes every event of the plugin, including the 2.x event. Called on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_unschedule_hook( self::SUBMIT_HOOK );
		wp_unschedule_hook( Fast_Index_Queue::HOOK );
		wp_unschedule_hook( self::HOURLY_HOOK );
		wp_unschedule_hook( Fast_Index_Retry::HOOK );
		wp_unschedule_hook( self::REPORT_HOOK );
		wp_unschedule_hook( self::LEGACY_HOOK );
	}

	/**
	 * Queues one notification (Fast_Index_Queue). The newest event of an address wins.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    URL_UPDATED or URL_DELETED.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function queue_submission( $url, $type, $post_id ) {
		Fast_Index_Queue::add( $url, $type, $post_id );
	}

	/**
	 * Handler of the single events of the 3.0.0 development builds: the event's notification and every other such
	 * event go into the queue, which sends them in batches.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    Notification type.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function run_submit( $url, $type, $post_id = 0 ) {
		Fast_Index_Queue::add( $url, $type, absint( $post_id ) );
		Fast_Index_Queue::adopt_legacy_events();
	}

	/**
	 * Hourly run: checks the notifications waiting for a key and the queue, then lets extensions do their hourly work.
	 *
	 * @return void
	 */
	public function run_hourly() {
		Fast_Index_Retry::maintain();
		Fast_Index_Queue::adopt_legacy_events();
		Fast_Index_Queue::resume();

		/**
		 * Fires once an hour, after the notifications that wait for a key were checked.
		 */
		do_action( 'fast_index_hourly' );
	}
}
