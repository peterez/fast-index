<?php
/**
 * Freemius SDK configuration.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds the settings that index.php passes to fs_dynamic_init(), so they can be read and tested in one place.
 *
 * Freemius sells the premium plan. The free version from WordPress.org contains no premium code: the premium
 * package adds includes/premium/, and only then does is_premium become true. No license is checked here; the
 * premium module checks it itself before it loads (includes/premium/class-fast-index-premium.php).
 */
class Fast_Index_Freemius {

	/**
	 * Freemius product ID (unchanged from 2.x).
	 */
	const ID = '11893';

	/**
	 * Freemius public key (unchanged from 2.x).
	 */
	const PUBLIC_KEY = 'pk_4352cecbab080b84df64da3246477';

	/**
	 * Folder name Freemius uses for the premium package when it is installed next to the free version.
	 * It must differ from the free slug: the SDK treats an existing folder of that name as an installed
	 * premium version and turns its updater on for it.
	 */
	const PREMIUM_SLUG = 'fast-index-premium';

	/**
	 * Whether this copy of the plugin is the premium package (the premium module is present).
	 *
	 * @return bool
	 */
	public static function has_premium_package() {
		return file_exists( FAST_INDEX_PREMIUM_LOADER );
	}

	/**
	 * Arguments for fs_dynamic_init().
	 *
	 * @param bool $is_premium Whether this copy is the premium package.
	 * @return array
	 */
	public static function config( $is_premium ) {
		return array(
			'id'                  => self::ID,
			'slug'                => 'fast-index',
			'premium_slug'        => self::PREMIUM_SLUG,
			'type'                => 'plugin',
			'public_key'          => self::PUBLIC_KEY,
			'is_premium'          => (bool) $is_premium,
			// Freemius adds this to the name of the premium version and removes it again for its own screens.
			'premium_suffix'      => '(Premium)',
			'has_premium_version' => true,
			'has_addons'          => false,
			'has_paid_plans'      => true,
			'is_org_compliant'    => true,
			// The opt-in screen keeps its Skip button; the plugin works the same either way.
			'enable_anonymous'    => true,
			'menu'                => array(
				'slug'       => Fast_Index_Admin::SLUG,
				'first-path' => 'admin.php?page=' . Fast_Index_Admin::SLUG,
				// The Freemius contact form loads in an iframe: not used.
				'contact'    => false,
				// Help & FAQ already links to the WordPress.org support forum.
				'support'    => false,
				'account'    => true,
				// Fast Index has no network admin screen; Freemius adds its own network menu when it needs one.
				'network'    => false,
			),
		);
	}

	/**
	 * Freemius notices that only advertise. Fast Index shows its own offers on its own screens (Fast_Index_Upgrade),
	 * never on the dashboard.
	 */
	const HIDDEN_NOTICES = array( 'trial_promotion', 'affiliate_program' );

	/**
	 * Attaches the plugin's settings to the Freemius instance. Runs right after figi_fs().
	 *
	 * @return void
	 */
	public static function register_hooks() {
		$fs = figi_fs();

		$fs->add_filter( 'plugin_icon', array( __CLASS__, 'icon' ) );
		// No trial promotion: Freemius would show it as a notice on every admin screen.
		$fs->add_filter( 'show_trial', '__return_false' );
		$fs->add_filter( 'show_affiliate_program_notice', '__return_false' );
		$fs->add_filter( 'show_admin_notice', array( __CLASS__, 'show_admin_notice' ), 10, 2 );
		// Network activation: Freemius would remove the Fast Index menu of every site until the network admin opts
		// in or skips, and the sites would answer "Cannot load fast-index". Each site keeps its menu and shows the
		// opt-in screen with its Skip button instead.
		$fs->add_filter( 'should_hide_site_admin_settings_on_network_activation_mode', '__return_false' );
		foreach ( self::NETWORK_CHOICE_FILTERS as $filter ) {
			$fs->add_filter( $filter, array( __CLASS__, 'after_network_choice_url' ) );
		}
		// Clean-up when the plugin is deleted, after Freemius recorded the uninstall (there is no uninstall.php).
		$fs->add_action( 'after_uninstall', array( 'Fast_Index_Uninstaller', 'run_once' ) );
		// Before the footer scripts are printed, so the guard runs before the pricing script of the SDK.
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'block_pricing_analytics' ), 1 );
	}

	/**
	 * Slug of the Freemius Upgrade (plans and prices) page.
	 */
	const PRICING_SLUG = 'fast-index-pricing';

	/**
	 * Keeps Google Analytics off the Upgrade page.
	 *
	 * The pricing script of the SDK (assets/js/pricing/freemius-pricing.js, 2.13.4) adds
	 * www.google-analytics.com/analytics.js to the page whenever it draws the plans. That would track the visit
	 * without consent (WordPress.org guideline 7), and no setting of the SDK turns it off. This guard runs before
	 * that script and refuses to insert a script element from google-analytics.com on that page; the plans, the
	 * prices and the Freemius checkout keep working. Same guard as in the other CultDevOps plugins.
	 *
	 * @return void
	 */
	public static function block_pricing_analytics() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Only on the Upgrade page of this plugin (site or network admin); the pages of other plugins are theirs.
		if ( ! $screen instanceof WP_Screen || ! self::is_pricing_screen( $screen->id ) || ! wp_script_is( 'freemius-pricing', 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script( 'freemius-pricing', self::analytics_guard(), 'before' );
	}

	/**
	 * Whether a screen ID is the Upgrade page of Fast Index, in the site or in the network admin.
	 *
	 * @param string $screen_id Screen ID.
	 * @return bool
	 */
	public static function is_pricing_screen( $screen_id ) {
		$hook = get_plugin_page_hookname( self::PRICING_SLUG, Fast_Index_Admin::SLUG );

		return in_array( (string) $screen_id, array( $hook, $hook . '-network' ), true );
	}

	/**
	 * The script of block_pricing_analytics().
	 *
	 * @return string JavaScript.
	 */
	public static function analytics_guard() {
		return '( function () { var insert = Node.prototype.insertBefore; Node.prototype.insertBefore = function ( node, child ) { if ( node && "SCRIPT" === node.nodeName && /(^|\\.)google-analytics\\.com$/.test( ( node.src ? new URL( node.src, document.baseURI ) : { hostname: "" } ).hostname ) ) { return node; } return insert.call( this, node, child ); }; }() );';
	}

	/**
	 * Freemius filters of the page it opens after the network admin skipped, allowed or delegated the opt-in.
	 */
	const NETWORK_CHOICE_FILTERS = array( 'after_skip_url', 'after_connect_url', 'after_delegation_url', 'after_network_activation_url' );

	/**
	 * After the network admin's opt-in choice Freemius opens the Fast Index page of the network admin, which only
	 * exists while the opt-in screen is shown: the network admin would read "Cannot load fast-index". Opens the
	 * settings of the main site instead. Every other address is kept, and so is the return address of the opt-in
	 * screen itself.
	 *
	 * @param string $url Address Freemius opens.
	 * @return string
	 */
	public static function after_network_choice_url( $url ) {
		// fs_is_network_admin() also knows the network admin in Ajax requests.
		$network = is_network_admin() || ( function_exists( 'fs_is_network_admin' ) && fs_is_network_admin() );
		if ( ! $network || ! is_string( $url ) || network_admin_url( 'admin.php?page=' . Fast_Index_Admin::SLUG ) !== $url ) {
			return $url;
		}
		if ( figi_fs()->is_activation_mode() ) {
			return $url;
		}

		return get_admin_url( get_main_site_id(), 'admin.php?page=' . Fast_Index_Admin::SLUG );
	}

	/**
	 * Hides the advertising notices of the SDK and keeps its other notices on the Fast Index screens and the Plugins
	 * screen, off the dashboard and the screens of other plugins and of WordPress.
	 *
	 * @param bool  $show    Whether the notice is shown.
	 * @param array $message Notice data; its id names the notice.
	 * @return bool
	 */
	public static function show_admin_notice( $show, $message ) {
		$id   = is_array( $message ) && isset( $message['id'] ) ? (string) $message['id'] : '';
		$type = is_array( $message ) && isset( $message['type'] ) ? (string) $message['type'] : '';

		if ( in_array( $id, self::HIDDEN_NOTICES, true ) || 'promotion' === $type ) {
			return false;
		}

		// The SDK's notices ("Premium activated", the connectivity retry, the opt-in reminder) belong to the Fast
		// Index screens and the Plugins screen; they stay off the dashboard and the screens of other plugins.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen instanceof WP_Screen && ! Fast_Index_Admin::is_plugin_screen( $screen->id ) && ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return false;
		}

		return (bool) $show;
	}

	/**
	 * Translates the Freemius texts that Help & FAQ names (the Upgrade menu entry and the two buttons of the opt-in
	 * screen) with the plugin's own strings, so they match in every language of the plugin; the SDK itself has no
	 * Turkish, Portuguese or Arabic. Runs on init, after the plugin's translations are loaded.
	 *
	 * @return void
	 */
	public static function translate_texts() {
		if ( ! function_exists( 'fs_override_i18n' ) ) {
			return;
		}

		fs_override_i18n(
			array(
				'upgrade'        => __( 'Upgrade', 'fast-index' ),
				'pricing'        => _x( 'Pricing', 'Title of the Freemius page with the plans', 'fast-index' ),
				'skip'           => _x( 'Skip', 'Button of the Freemius opt-in screen', 'fast-index' ),
				'opt-in-connect' => _x( 'Allow & Continue', 'Button of the Freemius opt-in screen', 'fast-index' ),
			),
			'fast-index'
		);
	}

	/**
	 * Plugin icon shown on the Freemius screens. A local file, so the SDK never downloads an icon or writes
	 * one into the plugin folder.
	 *
	 * Freemius turns the path into an address only below the plugins folder, so the path goes through that folder
	 * even when the plugin folder is a symbolic link to a place outside it.
	 *
	 * @return string Absolute path.
	 */
	public static function icon() {
		$path = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( FAST_INDEX_FILE ) ) . '/assets/fast-index-icon.png';

		return file_exists( $path ) ? $path : FAST_INDEX_PATH . 'assets/fast-index-icon.png';
	}
}
