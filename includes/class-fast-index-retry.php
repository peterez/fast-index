<?php
/**
 * Notifications that wait for a key.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Delivers queued notifications and keeps the ones that no key can take yet.
 *
 * Why a notification was not sent is always written to History. What happens next depends on
 * the reason:
 *
 * - Every key rests after 401, 403 or 429 (before the attempt, or because of it): the History
 *   entry is marked with Fast_Index_Log::META_RETRY and sent again when the first rest ends, or at
 *   once when a key is uploaded. The rest has a known end, so waiting is worth it.
 * - No key is stored, or OpenSSL is missing: only the History entry. Neither has a known end, and
 *   a first key upload would otherwise send a burst of old notifications at a project's daily quota.
 *   Posts that never reached Google stay in "Total Waiting" for the old-post batches.
 *
 * The queue lives in the log entries themselves (site's posts table): one entry per address, the
 * newest event of an address wins, at most MAX entries, none older than MAX_AGE.
 */
class Fast_Index_Retry {

	/**
	 * Single event that sends the waiting notifications.
	 */
	const HOOK = 'fast_index_retry';

	/**
	 * Most notifications kept waiting, per site: Google's default daily quota of one project.
	 */
	const MAX = 200;

	/**
	 * Longest wait, in seconds. A key that still fails after three days needs the user, not a retry.
	 */
	const MAX_AGE = 259200;

	/**
	 * Seconds one run may spend sending.
	 */
	const TIME_BUDGET = 20;

	/**
	 * Delay after the end of a rest before the run, in seconds.
	 */
	const MARGIN = 60;

	/**
	 * Name of the lock that keeps two runs from sending the same notifications.
	 */
	const LOCK = 'retry';

	/**
	 * Sends one notification queued by the post watcher and records what happened.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $type    URL_UPDATED or URL_DELETED.
	 * @param int    $post_id Post ID.
	 * @return array Result of Fast_Index_Submitter::send(), with the History entry in log_id.
	 */
	public static function deliver( $url, $type, $post_id ) {
		$result = ( new Fast_Index_Submitter() )->send( $url, $type, $post_id );

		if ( 0 === $result['log_id'] ) {
			// Nothing was sent: History says why.
			$result['log_id'] = Fast_Index_Log::add( $post_id, $url, $result['code'], $type );
		}

		if ( $result['retry'] && $result['log_id'] > 0 ) {
			self::hold( $result['log_id'], time() );
		} elseif ( 200 === $result['code'] ) {
			// A key works again: send what is waiting now instead of at the planned time.
			self::resume();
		}

		return $result;
	}

	/**
	 * Puts a History entry into the queue, drops the oldest entries above MAX and plans the run.
	 *
	 * @param int $log_id History entry ID.
	 * @param int $since  Unix time the notification started to wait.
	 * @return void
	 */
	public static function hold( $log_id, $since ) {
		Fast_Index_Log::hold( $log_id, $since );

		foreach ( Fast_Index_Log::held( self::MAX, self::MAX, true ) as $row ) {
			Fast_Index_Log::release( $row['id'] );
		}

		self::schedule( Fast_Index_Accounts::next_ready_time() + self::MARGIN );
	}

	/**
	 * Plans a run now when notifications are waiting. Called after a key upload and on activation.
	 *
	 * @return void
	 */
	public static function resume() {
		if ( Fast_Index_Log::count_held() > 0 ) {
			self::schedule( time() );
		}
	}

	/**
	 * Hourly check: plans a run when notifications wait and none is planned (a lost event, a rest that
	 * ended early, OpenSSL that came back), so that old entries also leave the queue on time.
	 *
	 * @return void
	 */
	public static function maintain() {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			self::resume();
		}
	}

	/**
	 * Plans the run for a moment, unless an earlier run is already planned.
	 *
	 * @param int $timestamp Unix time.
	 * @return void
	 */
	public static function schedule( $timestamp ) {
		$timestamp = max( time(), (int) $timestamp );
		$planned   = wp_next_scheduled( self::HOOK );

		if ( false !== $planned && $planned <= $timestamp ) {
			return;
		}

		if ( false !== $planned ) {
			wp_unschedule_event( $planned, self::HOOK );
		}

		wp_schedule_single_event( $timestamp, self::HOOK );
	}

	/**
	 * Cron handler: sends the waiting notifications, oldest first.
	 *
	 * @return void
	 */
	public static function run() {
		// One attempt can wait for two 15-second requests per key after the budget check, so the lock outlives the budget.
		$token = Fast_Index_Lock::acquire( self::LOCK, 5 * MINUTE_IN_SECONDS );

		if ( false === $token ) {
			return;
		}

		$more = self::process();

		Fast_Index_Lock::release( self::LOCK, $token );

		if ( $more ) {
			self::schedule( time() + MINUTE_IN_SECONDS );
		} elseif ( Fast_Index_Log::count_held() > 0 ) {
			self::plan_next();
		} else {
			// Nothing waits: a run planned for later has nothing to do.
			wp_unschedule_hook( self::HOOK );
		}
	}

	/**
	 * Sends waiting notifications until the time budget is spent or no key can take one.
	 *
	 * @return bool True when the time budget ran out with notifications left.
	 */
	private static function process() {
		$active    = Fast_Index_Settings::is_active( Fast_Index_Settings::get() );
		$cutoff    = time() - self::MAX_AGE;
		$deadline  = time() + self::TIME_BUDGET;
		$submitter = new Fast_Index_Submitter();
		$blocked   = ! $active;

		foreach ( Fast_Index_Log::held( self::MAX ) as $row ) {
			if ( $row['since'] < $cutoff || ! self::still_needed( $row ) ) {
				Fast_Index_Log::release( $row['id'] );
				continue;
			}

			if ( $blocked ) {
				// Passive, or no key can take a notification: only old entries leave the queue.
				continue;
			}

			if ( time() >= $deadline ) {
				return true;
			}

			$result = $submitter->send( $row['url'], $row['type'], $row['post_id'] );

			if ( 0 === $result['log_id'] ) {
				$blocked = true;
				continue;
			}

			Fast_Index_Log::release( $row['id'] );

			if ( $result['retry'] ) {
				// Every key rests again: the new attempt keeps the place and the age of the old one.
				Fast_Index_Log::hold( $result['log_id'], $row['since'] );
				$blocked = true;
			} elseif ( Fast_Index_Submitter::is_outage( $result['code'] ) ) {
				// Google did not answer or had a server error: the rest would wait the same way. Try again later.
				return true;
			}
		}

		return false;
	}

	/**
	 * Plans the next run at the end of the first rest. Without a key or without OpenSSL nothing is
	 * planned: a key upload resumes the queue, and the hourly check retires old entries.
	 *
	 * @return void
	 */
	private static function plan_next() {
		$ready = Fast_Index_Accounts::next_ready_time();

		if ( $ready > 0 && Fast_Index_Google_Client::is_supported() && Fast_Index_Settings::is_active( Fast_Index_Settings::get() ) ) {
			self::schedule( max( $ready, time() ) + self::MARGIN );
		}
	}

	/**
	 * Whether a waiting notification still describes the post. An update is not sent for a post
	 * that is no longer published; a removal is always sent.
	 *
	 * @param array $row Row from Fast_Index_Log::held().
	 * @return bool
	 */
	private static function still_needed( array $row ) {
		return Fast_Index_Post_Watcher::is_http_url( $row['url'] ) && Fast_Index_Post_Watcher::still_public( $row['type'], $row['post_id'] );
	}
}
