<?php
/**
 * Fast Index
 *
 * @package           FastIndex
 * @author            Samet AKIN
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Fast Index
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
 */

defined( 'ABSPATH' ) || exit;

define( 'FAST_INDEX_VERSION', '3.0.0' );
define( 'FAST_INDEX_DB_VERSION', '3.0.0' ); // Data version: raised only when stored data changes.
define( 'FAST_INDEX_FILE', __FILE__ );
define( 'FAST_INDEX_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAST_INDEX_URL', plugin_dir_url( __FILE__ ) );

require_once FAST_INDEX_PATH . 'includes/class-fast-index-settings.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-accounts.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-files.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-key-upload.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-google-client.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-log.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-submitter.php';
require_once FAST_INDEX_PATH . 'includes/class-fast-index-backlog.php';
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
require_once FAST_INDEX_PATH . 'includes/class-fast-index-plugin.php';
require_once FAST_INDEX_PATH . 'freemius-sdk/start.php';

if ( ! function_exists( 'figi_fs' ) ) {
	/**
	 * Returns the Freemius SDK instance of this plugin (unchanged from 2.x).
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

			$figi_fs = fs_dynamic_init(
				array(
					'id'              => '11893',
					'slug'            => 'fast-index',
					'premium_slug'    => 'fast-index',
					'type'            => 'plugin',
					'public_key'      => 'pk_4352cecbab080b84df64da3246477',
					'is_premium'      => true,
					'is_premium_only' => false,
					'has_addons'      => false,
					'has_paid_plans'  => true,
					'menu'            => array(
						'slug'       => 'fast-index',
						'first-path' => 'admin.php?page=fast-index',
						'contact'    => false,
						'support'    => true,
						'account'    => true,
						'network'    => true,
					),
				)
			);
		}

		return $figi_fs;
	}
}

figi_fs();
do_action( 'figi_fs_loaded' );

register_activation_hook( FAST_INDEX_FILE, array( 'Fast_Index_Installer', 'activate' ) );
register_deactivation_hook( FAST_INDEX_FILE, array( 'Fast_Index_Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( new Fast_Index_Plugin(), 'run' ) );
