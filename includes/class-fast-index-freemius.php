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
		// Clean-up when the plugin is deleted, after Freemius recorded the uninstall (there is no uninstall.php).
		$fs->add_action( 'after_uninstall', array( 'Fast_Index_Uninstaller', 'run_once' ) );
	}

	/**
	 * Hides the advertising notices of the SDK.
	 *
	 * @param bool  $show    Whether the notice is shown.
	 * @param array $message Notice data; its id names the notice.
	 * @return bool
	 */
	public static function show_admin_notice( $show, $message ) {
		$id = is_array( $message ) && isset( $message['id'] ) ? (string) $message['id'] : '';

		return in_array( $id, self::HIDDEN_NOTICES, true ) ? false : (bool) $show;
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
	 * @return string Absolute path.
	 */
	public static function icon() {
		return FAST_INDEX_PATH . 'assets/fast-index-icon.png';
	}
}
