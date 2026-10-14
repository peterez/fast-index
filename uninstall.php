<?php
/**
 * Runs when the plugin is deleted from the Plugins screen. Not run on deactivation.
 *
 * By default only temporary data (scheduled events, transients, locks) is removed and the
 * settings, keys and log are kept. When "Delete all plugin data" is on in the settings,
 * everything is removed. On multisite every site is handled.
 *
 * @package FastIndex
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-fast-index-uninstaller.php';

Fast_Index_Uninstaller::run();
