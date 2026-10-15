<?php
/**
 * Plugin bootstrap.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin classes to WordPress hooks. Holds no business logic.
 */
class Fast_Index_Plugin {

	/**
	 * Registers all hooks. Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public function run() {
		Fast_Index_Installer::maybe_upgrade();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'change_locale', array( __CLASS__, 'add_bundled_translation' ) );
		add_action( Fast_Index_Installer::NETWORK_HOOK, array( 'Fast_Index_Installer', 'upgrade_network_batch' ) );

		( new Fast_Index_Post_Watcher() )->register_hooks();
		( new Fast_Index_Cron() )->register_hooks();
		( new Fast_Index_IndexNow() )->register_hooks();

		if ( is_multisite() ) {
			// New site: set it up after core created its tables (core runs at priority 10).
			add_action( 'wp_initialize_site', array( 'Fast_Index_Installer', 'on_initialize_site' ), 900 );
		}

		if ( is_admin() ) {
			( new Fast_Index_Admin() )->register_hooks();
		}
	}

	/**
	 * Loads translations and registers the log post type and the setting. Runs on init.
	 *
	 * @return void
	 */
	public function init() {
		// Bundled translations in /languages; translate.wordpress.org packs load on their own.
		load_plugin_textdomain( 'fast-index', false, dirname( plugin_basename( FAST_INDEX_FILE ) ) . '/languages' );
		self::add_bundled_translation();
		Fast_Index_Freemius::translate_texts();

		Fast_Index_Log::register_post_type();
		Fast_Index_Settings::register();
	}

	/**
	 * Adds the bundled translation after a translate.wordpress.org language pack.
	 *
	 * WordPress loads only the pack when one is installed, and a pack can be older than this release:
	 * the strings it lacks would show in English. The pack keeps priority; the bundled file fills the gaps.
	 * Runs on init and change_locale.
	 *
	 * @param string $locale Optional. Locale to load. Default: the locale WordPress uses for this request.
	 * @return bool Whether the bundled translation was added.
	 */
	public static function add_bundled_translation( $locale = '' ) {
		$locale = is_string( $locale ) && '' !== $locale ? $locale : determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'fast-index' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.

		$bundled = dirname( FAST_INDEX_FILE ) . '/languages/fast-index-' . $locale . '.mo';
		$pack    = WP_LANG_DIR . '/plugins/fast-index-' . $locale;
		if ( ! is_readable( $bundled ) || ( ! is_readable( $pack . '.mo' ) && ! is_readable( $pack . '.l10n.php' ) ) ) {
			return false;
		}

		if ( ! is_textdomain_loaded( 'fast-index' ) ) {
			// WordPress 6.7 and later load the pack only when the first string is translated; load it first here.
			load_textdomain( 'fast-index', $pack . '.mo', $locale );
		}

		return load_textdomain( 'fast-index', $bundled, $locale );
	}

	/**
	 * Whether the premium module is loaded. The module fires fast_index_premium_loaded once it has added its
	 * hooks; it checks the license itself. No license is checked here.
	 *
	 * @return bool
	 */
	public static function premium_loaded() {
		return did_action( 'fast_index_premium_loaded' ) > 0;
	}
}
