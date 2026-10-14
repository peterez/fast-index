<?php
/**
 * Premium: sends old posts that were never submitted.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends a small batch of old posts within the daily limit set by the user, every hour and from the
 * "Send Old Posts Now" screen.
 *
 * The batch size keeps the 2.x rule: a random number between 0 and a third of the daily limit per run, never
 * more than what is left of the limit for the last 24 hours.
 */
class Fast_Index_Premium_Backlog {

	/**
	 * Seconds one run may spend sending.
	 */
	const TIME_BUDGET = 20;

	/**
	 * Slug of the "Send Old Posts Now" page (kept from 3.0.0 of the plugin).
	 */
	const PAGE_SLUG = 'fast-index-run';

	/**
	 * Form action: send a batch of old posts now.
	 */
	const RUN_ACTION = 'fast_index_run_backlog';

	/**
	 * Prefix of the per-user transient holding the last manual run results.
	 */
	const RESULTS_PREFIX = 'fast_index_run_results_';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'fast_index_hourly', array( __CLASS__, 'run_hourly' ) );
		add_action( 'fast_index_admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_' . self::RUN_ACTION, array( __CLASS__, 'handle_run' ) );
		add_filter( 'fast_index_history_stats', array( __CLASS__, 'history_stats' ), 10, 2 );
	}

	/**
	 * Hourly batch.
	 *
	 * @return void
	 */
	public static function run_hourly() {
		self::run();
	}

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

		return self::send( self::ids( $settings, $size ) );
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
	 * IDs of published posts of the enabled types that were never sent successfully and are not in an excluded
	 * category, newest first.
	 *
	 * @param array $settings Clean settings.
	 * @param int   $limit    Maximum number of IDs.
	 * @return int[]
	 */
	public static function ids( array $settings, $limit ) {
		global $wpdb;

		$types = Fast_Index_Settings::enabled_post_types( $settings );
		$terms = self::excluded_term_taxonomy_ids( $settings );
		$limit = absint( $limit );

		if ( array() === $types || 0 === $limit ) {
			return array();
		}

		$values = array_merge( $types, array( Fast_Index_Log::POST_TYPE, Fast_Index_Log::STATUS_SENT ), $terms, array( $limit ) );

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
	 * Number of posts that ids() would return without a limit.
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

		$values = array_merge( $types, array( Fast_Index_Log::POST_TYPE, Fast_Index_Log::STATUS_SENT ), $terms );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Same query as ids(); the result is cached by Fast_Index_Log::stats().
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
	 * History counters: posts in an excluded category do not count as waiting.
	 *
	 * @param int[] $stats    Keys: sent, waiting, today.
	 * @param array $settings Clean settings.
	 * @return int[]
	 */
	public static function history_stats( $stats, $settings ) {
		$stats            = is_array( $stats ) ? $stats : array();
		$stats['waiting'] = self::count_waiting( is_array( $settings ) ? $settings : Fast_Index_Settings::get() );

		return $stats;
	}

	/**
	 * Adds the "Send Old Posts Now" page to the Fast Index menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		Fast_Index_Admin::add_page( 'run', __( 'Send Old Posts Now', 'fast-index' ), self::PAGE_SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Renders the "Send Old Posts Now" page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fast-index' ), '', array( 'response' => 403 ) );
		}

		$key     = self::RESULTS_PREFIX . get_current_user_id();
		$results = get_transient( $key );

		delete_transient( $key );

		$fast_index_results = is_array( $results ) ? $results : null;

		include __DIR__ . '/view-run.php';
	}

	/**
	 * Sends one batch of old posts now and shows the results (admin-post handler).
	 *
	 * @return void
	 */
	public static function handle_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			Fast_Index_Admin_Actions::forbidden();
		}

		check_admin_referer( self::RUN_ACTION, Fast_Index_Admin_Actions::NONCE_FIELD );

		set_transient( self::RESULTS_PREFIX . get_current_user_id(), self::run(), 5 * MINUTE_IN_SECONDS );

		Fast_Index_Admin_Actions::redirect( self::PAGE_SLUG );
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

	/**
	 * Term taxonomy IDs of the excluded categories. Holds 0 when none, so the IN list is never empty.
	 *
	 * @param array $settings Clean settings.
	 * @return int[]
	 */
	private static function excluded_term_taxonomy_ids( array $settings ) {
		$ids = array();

		foreach ( array_map( 'absint', array_keys( $settings['exclude_category'] ) ) as $term_id ) {
			$term = get_term( $term_id, 'category' );

			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_taxonomy_id;
			}
		}

		return array() === $ids ? array( 0 ) : $ids;
	}
}
