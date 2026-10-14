<?php
/**
 * Admin menu, screens and assets.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Fast Index menu and renders its screens. Writes go to Fast_Index_Admin_Actions.
 */
class Fast_Index_Admin {

	/**
	 * Settings page slug (kept from 2.x; the Freemius menu configuration uses it).
	 */
	const SLUG = 'fast-index';

	/**
	 * History page slug.
	 */
	const SLUG_HISTORY = 'fast-index-history';

	/**
	 * Prefix of the per-user transient holding one-time screen messages.
	 */
	const FLASH_PREFIX = 'fast_index_flash_';

	/**
	 * Hook suffixes of the plugin pages, by screen key (settings, history, help and pages added by extensions).
	 *
	 * @var string[]
	 */
	private static $page_hooks = array();

	/**
	 * Registers the admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Late priority kept from 2.x: Freemius adjusts this menu after it exists.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99999999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		( new Fast_Index_Help() )->register_hooks();
		( new Fast_Index_Admin_Actions() )->register_hooks();
		( new Fast_Index_Notices() )->register_hooks();
	}

	/**
	 * Adds the menu and its pages.
	 *
	 * @return void
	 */
	public function add_menu() {
		$help = new Fast_Index_Help();

		self::$page_hooks = array();

		self::remember_page( 'settings', add_menu_page( __( 'Fast Index', 'fast-index' ), __( 'Fast Index', 'fast-index' ), 'manage_options', self::SLUG, array( $this, 'render_settings' ) ) );
		self::remember_page( 'history', add_submenu_page( self::SLUG, __( 'History', 'fast-index' ), __( 'History', 'fast-index' ), 'manage_options', self::SLUG_HISTORY, array( $this, 'render_history' ) ) );

		/**
		 * Fires between the History and Help & FAQ entries of the Fast Index menu. Add pages with
		 * Fast_Index_Admin::add_page(), so they get the plugin styles and a help tab.
		 *
		 * @param string $parent_slug Slug of the Fast Index menu.
		 */
		do_action( 'fast_index_admin_menu', self::SLUG );

		self::remember_page( 'help', add_submenu_page( self::SLUG, __( 'Help & FAQ', 'fast-index' ), __( 'Help & FAQ', 'fast-index' ), 'manage_options', Fast_Index_Help::SLUG, array( $help, 'render' ) ) );

		$help->register_screens( self::$page_hooks );
	}

	/**
	 * Adds a page to the Fast Index menu. Call it on the fast_index_admin_menu action.
	 *
	 * @param string   $key      Screen key; its help tab text comes from Fast_Index_Help_Content::screen_help().
	 * @param string   $title    Page and menu title.
	 * @param string   $slug     Page slug.
	 * @param callable $callback Renders the page.
	 * @return string Hook suffix, empty when the page was not added.
	 */
	public static function add_page( $key, $title, $slug, $callback ) {
		return self::remember_page( $key, add_submenu_page( self::SLUG, $title, $title, 'manage_options', $slug, $callback ) );
	}

	/**
	 * Stores the hook suffix of a plugin page.
	 *
	 * @param string       $key  Screen key.
	 * @param string|false $hook Hook suffix returned by add_menu_page() or add_submenu_page().
	 * @return string
	 */
	private static function remember_page( $key, $hook ) {
		if ( is_string( $hook ) && '' !== $hook ) {
			self::$page_hooks[ (string) $key ] = $hook;
			return $hook;
		}

		return '';
	}

	/**
	 * Whether a screen ID belongs to a plugin page.
	 *
	 * @param string $screen_id Screen ID (equals the page hook suffix).
	 * @return bool
	 */
	public static function is_plugin_screen( $screen_id ) {
		return in_array( (string) $screen_id, self::$page_hooks, true );
	}

	/**
	 * Loads the stylesheet and script on the plugin pages, and the script on the dashboard
	 * while a notice is waiting.
	 *
	 * @param string $hook_suffix Hook suffix of the current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$is_plugin_page = self::is_plugin_screen( $hook_suffix );

		if ( ! $is_plugin_page && ! ( 'index.php' === $hook_suffix && Fast_Index_Notices::has_pending() ) ) {
			return;
		}

		if ( $is_plugin_page ) {
			wp_enqueue_style( 'fast-index-admin', FAST_INDEX_URL . 'assets/fi-css.css', array(), FAST_INDEX_VERSION );
		}

		wp_enqueue_script( 'fast-index-admin', FAST_INDEX_URL . 'assets/fast-index-admin.js', array(), FAST_INDEX_VERSION, true );
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		self::require_capability();

		$settings        = Fast_Index_Settings::get();
		$fast_index_view = array(
			'settings'     => $settings,
			'post_types'   => Fast_Index_Settings::post_type_choices(),
			'events'       => Fast_Index_Settings::event_choices(),
			'several_keys' => Fast_Index_Accounts::several_keys(),
			'accounts'     => self::account_rows(),
			'unused'       => Fast_Index_Settings::unused( $settings ),
			'premium_card' => Fast_Index_Plugin::premium_loaded() ? null : self::premium_card(),
			'openssl'      => Fast_Index_Google_Client::is_supported(),
			'messages'     => self::take_flash(),
		);

		include FAST_INDEX_PATH . 'view/settings.php';
	}

	/**
	 * Renders the history page.
	 *
	 * @return void
	 */
	public function render_history() {
		self::require_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page number.
		$page            = isset( $_GET['pn'] ) ? absint( $_GET['pn'] ) : 0;
		$log             = Fast_Index_Log::page( $page );
		$fast_index_view = array(
			'stats'    => Fast_Index_Log::stats( Fast_Index_Settings::get() ),
			'entries'  => $log['entries'],
			'page'     => $page,
			'has_next' => $log['has_next'],
			'base_url' => admin_url( 'admin.php?page=' . self::SLUG_HISTORY ),
		);

		include FAST_INDEX_PATH . 'view/history.php';
	}

	/**
	 * Queues a one-time message for the next screen of this user.
	 *
	 * @param string $type    success or error.
	 * @param string $message Plain text.
	 * @return void
	 */
	public static function flash( $type, $message ) {
		$key      = self::FLASH_PREFIX . get_current_user_id();
		$messages = get_transient( $key );
		$messages = is_array( $messages ) ? $messages : array();

		$messages[] = array(
			'type'    => 'error' === $type ? 'error' : 'success',
			'message' => (string) $message,
		);

		set_transient( $key, $messages, MINUTE_IN_SECONDS );
	}

	/**
	 * Returns and clears the queued messages of this user.
	 *
	 * @return array[]
	 */
	public static function take_flash() {
		$key      = self::FLASH_PREFIX . get_current_user_id();
		$messages = get_transient( $key );

		delete_transient( $key );

		return is_array( $messages ) ? $messages : array();
	}

	/**
	 * Text for a submission result code.
	 *
	 * @param int $code HTTP status or a Fast_Index_Submitter constant.
	 * @return string
	 */
	public static function status_label( $code ) {
		$code = (int) $code;

		if ( Fast_Index_Submitter::NO_ACCOUNT === $code ) {
			return __( 'Not sent: no service account key', 'fast-index' );
		}

		if ( Fast_Index_Submitter::RESTING === $code ) {
			return __( 'Not sent: every key was paused', 'fast-index' );
		}

		if ( Fast_Index_Submitter::UNSUPPORTED === $code ) {
			return __( 'Not sent: OpenSSL is missing', 'fast-index' );
		}

		if ( Fast_Index_Submitter::NO_RESPONSE === $code ) {
			return __( 'No answer from Google', 'fast-index' );
		}

		// HTTP status names are not translated: "200 OK" reads like "403 Forbidden" and "429 Too Many Requests".
		return trim( $code . ' ' . get_status_header_desc( $code ) );
	}

	/**
	 * Note under the status of a History entry that waits for a key.
	 *
	 * @return string
	 */
	public static function retry_note() {
		return __( 'Fast Index tries again when a key is ready.', 'fast-index' );
	}

	/**
	 * Display rows of the stored keys. The private key is never part of them.
	 *
	 * @return array[] Each: id, email, key_id, status (last HTTP status or null), in_use.
	 */
	private static function account_rows() {
		$statuses = Fast_Index_Accounts::statuses();
		$in_use   = Fast_Index_Accounts::in_use();
		$rows     = array();

		foreach ( Fast_Index_Accounts::all() as $id => $account ) {
			$rows[] = array(
				'id'     => $id,
				'email'  => $account['client_email'],
				'key_id' => $account['private_key_id'],
				'status' => isset( $statuses[ $id ] ) ? $statuses[ $id ] : null,
				'in_use' => isset( $in_use[ $id ] ),
			);
		}

		return $rows;
	}

	/**
	 * Content of the Premium box on the settings screen: what the premium plan adds and where to get it.
	 *
	 * @return array Keys: features (plain text lines), url (upgrade page).
	 */
	public static function premium_card() {
		return array(
			'features' => array(
				__( 'Pages and custom post types', 'fast-index' ),
				__( 'Notifications when a published post is edited, trashed or deleted', 'fast-index' ),
				__( 'Old posts sent in small hourly batches, within a daily limit you set', 'fast-index' ),
				__( 'Categories whose posts are never sent', 'fast-index' ),
				__( 'Several service account keys: when Google pauses one, the next one is tried', 'fast-index' ),
			),
			'url'      => function_exists( 'figi_fs' ) ? figi_fs()->get_upgrade_url() : '',
		);
	}

	/**
	 * Stops the request when the user cannot manage options.
	 *
	 * @return void
	 */
	private static function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fast-index' ), '', array( 'response' => 403 ) );
		}
	}
}
