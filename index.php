<?php
/**
 * Fast Index
 *
 * @package           FastIndex
 * @author            Samet AKIN
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Fast Index for Google Indexing API
 * Description:       Sends new, updated and deleted posts to the Google Indexing API so Google can crawl them sooner.
 * Version:           3.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Samet AKIN
 * Author URI:        https://www.linkedin.com/in/samet-akin/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fast-index
 * Domain Path:       /languages
 *
 * Read by the Freemius deployment: a free version that Freemius builds from the premium package leaves this out.
 * @fs_premium_only /includes/premium/
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'figi_fs' ) ) {
	// The free version and the premium package can be installed side by side, and the other one is loaded
	// already. Tell Freemius which one this file is and stop: activating it makes Freemius deactivate the other.
	figi_fs()->set_basename( file_exists( __DIR__ . '/includes/premium/class-fast-index-premium.php' ), __FILE__ );
	return;
}

define( 'FAST_INDEX_VERSION', '3.0.0' );
define( 'FAST_INDEX_DB_VERSION', '3.0.0' ); // Data version: raised only when stored data changes.
define( 'FAST_INDEX_FILE', __FILE__ );
define( 'FAST_INDEX_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAST_INDEX_URL', plugin_dir_url( __FILE__ ) );
// Loader of the premium module. Only the premium package has it; the WordPress.org package leaves includes/premium/ out.
define( 'FAST_INDEX_PREMIUM_LOADER', FAST_INDEX_PATH . 'includes/premium/class-fast-index-premium.php' );

require_once FAST_INDEX_PATH . 'includes/class-fast-index-settings.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-accounts.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-files.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-key-upload.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-google-client.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-log.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-submitter.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-retry.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-indexnow.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-notifier.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-cron.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-post-watcher.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-lock.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-migration.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-notices.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-installer.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-help-content.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-help.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-admin.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-admin-actions.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-manual.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-upgrade.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-uninstaller.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-freemius.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-plugin.php';

// Declared inside a condition on purpose: PHP declares an unconditional function before the file runs, and the
// check at the top of this file would then always find it.
if ( ! function_exists( 'figi_fs' ) ) {
	/**
	 * Returns the Freemius SDK instance of this plugin (the accessor name is unchanged from 2.x).
	 *
	 * @return Freemius
	 */
	function figi_fs() {
		global $figi_fs;

		if ( ! isset( $figi_fs ) ) {
			if ( ! defined( 'WP_FS__PRODUCT_11893_MULTISITE' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constant name is defined by the Freemius SDK.
				define( 'WP_FS__PRODUCT_11893_MULTISITE', true );
			}

			require_once FAST_INDEX_PATH . 'freemius-sdk/start.php';

			$figi_fs = fs_dynamic_init( Fast_Index_Freemius::config( Fast_Index_Freemius::has_premium_package() ) );
		}

		return $figi_fs;
	}
}

figi_fs();
Fast_Index_Freemius::register_hooks();
do_action( 'figi_fs_loaded' );

register_activation_hook( FAST_INDEX_FILE, array( 'Fast_Index_Installer', 'activate' ) );
register_deactivation_hook( FAST_INDEX_FILE, array( 'Fast_Index_Installer', 'deactivate' ) );
// Deleting the plugin: WordPress loads this file and fires this action. Fallback for the Freemius after_uninstall
// action (see Fast_Index_Uninstaller); the routine runs once either way.
add_action( 'uninstall_' . plugin_basename( FAST_INDEX_FILE ), array( 'Fast_Index_Uninstaller', 'run_once' ), 20 );

add_action( 'plugins_loaded', array( new Fast_Index_Plugin(), 'run' ) );

if ( Fast_Index_Freemius::has_premium_package() ) {
	// Premium package only. The module checks the license itself and stays off without one.
	require_once FAST_INDEX_PREMIUM_LOADER;
}
