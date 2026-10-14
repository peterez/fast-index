<?php
/**
 * Help & FAQ screen, help tabs and system information.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Help & FAQ sub page, adds the help tab to every plugin screen and builds the
 * read-only system information. The texts come from Fast_Index_Help_Content.
 *
 * The page assets load on this page only. No secret (key, token) is ever part of the output.
 */
class Fast_Index_Help {

	/**
	 * Help & FAQ page slug.
	 */
	const SLUG = 'fast-index-help';

	/**
	 * Hook suffixes of the plugin screens, by screen key (settings, history, run, help).
	 *
	 * @var string[]
	 */
	private static $screens = array();

	/**
	 * Registers the asset hook.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Remembers the screens and adds a help tab to each of them when it loads.
	 *
	 * @param string[] $hooks Hook suffix by screen key: settings, history, run, help.
	 * @return void
	 */
	public function register_screens( array $hooks ) {
		self::$screens = array_filter( array_map( 'strval', $hooks ) );

		foreach ( self::$screens as $hook ) {
			add_action( 'load-' . $hook, array( $this, 'add_help_tabs' ) );
		}
	}

	/**
	 * Adds the help tab and the sidebar to the current plugin screen.
	 *
	 * @return void
	 */
	public function add_help_tabs() {
		$screen = get_current_screen();
		$key    = $screen instanceof WP_Screen ? array_search( $screen->id, self::$screens, true ) : false;

		if ( false === $key ) {
			return;
		}

		$tab = Fast_Index_Help_Content::screen_help( (string) $key );

		$screen->add_help_tab(
			array(
				'id'      => 'fast-index-help-' . $key,
				'title'   => $tab['title'],
				'content' => wp_kses( $tab['content'], Fast_Index_Help_Content::allowed_html() ),
			)
		);
		$screen->set_help_sidebar( wp_kses( Fast_Index_Help_Content::sidebar(), Fast_Index_Help_Content::allowed_html() ) );
	}

	/**
	 * Loads the stylesheet and script on the Help & FAQ page only.
	 *
	 * @param string $hook_suffix Hook suffix of the current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! isset( self::$screens['help'] ) || self::$screens['help'] !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'fast-index-help', FAST_INDEX_URL . 'assets/fast-index-help.css', array(), FAST_INDEX_VERSION );
		wp_enqueue_script( 'fast-index-help', FAST_INDEX_URL . 'assets/fast-index-help.js', array(), FAST_INDEX_VERSION, true );
		wp_add_inline_script(
			'fast-index-help',
			'window.fastIndexHelp = ' . wp_json_encode(
				array(
					'copied'   => __( 'System info copied.', 'fast-index' ),
					'selected' => __( 'System info selected. Press Ctrl+C (Cmd+C on a Mac) to copy it.', 'fast-index' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Renders the Help & FAQ page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fast-index' ), '', array( 'response' => 403 ) );
		}

		$fast_index_view = array(
			'sections'    => Fast_Index_Help_Content::sections(),
			'steps'       => Fast_Index_Help_Content::getting_started_steps(),
			'settings'    => Fast_Index_Help_Content::settings(),
			'problems'    => Fast_Index_Help_Content::troubleshooting(),
			'faq'         => Fast_Index_Help_Content::faq(),
			'checklist'   => Fast_Index_Help_Content::support_checklist(),
			'system_info' => self::system_info_text(),
			'support_url' => Fast_Index_Help_Content::URL_SUPPORT,
		);

		include FAST_INDEX_PATH . 'view/help.php';
	}

	/**
	 * Read-only facts for a support request. Contains no key, token or email address.
	 *
	 * @return array[] Each: label, value (plain text).
	 */
	public static function system_info() {
		$settings = Fast_Index_Settings::get();
		$accounts = Fast_Index_Accounts::all();
		$stats    = Fast_Index_Log::stats( $settings );
		$next     = wp_next_scheduled( Fast_Index_Cron::BACKLOG_HOOK );
		$yes      = __( 'Yes', 'fast-index' );
		$no       = __( 'No', 'fast-index' );

		return array(
			array(
				'label' => __( 'Fast Index version', 'fast-index' ),
				'value' => FAST_INDEX_VERSION,
			),
			array(
				'label' => __( 'WordPress version', 'fast-index' ),
				'value' => get_bloginfo( 'version' ),
			),
			array(
				'label' => __( 'PHP version', 'fast-index' ),
				'value' => PHP_VERSION,
			),
			array(
				'label' => __( 'Multisite', 'fast-index' ),
				'value' => is_multisite() ? $yes : $no,
			),
			array(
				'label' => __( 'Site language', 'fast-index' ),
				'value' => get_locale(),
			),
			array(
				'label' => __( 'OpenSSL available', 'fast-index' ),
				'value' => Fast_Index_Google_Client::is_supported() ? $yes : $no,
			),
			array(
				'label' => __( 'WP-Cron disabled (DISABLE_WP_CRON)', 'fast-index' ),
				'value' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? $yes : $no,
			),
			array(
				'label' => __( 'Premium plan active', 'fast-index' ),
				'value' => Fast_Index_Plugin::can_use_premium() ? $yes : $no,
			),
			array(
				'label' => __( 'Status setting', 'fast-index' ),
				'value' => Fast_Index_Settings::is_active( $settings ) ? __( 'Active', 'fast-index' ) : __( 'Passive', 'fast-index' ),
			),
			array(
				'label' => __( 'Post types enabled', 'fast-index' ),
				'value' => implode( ', ', Fast_Index_Settings::enabled_post_types( $settings ) ),
			),
			array(
				'label' => __( 'Stored service account keys', 'fast-index' ),
				'value' => (string) count( $accounts ),
			),
			array(
				'label' => __( 'Keys available now (not resting)', 'fast-index' ),
				'value' => (string) count( Fast_Index_Accounts::available() ),
			),
			array(
				'label' => __( 'Last answer for each key', 'fast-index' ),
				'value' => self::key_statuses( array_keys( $accounts ) ),
			),
			array(
				'label' => __( 'Next hourly old-post run', 'fast-index' ),
				'value' => $next ? gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' : __( 'Not scheduled', 'fast-index' ),
			),
			array(
				'label' => __( 'Sent in the last 24 hours', 'fast-index' ),
				'value' => (string) $stats['today'],
			),
			array(
				'label' => __( 'Posts waiting', 'fast-index' ),
				'value' => (string) $stats['waiting'],
			),
			array(
				'label' => __( 'Last notification', 'fast-index' ),
				'value' => self::last_notification(),
			),
		);
	}

	/**
	 * The system information as plain text, one "label: value" line each.
	 *
	 * @return string
	 */
	public static function system_info_text() {
		$lines = array();

		foreach ( self::system_info() as $row ) {
			$lines[] = $row['label'] . ': ' . $row['value'];
		}

		return implode( "\n", $lines );
	}

	/**
	 * Last status of every key, numbered. The account IDs and emails are not shown.
	 *
	 * @param string[] $ids Account IDs.
	 * @return string
	 */
	private static function key_statuses( array $ids ) {
		if ( array() === $ids ) {
			return '-';
		}

		$statuses = Fast_Index_Accounts::statuses();
		$parts    = array();
		$number   = 0;

		foreach ( $ids as $id ) {
			++$number;
			$parts[] = sprintf(
				/* translators: 1: number of the key in the list, 2: last answer from Google. */
				__( 'Key %1$d: %2$s', 'fast-index' ),
				$number,
				isset( $statuses[ $id ] ) ? Fast_Index_Admin::status_label( $statuses[ $id ] ) : __( 'Not used yet', 'fast-index' )
			);
		}

		return implode( '; ', $parts );
	}

	/**
	 * Result of the newest notification: time, kind and answer. The address is left out.
	 *
	 * @return string
	 */
	private static function last_notification() {
		$entry = Fast_Index_Log::latest();

		if ( null === $entry ) {
			return __( 'Nothing was sent yet.', 'fast-index' );
		}

		return gmdate( 'Y-m-d H:i:s', $entry['timestamp'] ) . ' UTC, ' . $entry['type'] . ', ' . Fast_Index_Admin::status_label( $entry['code'] );
	}
}
