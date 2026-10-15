<?php
/**
 * Queue of the notifications that wait for the next scheduled-task run.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds the addresses to send and sends them in batches from one scheduled event.
 *
 * - Each address is one row of the options table (name PREFIX + md5 of the address, autoload off), written with a
 *   single INSERT ... ON DUPLICATE KEY UPDATE: adding never reads or rewrites the whole queue, and two requests
 *   that add at the same time never lose each other's rows. The newest event of an address wins (its type and post
 *   replace the older ones) and keeps its place in the queue. The rows are never read through get_option().
 * - One single event (HOOK) sends the queue: at most BATCH addresses per run within TIME_BUDGET seconds. Google gets
 *   them one by one (Fast_Index_Notifier::deliver_many()), IndexNow gets the addresses of the run in one request.
 *   While addresses are left, the next run is planned right away; after a Google outage (no answer or 5xx) in a
 *   run, OUTAGE_DELAY later.
 * - A row is claimed by deleting it (DELETE with its value): two runs never send the same row.
 *
 * So a 5,000-post import adds 5,000 small rows and at most one event: the autoloaded cron option keeps its size.
 * Before this queue (3.0.0 development builds), every address was its own single event fast_index_submit_url; such
 * events are moved into the queue (adopt_legacy_events()).
 */
class Fast_Index_Queue {

	/**
	 * Option name prefix of queue rows.
	 */
	const PREFIX = 'fast_index_queue_';

	/**
	 * Single event that sends the queue.
	 */
	const HOOK = 'fast_index_process_queue';

	/**
	 * Most addresses taken by one run.
	 */
	const BATCH = 100;

	/**
	 * Seconds the Google requests of one run may take.
	 */
	const TIME_BUDGET = 20;

	/**
	 * Rows per statement of add_many().
	 */
	const ADD_CHUNK = 500;

	/**
	 * Seconds before the next run after Google did not answer or answered 5xx.
	 */
	const OUTAGE_DELAY = 300;

	/**
	 * Adds a notification to the queue and plans the run.
	 *
	 * @param string $url      Absolute URL.
	 * @param string $type     URL_UPDATED or URL_DELETED.
	 * @param int    $post_id  Post ID, 0 when unknown.
	 * @param bool   $schedule Whether to plan the run now.
	 * @return bool Whether the row was written.
	 */
	public static function add( $url, $type, $post_id, $schedule = true ) {
		global $wpdb;

		$url  = (string) $url;
		$type = (string) $type;

		if ( ! Fast_Index_Post_Watcher::is_http_url( $url ) || ! in_array( $type, Fast_Index_Google_Client::types(), true ) ) {
			return false;
		}

		$value = (string) wp_json_encode(
			array(
				'url'     => $url,
				'type'    => $type,
				'post_id' => absint( $post_id ),
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic upsert of one queue row; update_option() would read and write the row through the options cache.
		$written = false !== $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' ) ON DUPLICATE KEY UPDATE option_value = %s", self::PREFIX . md5( $url ), $value, $value ) );

		if ( $written && $schedule ) {
			self::schedule( time() );
		}

		return $written;
	}

	/**
	 * Adds several notifications with one statement per ADD_CHUNK rows (moving many events at once).
	 *
	 * @param array[] $items Each: url, type, post_id.
	 * @return int Number of notifications written.
	 */
	public static function add_many( array $items ) {
		global $wpdb;

		$rows = array();

		foreach ( $items as $item ) {
			$url  = isset( $item['url'] ) ? (string) $item['url'] : '';
			$type = isset( $item['type'] ) ? (string) $item['type'] : '';

			if ( Fast_Index_Post_Watcher::is_http_url( $url ) && in_array( $type, Fast_Index_Google_Client::types(), true ) ) {
				// Keyed by name: the newest event of an address wins within the list as well.
				$rows[ self::PREFIX . md5( $url ) ] = (string) wp_json_encode(
					array(
						'url'     => $url,
						'type'    => $type,
						'post_id' => isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0,
					)
				);
			}
		}

		foreach ( array_chunk( $rows, self::ADD_CHUNK, true ) as $chunk ) {
			$values = array();

			foreach ( $chunk as $name => $value ) {
				$values[] = $name;
				$values[] = $value;
			}

			$places = implode( ', ', array_fill( 0, count( $chunk ), "( %s, %s, 'no' )" ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One upsert per chunk, as core add_option() writes it; $places holds only placeholders.
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES {$places} ON DUPLICATE KEY UPDATE option_value = VALUES( option_value )", $values ) );
		}

		return count( $rows );
	}

	/**
	 * Queued notifications, oldest first.
	 *
	 * @param int $limit Most rows.
	 * @return array[] Keys: url, type, post_id.
	 */
	public static function items( $limit = self::BATCH ) {
		$items = array();

		foreach ( self::rows( $limit ) as $row ) {
			$item = self::decode( $row['value'] );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Number of queued notifications.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue rows are never cached; the prefix uses the option_name index.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::PREFIX ) . '%' ) );
	}

	/**
	 * Takes the queued notification of one address out of the queue.
	 *
	 * @param string $url Absolute URL.
	 * @return array|null The notification (url, type, post_id), null when the address was not queued.
	 */
	public static function take( $url ) {
		global $wpdb;

		$name = self::PREFIX . md5( (string) $url );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue rows are never cached.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

		if ( null === $value || ! self::claim( $name, (string) $value ) ) {
			return null;
		}

		return self::decode( (string) $value );
	}

	/**
	 * Plans a run at a moment, unless a run is already planned.
	 *
	 * @param int $timestamp Unix time.
	 * @return void
	 */
	public static function schedule( $timestamp ) {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( max( time(), (int) $timestamp ), self::HOOK );
		}
	}

	/**
	 * Plans a run when notifications wait and none is planned (activation, upgrade, hourly check).
	 *
	 * @return void
	 */
	public static function resume() {
		if ( false === wp_next_scheduled( self::HOOK ) && self::count() > 0 ) {
			self::schedule( time() );
		}
	}

	/**
	 * Cron handler: sends one batch and plans the next run while addresses are left.
	 *
	 * @return array Keys: taken (rows taken from the queue), result (of Fast_Index_Notifier::deliver_many(), null
	 *               when nothing was sent).
	 */
	public static function run() {
		$items = array();

		foreach ( self::rows( self::BATCH ) as $row ) {
			if ( ! self::claim( $row['name'], $row['value'] ) ) {
				continue; // Another run took it, or a newer event of the address replaced it.
			}

			$item = self::decode( $row['value'] );

			if ( null !== $item && Fast_Index_Post_Watcher::still_public( $item['type'], $item['post_id'] ) ) {
				$items[] = $item;
			}
		}

		$result = array() === $items ? null : Fast_Index_Notifier::deliver_many( $items, self::TIME_BUDGET );

		if ( null !== $result && ! empty( $result['outage'] ) ) {
			// The addresses put back during this run planned a run for now; Google gets a break first.
			wp_unschedule_hook( self::HOOK );
			self::schedule( time() + self::OUTAGE_DELAY );
		} else {
			self::resume();
		}

		return array(
			'taken'  => count( $items ),
			'result' => $result,
		);
	}

	/**
	 * Moves the single events of the 3.0.0 development builds (one event fast_index_submit_url per address) into the
	 * queue and removes them from the cron option.
	 *
	 * @return int Number of events moved.
	 */
	public static function adopt_legacy_events() {
		$crons = _get_cron_array();
		$items = array();
		$found = false;

		if ( ! is_array( $crons ) ) {
			return 0;
		}

		foreach ( $crons as $hooks ) {
			if ( ! is_array( $hooks ) || empty( $hooks[ Fast_Index_Cron::SUBMIT_HOOK ] ) || ! is_array( $hooks[ Fast_Index_Cron::SUBMIT_HOOK ] ) ) {
				continue;
			}

			foreach ( $hooks[ Fast_Index_Cron::SUBMIT_HOOK ] as $event ) {
				$found = true;
				$args  = isset( $event['args'] ) && is_array( $event['args'] ) ? array_values( $event['args'] ) : array();

				if ( count( $args ) >= 2 ) {
					$items[] = array(
						'url'     => (string) $args[0],
						'type'    => (string) $args[1],
						'post_id' => isset( $args[2] ) ? absint( $args[2] ) : 0,
					);
				}
			}
		}

		if ( ! $found ) {
			return 0;
		}

		$moved = self::add_many( $items );
		wp_unschedule_hook( Fast_Index_Cron::SUBMIT_HOOK );

		if ( $moved > 0 ) {
			self::schedule( time() );
		}

		return $moved;
	}

	/**
	 * Oldest queue rows.
	 *
	 * @param int $limit Most rows.
	 * @return array[] Keys: name, value.
	 */
	private static function rows( $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue rows are never cached; the prefix uses the option_name index.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d", $wpdb->esc_like( self::PREFIX ) . '%', max( 1, absint( $limit ) ) ), ARRAY_A );
		$list = array();

		foreach ( (array) $rows as $row ) {
			$list[] = array(
				'name'  => (string) $row['option_name'],
				'value' => (string) $row['option_value'],
			);
		}

		return $list;
	}

	/**
	 * Takes a row out of the queue if it still has the value that was read.
	 *
	 * @param string $name  Option name.
	 * @param string $value Value that was read.
	 * @return bool Whether this call removed the row.
	 */
	private static function claim( $name, $value ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The DELETE is the claim: only one run removes the row.
		return 1 === (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $value ) );
	}

	/**
	 * Reads a row value.
	 *
	 * @param string $value JSON.
	 * @return array|null Keys: url, type, post_id; null when the value is not a notification.
	 */
	private static function decode( $value ) {
		$data = json_decode( (string) $value, true );

		if ( ! is_array( $data ) || ! isset( $data['url'], $data['type'] ) || ! Fast_Index_Post_Watcher::is_http_url( $data['url'] ) || ! in_array( $data['type'], Fast_Index_Google_Client::types(), true ) ) {
			return null;
		}

		return array(
			'url'     => (string) $data['url'],
			'type'    => (string) $data['type'],
			'post_id' => isset( $data['post_id'] ) ? absint( $data['post_id'] ) : 0,
		);
	}
}
