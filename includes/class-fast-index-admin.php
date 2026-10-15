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
	 * Style handle of the shared CultDevOps design language (assets/css/cdo-ui.css).
	 */
	const UI_HANDLE = 'fast-index-cdo-ui';

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
		( new Fast_Index_Manual() )->register_hooks();
		( new Fast_Index_Notices() )->register_hooks();
		( new Fast_Index_Upgrade() )->register_hooks();
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
			// The CultDevOps design language (the same file in every CultDevOps plugin), then the few Fast Index rules.
			wp_enqueue_style( self::UI_HANDLE, FAST_INDEX_URL . 'assets/css/cdo-ui.css', array(), FAST_INDEX_VERSION );
			wp_enqueue_style( 'fast-index-admin', FAST_INDEX_URL . 'assets/fi-css.css', array( self::UI_HANDLE ), FAST_INDEX_VERSION );
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
			'settings'         => $settings,
			'post_types'       => Fast_Index_Settings::post_type_choices(),
			'events'           => Fast_Index_Settings::event_choices(),
			'several_keys'     => Fast_Index_Accounts::several_keys(),
			'accounts'         => self::account_rows(),
			'unused'           => Fast_Index_Settings::unused( $settings ),
			'premium_card'     => Fast_Index_Upgrade::is_offered() ? self::premium_card() : null,
			'hints'            => Fast_Index_Upgrade::is_offered() ? self::hints() : array(),
			'openssl'          => Fast_Index_Google_Client::is_supported(),
			'messages'         => self::take_flash(),
			// The key file answers only while IndexNow is on.
			'indexnow_key_url' => Fast_Index_IndexNow::is_enabled( $settings ) ? Fast_Index_IndexNow::key_url() : '',
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
		$page = isset( $_GET['pn'] ) ? absint( $_GET['pn'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter, checked against the channel list.
		$channel  = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
		$channel  = array_key_exists( $channel, Fast_Index_Log::post_types() ) ? $channel : '';
		$settings = Fast_Index_Settings::get();
		$log      = Fast_Index_Log::page( $page, $channel );
		$stats    = Fast_Index_Log::stats( $settings );
		$base_url = admin_url( 'admin.php?page=' . self::SLUG_HISTORY );

		$fast_index_view = array(
			'stats'       => $stats,
			'entries'     => $log['entries'],
			'page'        => $page,
			'has_next'    => $log['has_next'],
			'channel'     => $channel,
			'channels'    => self::channel_names(),
			'base_url'    => '' === $channel ? $base_url : add_query_arg( 'channel', $channel, $base_url ),
			'filter_url'  => $base_url,
			'indexnow'    => Fast_Index_IndexNow::is_enabled( $settings ) || $stats['indexnow_today'] > 0,
			'upgrade'     => Fast_Index_Upgrade::history_lines( $stats, get_current_user_id() ),
			'upgrade_url' => Fast_Index_Upgrade::upgrade_url(),
			'hint'        => Fast_Index_Upgrade::is_offered() ? Fast_Index_Upgrade::hint( 'index_status' ) : '',
			'messages'    => self::take_flash(),
		);

		include FAST_INDEX_PATH . 'view/history.php';
	}

	/**
	 * Prints the header band of a Fast Index screen: the family mark, the plugin name, the plan and the screen title
	 * as the page's only <h1>, followed by the line under which WordPress places its notices.
	 *
	 * @param string $title Screen title, plain text.
	 * @param string $lead  Optional one-line introduction, plain text.
	 * @return void
	 */
	public static function render_band( $title, $lead = '' ) {
		$fast_index_band = array(
			'title' => (string) $title,
			'lead'  => (string) $lead,
		);

		include FAST_INDEX_PATH . 'view/partial-band.php';
	}

	/**
	 * Queues a one-time message for the next screen of this user.
	 *
	 * @param string $type      success or error.
	 * @param string $message   Plain text.
	 * @param string $link_url  Optional link printed after the message.
	 * @param string $link_text Text of that link.
	 * @return void
	 */
	public static function flash( $type, $message, $link_url = '', $link_text = '' ) {
		$key      = self::FLASH_PREFIX . get_current_user_id();
		$messages = get_transient( $key );
		$messages = is_array( $messages ) ? $messages : array();

		$messages[] = array(
			'type'      => 'error' === $type ? 'error' : 'success',
			'message'   => (string) $message,
			'link_url'  => '' !== (string) $link_text ? (string) $link_url : '',
			'link_text' => (string) $link_text,
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
		$clean    = array();

		delete_transient( $key );

		foreach ( is_array( $messages ) ? $messages : array() as $message ) {
			if ( is_array( $message ) && isset( $message['type'], $message['message'] ) ) {
				$clean[] = array_merge(
					array(
						'link_url'  => '',
						'link_text' => '',
					),
					$message
				);
			}
		}

		return $clean;
	}

	/**
	 * Names of the History channels. Product names, not translated.
	 *
	 * @return string[] Name by channel.
	 */
	public static function channel_names() {
		return array(
			Fast_Index_Log::CHANNEL_GOOGLE   => 'Google',
			Fast_Index_Log::CHANNEL_INDEXNOW => 'IndexNow',
		);
	}

	/**
	 * Text for a submission result code.
	 *
	 * @param int    $code    HTTP status or a Fast_Index_Submitter constant.
	 * @param string $channel Channel of the answer.
	 * @return string
	 */
	public static function status_label( $code, $channel = Fast_Index_Log::CHANNEL_GOOGLE ) {
		$code = (int) $code;

		if ( Fast_Index_Log::CHANNEL_INDEXNOW === $channel && $code <= 0 ) {
			return __( 'No answer from IndexNow', 'fast-index' );
		}

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
	 * Badge tone of a submission result: accepted, not sent (waits or needs a key), or refused.
	 *
	 * @param int $code HTTP status or a Fast_Index_Submitter constant.
	 * @return string A cdo-badge modifier class.
	 */
	public static function status_tone( $code ) {
		$code = (int) $code;

		if ( $code >= 200 && $code < 300 ) {
			return 'cdo-badge--success';
		}

		return $code < 0 ? 'cdo-badge--warning' : 'cdo-badge--error';
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
	 * Reason printed under the status of a History entry, for answers whose code alone does not say what happens.
	 *
	 * @param int    $code    HTTP status or a Fast_Index_Submitter constant.
	 * @param string $channel Channel of the answer.
	 * @return string Plain text; empty when the status needs no note.
	 */
	public static function status_note( $code, $channel = Fast_Index_Log::CHANNEL_GOOGLE ) {
		$code = (int) $code;

		if ( Fast_Index_Log::CHANNEL_INDEXNOW === $channel ) {
			switch ( $code ) {
				case 403:
					return __( 'IndexNow could not verify the key: open the key file address on the settings screen and check that it shows the key.', 'fast-index' );
				case 422:
					return __( 'IndexNow refused the address: it does not belong to the host of the key.', 'fast-index' );
				case 429:
					return __( 'IndexNow received too many requests from this site and treats them as possible spam. Send fewer addresses at once.', 'fast-index' );
				default:
					return '';
			}
		}

		if ( 429 === $code ) {
			return __( 'Daily quota reached: every key is paused until Google resets the quota at midnight Pacific Time.', 'fast-index' );
		}

		return '';
	}

	/**
	 * Date and time a key's pause ends, in the site's format.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	public static function pause_label( $timestamp ) {
		/* translators: %s: date and time. */
		return sprintf( __( 'Paused until %s', 'fast-index' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp ) );
	}

	/**
	 * Display rows of the stored keys. The private key is never part of them.
	 *
	 * @return array[] Each: id, email, key_id, status (last HTTP status or null), in_use, main (the key that is
	 *                 tried first), paused_until (Unix time, 0 when the key can be used now).
	 */
	private static function account_rows() {
		$statuses = Fast_Index_Accounts::statuses();
		$in_use   = Fast_Index_Accounts::in_use();
		$main     = array_key_first( $in_use );
		$rows     = array();

		foreach ( Fast_Index_Accounts::all() as $id => $account ) {
			$rows[] = array(
				'id'           => $id,
				'email'        => $account['client_email'],
				'key_id'       => $account['private_key_id'],
				'status'       => isset( $statuses[ $id ] ) ? $statuses[ $id ] : null,
				'in_use'       => isset( $in_use[ $id ] ),
				'main'         => $main === $id,
				'paused_until' => isset( $in_use[ $id ] ) ? Fast_Index_Accounts::paused_until( $id ) : 0,
			);
		}

		return $rows;
	}

	/**
	 * Content of the Premium box on the settings screen: a Free / Premium comparison and where to get the plan.
	 *
	 * @return array Keys: rows (see Fast_Index_Upgrade::comparison()), url (upgrade page).
	 */
	public static function premium_card() {
		return array(
			'rows' => Fast_Index_Upgrade::comparison(),
			'url'  => Fast_Index_Upgrade::upgrade_url(),
		);
	}

	/**
	 * The "Premium" hints of the settings screen, by section.
	 *
	 * @return array Keys: old_posts, exclude_category, keys (plain text) and url (upgrade page).
	 */
	private static function hints() {
		return array(
			'old_posts'        => Fast_Index_Upgrade::hint( 'old_posts' ),
			'exclude_category' => Fast_Index_Upgrade::hint( 'exclude_category' ),
			'keys'             => Fast_Index_Upgrade::hint( 'keys' ),
			'url'              => Fast_Index_Upgrade::upgrade_url(),
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
