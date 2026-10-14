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
 * such as quota errors (HTTP 429). Only published entries count as "sent".
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
					'name'          => __( 'Fast Index Logs', 'fast-index' ),
					'singular_name' => __( 'Fast Index Log', 'fast-index' ),
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
	 * Adds a log entry.
	 *
	 * @param int    $post_id Submitted post, 0 when unknown.
	 * @param string $url     Submitted URL.
	 * @param int    $code    HTTP status (200 on success).
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

		return is_wp_error( $log_id ) ? 0 : (int) $log_id;
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
	 * IDs of published posts that were never sent successfully, newest first.
	 *
	 * @param array $settings Clean settings.
	 * @param int   $limit    Maximum number of IDs.
	 * @return int[]
	 */
	public static function backlog_ids( array $settings, $limit ) {
		global $wpdb;

		$types = Fast_Index_Settings::enabled_post_types( $settings );
		$terms = self::excluded_term_taxonomy_ids( $settings );
		$limit = absint( $limit );

		if ( array() === $types || 0 === $limit ) {
			return array();
		}

		$values = array_merge( $types, array( self::POST_TYPE, self::STATUS_SENT ), $terms, array( $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- NOT EXISTS over the log has no WP_Query equivalent; runs in cron or after an admin action, every value is a placeholder.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} AS p
				WHERE p.post_status = 'publish'
				AND p.post_type IN ( " . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . " )
				AND NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} AS l WHERE l.post_parent = p.ID AND l.post_type = %s AND l.post_status = %s )
				AND NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} AS tr WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN ( " . implode( ', ', array_fill( 0, count( $terms ), '%d' ) ) . ' ) )
				ORDER BY p.ID DESC
				LIMIT %d',
				$values
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Number of published posts that were never sent successfully.
	 *
	 * @param array $settings Clean settings.
	 * @return int
	 */
	public static function count_waiting( array $settings ) {
		global $wpdb;

		$types = Fast_Index_Settings::enabled_post_types( $settings );
		$terms = self::excluded_term_taxonomy_ids( $settings );

		if ( array() === $types ) {
			return 0;
		}

		$values = array_merge( $types, array( self::POST_TYPE, self::STATUS_SENT ), $terms );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Same query as backlog_ids(); the result is cached by stats().
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} AS p
				WHERE p.post_status = 'publish'
				AND p.post_type IN ( " . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . " )
				AND NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} AS l WHERE l.post_parent = p.ID AND l.post_type = %s AND l.post_status = %s )
				AND NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} AS tr WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN ( " . implode( ', ', array_fill( 0, count( $terms ), '%d' ) ) . ' ) )',
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
		);
	}

	/**
	 * Term taxonomy IDs of the excluded categories. Holds 0 when none, so the IN list is never empty.
	 *
	 * @param array $settings Clean settings.
	 * @return int[]
	 */
	private static function excluded_term_taxonomy_ids( array $settings ) {
		$ids = array();

		foreach ( Fast_Index_Settings::excluded_category_ids( $settings ) as $term_id ) {
			$term = get_term( $term_id, 'category' );

			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_taxonomy_id;
			}
		}

		return array() === $ids ? array( 0 ) : $ids;
	}
}
