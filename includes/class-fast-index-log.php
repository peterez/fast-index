<?php
/**
 * Submission log stored in the fi_log post type.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes and reads the submission log.
 *
 * A log entry is a fi_log post whose parent is the submitted post. Published entries
 * mark a successful submission (2.x wrote only these); draft entries record failures
 * such as quota errors (HTTP 429) and notifications that could not be sent at all (no key,
 * every key resting, no OpenSSL; negative codes). Only published entries count as "sent".
 *
 * A draft entry that carries META_RETRY waits in the retry queue (Fast_Index_Retry).
 */
class Fast_Index_Log {

	/**
	 * Post type of log entries (name kept from 2.x).
	 */
	const POST_TYPE = 'fi_log';

	/**
	 * Post status of successful submissions.
	 */
	const STATUS_SENT = 'publish';

	/**
	 * Post status of failed submissions.
	 */
	const STATUS_FAILED = 'draft';

	/**
	 * Meta key of the HTTP status code.
	 */
	const META_CODE = '_fast_index_code';

	/**
	 * Meta key of the notification type.
	 */
	const META_TYPE = '_fast_index_type';

	/**
	 * Meta key marking an entry whose notification waits for a key: Unix time the wait began.
	 */
	const META_RETRY = '_fast_index_retry';

	/**
	 * Draft entries of one post looked at when a newer entry for the same address arrives.
	 */
	const SUPERSEDE_LOOKUP = 50;

	/**
	 * Entries per history page.
	 */
	const PER_PAGE = 20;

	/**
	 * Transient caching the history page counters.
	 */
	const STATS_TRANSIENT = 'fast_index_stats';

	/**
	 * Registers the log post type. Runs on init.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Fast Index History', 'fast-index' ),
					'singular_name' => __( 'Fast Index History Entry', 'fast-index' ),
				),
				'public'       => false,
				'rewrite'      => false,
				'query_var'    => false,
				'can_export'   => false,
				'show_in_rest' => false,
				'supports'     => array( 'title' ),
			)
		);
	}

	/**
	 * Adds a log entry. The newest event of an address wins: earlier entries of the same post and
	 * address leave the retry queue, and an entry for a notification that could not be sent replaces
	 * the earlier entry of that kind, so History keeps one such line per address.
	 *
	 * @param int    $post_id Submitted post, 0 when unknown.
	 * @param string $url     Submitted URL.
	 * @param int    $code    HTTP status (200 on success) or a Fast_Index_Submitter constant.
	 * @param string $type    Notification type.
	 * @return int Log entry ID, 0 on failure.
	 */
	public static function add( $post_id, $url, $code, $type ) {
		$post_id = absint( $post_id );
		$title   = $post_id ? (string) get_post_field( 'post_title', $post_id, 'raw' ) : '';
		$now_gmt = gmdate( 'Y-m-d H:i:s' );

		$log_id = wp_insert_post(
			array(
				'post_type'     => self::POST_TYPE,
				'post_status'   => 200 === (int) $code ? self::STATUS_SENT : self::STATUS_FAILED,
				'post_title'    => '' !== $title ? $title : $url,
				'post_content'  => esc_url_raw( $url ),
				'post_parent'   => $post_id,
				'post_author'   => 0,
				'post_date'     => get_date_from_gmt( $now_gmt ),
				'post_date_gmt' => $now_gmt,
				'meta_input'    => array(
					self::META_CODE => (int) $code,
					self::META_TYPE => (string) $type,
				),
			),
			true
		);

		delete_transient( self::STATS_TRANSIENT );

		if ( is_wp_error( $log_id ) ) {
			return 0;
		}

		self::supersede( $post_id, esc_url_raw( $url ), (int) $log_id, Fast_Index_Submitter::is_not_sent( $code ) );

		return (int) $log_id;
	}

	/**
	 * Marks an entry as waiting for a key.
	 *
	 * @param int $log_id Log entry ID.
	 * @param int $since  Unix time the notification started to wait.
	 * @return void
	 */
	public static function hold( $log_id, $since ) {
		update_post_meta( (int) $log_id, self::META_RETRY, (int) $since );
	}

	/**
	 * Takes an entry out of the retry queue. The entry stays in History.
	 *
	 * @param int $log_id Log entry ID.
	 * @return void
	 */
	public static function release( $log_id ) {
		delete_post_meta( (int) $log_id, self::META_RETRY );
	}

	/**
	 * Entries waiting for a key, oldest first.
	 *
	 * @param int  $limit  Maximum number of entries.
	 * @param int  $offset Entries to skip, counted from the newest one when $newest is true.
	 * @param bool $newest Newest first instead of oldest first.
	 * @return array[] Keys: id, post_id, url, type, since.
	 */
	public static function held( $limit, $offset = 0, $newest = false ) {
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_FAILED,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- At most Fast_Index_Retry::MAX entries carry the key; runs in cron or after a key upload.
				'meta_key'    => self::META_RETRY,
				'numberposts' => max( 1, absint( $limit ) ),
				'offset'      => absint( $offset ),
				'orderby'     => 'ID',
				'order'       => $newest ? 'DESC' : 'ASC',
			)
		);
		$rows  = array();

		foreach ( $posts as $post ) {
			$type   = (string) get_post_meta( $post->ID, self::META_TYPE, true );
			$rows[] = array(
				'id'      => (int) $post->ID,
				'post_id' => (int) $post->post_parent,
				'url'     => (string) $post->post_content,
				'type'    => '' === $type ? Fast_Index_Google_Client::TYPE_UPDATED : $type,
				'since'   => (int) get_post_meta( $post->ID, self::META_RETRY, true ),
			);
		}

		return $rows;
	}

	/**
	 * Number of entries waiting for a key.
	 *
	 * @return int
	 */
	public static function count_held() {
		$query = new WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => self::STATUS_FAILED,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- At most Fast_Index_Retry::MAX entries carry the key.
				'meta_key'               => self::META_RETRY,
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Applies "the newest event of an address wins" to the older draft entries of a post.
	 *
	 * @param int    $post_id  Post ID; nothing happens for 0.
	 * @param string $url      Address as stored.
	 * @param int    $new_id   The entry just added.
	 * @param bool   $not_sent Whether the new entry is a notification that could not be sent.
	 * @return void
	 */
	private static function supersede( $post_id, $url, $new_id, $not_sent ) {
		$post_id = absint( $post_id );

		if ( 0 === $post_id ) {
			return;
		}

		$older = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_FAILED,
				'post_parent' => $post_id,
				'numberposts' => self::SUPERSEDE_LOOKUP,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);

		foreach ( $older as $entry ) {
			if ( $entry->ID === $new_id || $entry->ID > $new_id || $entry->post_content !== $url ) {
				continue;
			}

			if ( $not_sent && Fast_Index_Submitter::is_not_sent( get_post_meta( $entry->ID, self::META_CODE, true ) ) ) {
				wp_delete_post( $entry->ID, true );
				continue;
			}

			self::release( $entry->ID );
		}
	}

	/**
	 * Counters for the history page, cached for ten minutes.
	 *
	 * @param array $settings Clean settings.
	 * @return int[] Keys: sent, waiting, today.
	 */
	public static function stats( array $settings ) {
		$cached = get_transient( self::STATS_TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['sent'], $cached['waiting'], $cached['today'] ) ) {
			return $cached;
		}

		$stats = array(
			'sent'    => self::count_sent(),
			'waiting' => self::count_waiting( $settings ),
			'today'   => self::count_sent_since( time() - DAY_IN_SECONDS ),
		);

		/**
		 * Filters the History counters before they are cached.
		 *
		 * @param int[] $stats    Keys: sent, waiting, today.
		 * @param array $settings Clean settings.
		 */
		$filtered = apply_filters( 'fast_index_history_stats', $stats, $settings );
		$stats    = is_array( $filtered ) ? array_map( 'intval', array_merge( $stats, array_intersect_key( $filtered, $stats ) ) ) : $stats;

		set_transient( self::STATS_TRANSIENT, $stats, 10 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Forgets the cached counters (settings changed).
	 *
	 * @return void
	 */
	public static function flush_stats() {
		delete_transient( self::STATS_TRANSIENT );
	}

	/**
	 * Number of successful submissions.
	 *
	 * @return int
	 */
	public static function count_sent() {
		$counts = wp_count_posts( self::POST_TYPE );

		return isset( $counts->{self::STATUS_SENT} ) ? (int) $counts->{self::STATUS_SENT} : 0;
	}

	/**
	 * Number of successful submissions since a moment. Compared in UTC.
	 *
	 * @param int $since Unix timestamp.
	 * @return int
	 */
	public static function count_sent_since( $since ) {
		$query = new WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => self::STATUS_SENT,
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'column'    => 'post_date_gmt',
						'after'     => gmdate( 'Y-m-d H:i:s', (int) $since ),
						'inclusive' => true,
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Number of published posts of the enabled types that were never sent successfully.
	 *
	 * @param array $settings Clean settings.
	 * @return int
	 */
	public static function count_waiting( array $settings ) {
		global $wpdb;

		$types = Fast_Index_Settings::enabled_post_types( $settings );

		if ( array() === $types ) {
			return 0;
		}

		$values = array_merge( $types, array( self::POST_TYPE, self::STATUS_SENT ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- NOT EXISTS over the log has no WP_Query equivalent; the result is cached by stats(), every value is a placeholder.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} AS p
				WHERE p.post_status = 'publish'
				AND p.post_type IN ( " . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . " )
				AND NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} AS l WHERE l.post_parent = p.ID AND l.post_type = %s AND l.post_status = %s )",
				$values
			)
		);

		return (int) $count;
	}

	/**
	 * One page of log entries, newest first.
	 *
	 * @param int $page Zero-based page number.
	 * @return array Keys: entries (array[]), has_next (bool).
	 */
	public static function page( $page ) {
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array( self::STATUS_SENT, self::STATUS_FAILED ),
				'numberposts' => self::PER_PAGE + 1,
				'offset'      => absint( $page ) * self::PER_PAGE,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);

		$entries = array();

		foreach ( array_slice( $posts, 0, self::PER_PAGE ) as $post ) {
			$entries[] = self::entry( $post );
		}

		return array(
			'entries'  => $entries,
			'has_next' => count( $posts ) > self::PER_PAGE,
		);
	}

	/**
	 * The newest log entry, successful or not.
	 *
	 * @return array|null Display fields (see page()), null when nothing was logged yet.
	 */
	public static function latest() {
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array( self::STATUS_SENT, self::STATUS_FAILED ),
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);

		return array() === $posts ? null : self::entry( $posts[0] );
	}

	/**
	 * Display fields of one log entry.
	 *
	 * @param WP_Post $post Log post.
	 * @return array
	 */
	private static function entry( WP_Post $post ) {
		$code = get_post_meta( $post->ID, self::META_CODE, true );
		$type = get_post_meta( $post->ID, self::META_TYPE, true );

		return array(
			'title'     => $post->post_title,
			'post_id'   => (int) $post->post_parent,
			'url'       => $post->post_content,
			'timestamp' => (int) strtotime( $post->post_date_gmt . ' UTC' ),
			// Entries written by 2.x have no meta: they were all successful URL_UPDATED requests.
			'code'      => '' === $code ? 200 : (int) $code,
			'type'      => '' === $type ? Fast_Index_Google_Client::TYPE_UPDATED : (string) $type,
			'retry'     => '' !== get_post_meta( $post->ID, self::META_RETRY, true ),
		);
	}
}
