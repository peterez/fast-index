<?php
/**
 * Premium module loader.
 *
 * Part of the premium package only. The WordPress.org package does not contain includes/premium/ (.distignore).
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the premium features when Freemius reports a license (or a trial) that allows the premium code.
 *
 * The license is checked here and nowhere else. Without it the module adds nothing and Fast Index works like the
 * free version; the settings that belong to the premium plan stay stored and are used again once it loads.
 */
class Fast_Index_Premium {

	/**
	 * Loads the module when the license allows it.
	 *
	 * @return bool Whether the module was loaded.
	 */
	public static function maybe_boot() {
		if ( ! self::can_load() ) {
			return false;
		}

		self::boot();

		return true;
	}

	/**
	 * Whether Freemius allows the premium code on this site.
	 *
	 * @return bool
	 */
	public static function can_load() {
		return function_exists( 'figi_fs' ) && figi_fs()->can_use_premium_code();
	}

	/**
	 * Adds the premium features to the hooks of the free version.
	 *
	 * @return void
	 */
	public static function boot() {
		require_once __DIR__ . '/class-fast-index-premium-features.php';
		require_once __DIR__ . '/class-fast-index-premium-backlog.php';

		Fast_Index_Premium_Features::register_hooks();
		Fast_Index_Premium_Backlog::register_hooks();

		/**
		 * Fires once the premium module has added its hooks.
		 */
		do_action( 'fast_index_premium_loaded' );
	}
}

Fast_Index_Premium::maybe_boot();
