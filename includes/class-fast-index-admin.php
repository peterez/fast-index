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
	 * Manual run page slug.
	 */
	const SLUG_RUN = 'fast-index-run';

	/**
	 * Prefix of the per-user transient holding one-time screen messages.
	 */
	const FLASH_PREFIX = 'fast_index_flash_';

	/**
	 * Prefix of the per-user transient holding the last manual run results.
	 */
	const RESULTS_PREFIX = 'fast_index_run_results_';

	/**
	 * Hook suffixes of the plugin pages.
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
		$help  = new Fast_Index_Help();
		$hooks = array(
			'settings' => add_menu_page( __( 'Fast Index', 'fast-index' ), __( 'Fast Index', 'fast-index' ), 'manage_options', self::SLUG, array( $this, 'render_settings' ) ),
			'history'  => add_submenu_page( self::SLUG, __( 'History', 'fast-index' ), __( 'History', 'fast-index' ), 'manage_options', self::SLUG_HISTORY, array( $this, 'render_history' ) ),
			'run'      => add_submenu_page( self::SLUG, __( 'Send Old Posts Now', 'fast-index' ), __( 'Send Old Posts Now', 'fast-index' ), 'manage_options', self::SLUG_RUN, array( $this, 'render_run' ) ),
			'help'     => add_submenu_page( self::SLUG, __( 'Help & FAQ', 'fast-index' ), __( 'Help & FAQ', 'fast-index' ), 'manage_options', Fast_Index_Help::SLUG, array( $help, 'render' ) ),
		);

		self::$page_hooks = array_values( array_filter( array_map( 'strval', $hooks ) ) );

		$help->register_screens( $hooks );
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
		wp_add_inline_script(
			'fast-index-admin',
			'window.fastIndexAdmin = ' . wp_json_encode(
				array(
					'upgradeMessage' => __( 'This option needs the premium plan. Go to the upgrade page?', 'fast-index' ),
					'upgradeUrl'     => admin_url( 'admin.php?page=fast-index-pricing' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		self::require_capability();

		$premium         = Fast_Index_Plugin::can_use_premium();
		$fast_index_view = array(
			'settings'   => Fast_Index_Settings::apply_plan_limits( Fast_Index_Settings::get(), $premium ),
			'premium'    => $premium,
			'post_types' => Fast_Index_Settings::public_post_types(),
			'categories' => get_categories( array( 'hide_empty' => false ) ),
			'accounts'   => self::account_rows(),
			'openssl'    => Fast_Index_Google_Client::is_supported(),
			'messages'   => self::take_flash(),
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
	 * Renders the manual run page.
	 *
	 * @return void
	 */
	public function render_run() {
		self::require_capability();

		$key     = self::RESULTS_PREFIX . get_current_user_id();
		$results = get_transient( $key );

		delete_transient( $key );

		$fast_index_view = array(
			'results' => is_array( $results ) ? $results : null,
		);

		include FAST_INDEX_PATH . 'view/run.php';
	}

	/**
	 * Stores the results of a manual run for the next page view of this user.
	 *
	 * @param array[] $results Rows from Fast_Index_Backlog::run().
	 * @return void
	 */
	public static function store_run_results( array $results ) {
		set_transient( self::RESULTS_PREFIX . get_current_user_id(), $results, 5 * MINUTE_IN_SECONDS );
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
			return __( 'No service account available', 'fast-index' );
		}

		if ( Fast_Index_Submitter::UNSUPPORTED === $code ) {
			return __( 'OpenSSL is missing', 'fast-index' );
		}

		if ( Fast_Index_Submitter::NO_RESPONSE === $code ) {
			return __( 'No answer from Google', 'fast-index' );
		}

		if ( 200 === $code ) {
			return '200 ' . __( 'OK', 'fast-index' );
		}

		return trim( $code . ' ' . get_status_header_desc( $code ) );
	}

	/**
	 * Display rows of the stored keys. The private key is never part of them.
	 *
	 * @return array[]
	 */
	private static function account_rows() {
		$statuses = Fast_Index_Accounts::statuses();
		$rows     = array();

		foreach ( Fast_Index_Accounts::all() as $id => $account ) {
			$rows[] = array(
				'id'     => $id,
				'email'  => $account['client_email'],
				'key_id' => $account['private_key_id'],
				'status' => isset( $statuses[ $id ] ) ? $statuses[ $id ] : null,
			);
		}

		return $rows;
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
