<?php
/**
 * Plugin Name: Fast Index QA premium license
 * Description: Local QA only. Loads the premium module of Fast Index as a valid Freemius license would, so the end-to-end tests can check the premium package without a license. Copy into wp-content/mu-plugins only while the premium package is tested, and remove it afterwards.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots the premium module when its folder exists and it did not load (no license on the QA site).
 *
 * @return void
 */
function fast_index_qa_premium_license() {
	if ( class_exists( 'Fast_Index_Premium' ) && class_exists( 'Fast_Index_Plugin' ) && ! Fast_Index_Plugin::premium_loaded() ) {
		Fast_Index_Premium::boot();
	}
}
add_action( 'plugins_loaded', 'fast_index_qa_premium_license', 0 );
