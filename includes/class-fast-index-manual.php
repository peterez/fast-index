<?php
/**
 * Sending by hand from the post lists.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * "Notify search engines": a row action and a bulk action on the list screens of the post types Fast Index sends.
 *
 * - Only published posts are sent, and only by a user who can edit each of them (edit_post, checked per post).
 *   The row action carries its own nonce; the bulk action is checked by core (bulk-posts) before it reaches
 *   handle_bulk().
 * - At most MAX posts are sent at a time, in the order of the list. Google gets them one by one within
 *   TIME_BUDGET seconds; what is left is queued for the next scheduled-task run. IndexNow, when it is on, gets
 *   the addresses in one request.
 * - The result is shown once, on the same list screen, from a per-user transient.
 * - While Status is Passive the actions are not offered.
 */
class Fast_Index_Manual {

	/**
	 * Most posts sent at a time.
	 */
	const MAX = 100;

	/**
	 * Seconds the Google requests of one action may take.
	 */
	const TIME_BUDGET = 20;

	/**
	 * Bulk action name.
	 */
	const BULK_ACTION = 'fast_index_notify';

	/**
	 * Action of admin-post.php for the row action.
	 */
	const ROW_ACTION = 'fast_index_notify_post';

	/**
	 * Query argument that asks the list screen to show the result.
	 */
	const RESULT_ARG = 'fast_index_notified';

	/**
	 * Prefix of the per-user transient holding the result.
	 */
	const RESULT_PREFIX = 'fast_index_manual_';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( __CLASS__, 'register_bulk_actions' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_post_' . self::ROW_ACTION, array( __CLASS__, 'handle_row' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_result' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ) );
	}

	/**
	 * Post types whose list screens get the actions: the checked ones, while Status is Active.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$settings = Fast_Index_Settings::get();

		return Fast_Index_Settings::is_active( $settings ) ? Fast_Index_Settings::enabled_post_types( $settings ) : array();
	}

	/**
	 * Adds the bulk action to the list screens of the post types Fast Index sends.
	 *
	 * @return void
	 */
	public static function register_bulk_actions() {
		foreach ( self::post_types() as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, array( __CLASS__, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
	}

	/**
	 * Adds "Notify search engines" to the bulk actions.
	 *
	 * @param string[] $actions Bulk actions by name.
	 * @return string[]
	 */
	public static function bulk_actions( $actions ) {
		$actions                      = is_array( $actions ) ? $actions : array();
		$actions[ self::BULK_ACTION ] = self::label();

		return $actions;
	}

	/**
	 * Label of both actions.
	 *
	 * @return string
	 */
	public static function label() {
		return __( 'Notify search engines', 'fast-index' );
	}

	/**
	 * Adds "Notify search engines" to the row actions of a published post the user can edit.
	 *
	 * @param string[] $actions Row actions.
	 * @param WP_Post  $post    Post of the row.
	 * @return string[]
	 */
	public static function row_action( $actions, $post ) {
		$actions = is_array( $actions ) ? $actions : array();

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::post_types(), true ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ROW_ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::ROW_ACTION . '_' . $post->ID
		);

		$actions['fast_index_notify'] = '<a href="' . esc_url( $url ) . '">' . esc_html( self::label() ) . '</a>';

		return $actions;
	}

	/**
	 * Row action handler (admin-post.php): checks the capability for the post, then the nonce, sends, and goes back
	 * to the list.
	 *
	 * @return void
	 */
	public static function handle_row() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is checked below, after the capability; the ID is only cast here.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			Fast_Index_Admin_Actions::forbidden();
		}

		check_admin_referer( self::ROW_ACTION . '_' . $post_id );

		self::send( array( $post_id ) );

		$referer = wp_get_referer();
		$back    = $referer ? $referer : admin_url( 'edit.php?post_type=' . get_post_type( $post_id ) );

		wp_safe_redirect( add_query_arg( self::RESULT_ARG, '1', $back ) );
		exit;
	}

	/**
	 * Bulk action handler. Core checked the bulk-posts nonce before this filter.
	 *
	 * @param string $redirect_to Address of the list screen.
	 * @param string $action      Chosen bulk action.
	 * @param int[]  $post_ids    Selected posts.
	 * @return string
	 */
	public static function handle_bulk( $redirect_to, $action, $post_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $redirect_to;
		}

		self::send( is_array( $post_ids ) ? $post_ids : array() );

		return add_query_arg( self::RESULT_ARG, '1', $redirect_to );
	}

	/**
	 * Sends the posts the current user may send and stores the result for the list screen.
	 *
	 * @param int[] $post_ids Selected posts, in list order.
	 * @return array The stored result (see the keys below).
	 */
	public static function send( array $post_ids ) {
		$ids      = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
		$types    = self::post_types();
		$items    = array();
		$skipped  = 0;
		$selected = count( $ids );

		foreach ( array_slice( $ids, 0, self::MAX ) as $post_id ) {
			$post = get_post( $post_id );
			$url  = $post instanceof WP_Post ? get_permalink( $post ) : '';

			if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! in_array( $post->post_type, $types, true ) || ! current_user_can( 'edit_post', $post_id ) || ! Fast_Index_Post_Watcher::is_http_url( $url ) ) {
				++$skipped;
				continue;
			}

			$items[] = array(
				'url'     => $url,
				'type'    => Fast_Index_Google_Client::TYPE_UPDATED,
				'post_id' => $post_id,
			);
		}

		$sent   = array() === $items ? null : Fast_Index_Notifier::deliver_many( $items, self::TIME_BUDGET );
		$result = array(
			'selected'          => $selected,
			'over_limit'        => max( 0, $selected - self::MAX ),
			'skipped'           => $skipped,
			'google_accepted'   => null === $sent ? 0 : $sent['google']['accepted'],
			'google_failed'     => null === $sent ? 0 : $sent['google']['failed'],
			'queued'            => null === $sent ? 0 : $sent['queued'],
			'indexnow_sent'     => null === $sent || null === $sent['indexnow'] ? 0 : $sent['indexnow']['sent'],
			'indexnow_accepted' => null !== $sent && null !== $sent['indexnow'] && $sent['indexnow']['accepted'],
		);

		set_transient( self::RESULT_PREFIX . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Lines of the result notice.
	 *
	 * @param array $result Result of send().
	 * @return string[] Plain text.
	 */
	public static function result_lines( array $result ) {
		$lines = array();

		if ( $result['google_accepted'] > 0 ) {
			/* translators: %s: number of addresses. */
			$lines[] = sprintf( _n( 'Google accepted %s address.', 'Google accepted %s addresses.', $result['google_accepted'], 'fast-index' ), number_format_i18n( $result['google_accepted'] ) );
		}

		if ( $result['google_failed'] > 0 ) {
			/* translators: %s: number of addresses. */
			$lines[] = sprintf( _n( '%s address was not accepted by Google or could not be sent. History shows the reason.', '%s addresses were not accepted by Google or could not be sent. History shows the reason.', $result['google_failed'], 'fast-index' ), number_format_i18n( $result['google_failed'] ) );
		}

		if ( $result['indexnow_sent'] > 0 && $result['indexnow_accepted'] ) {
			/* translators: %s: number of addresses. */
			$lines[] = sprintf( _n( 'IndexNow accepted %s address.', 'IndexNow accepted %s addresses.', $result['indexnow_sent'], 'fast-index' ), number_format_i18n( $result['indexnow_sent'] ) );
		} elseif ( $result['indexnow_sent'] > 0 ) {
			/* translators: %s: number of addresses. */
			$lines[] = sprintf( _n( 'IndexNow did not accept %s address. History shows the reason.', 'IndexNow did not accept %s addresses. History shows the reason.', $result['indexnow_sent'], 'fast-index' ), number_format_i18n( $result['indexnow_sent'] ) );
		}

		if ( $result['queued'] > 0 ) {
			/* translators: %s: number of addresses. */
			$lines[] = sprintf( _n( '%s address is queued and is sent in the background.', '%s addresses are queued and are sent in the background.', $result['queued'], 'fast-index' ), number_format_i18n( $result['queued'] ) );
		}

		if ( $result['skipped'] > 0 ) {
			/* translators: %s: number of selected items. */
			$lines[] = sprintf( _n( '%s selected item was not sent: it is not published, its post type is not checked in the settings, or you cannot edit it.', '%s selected items were not sent: they are not published, their post type is not checked in the settings, or you cannot edit them.', $result['skipped'], 'fast-index' ), number_format_i18n( $result['skipped'] ) );
		}

		if ( $result['over_limit'] > 0 ) {
			/* translators: 1: most items sent at a time, 2: number of selected items. */
			$lines[] = sprintf( __( 'Up to %1$s items are sent at a time: the first %1$s of the %2$s selected items were handled, the others were not sent.', 'fast-index' ), number_format_i18n( self::MAX ), number_format_i18n( $result['selected'] ) );
		}

		return $lines;
	}

	/**
	 * Prints the result once on the list screen it came back to.
	 *
	 * @return void
	 */
	public static function render_result() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to read this user's own stored result.
		if ( ! $screen instanceof WP_Screen || 'edit' !== $screen->base || ! isset( $_GET[ self::RESULT_ARG ] ) ) {
			return;
		}

		$key    = self::RESULT_PREFIX . get_current_user_id();
		$result = get_transient( $key );

		delete_transient( $key );

		if ( ! is_array( $result ) || ! isset( $result['selected'] ) ) {
			return;
		}

		$lines   = self::result_lines( $result );
		$failed  = $result['google_failed'] > 0 || ( $result['indexnow_sent'] > 0 && ! $result['indexnow_accepted'] ) || $result['skipped'] > 0 || $result['over_limit'] > 0;
		$type    = $failed ? 'warning' : 'success';
		$history = admin_url( 'admin.php?page=' . Fast_Index_Admin::SLUG_HISTORY );

		echo '<div class="' . esc_attr( 'notice notice-' . $type . ' is-dismissible fast-index-manual-result' ) . '">';
		echo '<p><strong>' . esc_html__( 'Fast Index', 'fast-index' ) . '</strong></p>';

		foreach ( $lines as $line ) {
			echo '<p>' . esc_html( $line ) . '</p>';
		}

		if ( $result['over_limit'] > 0 && Fast_Index_Upgrade::is_offered() ) {
			echo '<p class="fi-upgrade-line">' . esc_html( Fast_Index_Upgrade::limit_line() ) . ' <a href="' . esc_url( Fast_Index_Upgrade::upgrade_url() ) . '">' . esc_html__( 'See the premium plan', 'fast-index' ) . '</a></p>';
		}

		if ( current_user_can( 'manage_options' ) ) {
			echo '<p><a href="' . esc_url( $history ) . '">' . esc_html__( 'Open History', 'fast-index' ) . '</a></p>';
		}

		echo '</div>';
	}

	/**
	 * Removes the result argument from the address bar after the notice was shown.
	 *
	 * @param string[] $args Removable query arguments.
	 * @return string[]
	 */
	public static function removable_query_args( $args ) {
		$args   = is_array( $args ) ? $args : array();
		$args[] = self::RESULT_ARG;

		return $args;
	}
}
