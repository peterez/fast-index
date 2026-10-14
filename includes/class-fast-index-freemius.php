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
	 * Plugin icon shown on the Freemius screens. A local file, so the SDK never downloads an icon or writes
	 * one into the plugin folder.
	 *
	 * @return string Absolute path.
	 */
	public static function icon() {
		return FAST_INDEX_PATH . 'assets/fast-index-icon.png';
	}
}
