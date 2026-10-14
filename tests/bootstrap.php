<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite and the plugin.
 *
 * Run with WP_TESTS_DIR pointing at the WordPress test library; WP_MULTISITE=1 runs the
 * multisite round.
 *
 * @package FastIndex
 */

$fast_index_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $fast_index_tests_dir ) {
	$fast_index_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constant name is defined by the WordPress test suite.
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $fast_index_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin as a must-use plugin.
 *
 * @return void
 */
function fast_index_tests_load_plugin() {
	require dirname( __DIR__ ) . '/index.php';
}
tests_add_filter( 'muplugins_loaded', 'fast_index_tests_load_plugin' );

/**
 * Blocks every outgoing HTTP request that a test did not mock: no real call ever reaches Google or Freemius.
 *
 * Runs last, so a test's own pre_http_request mock answers first.
 *
 * @param false|array|WP_Error $response Short-circuit value.
 * @param array                $args     Request arguments.
 * @param string               $url      Request URL.
 * @return false|array|WP_Error
 */
function fast_index_tests_block_http( $response, $args, $url ) {
	if ( false !== $response ) {
		return $response;
	}

	return new WP_Error( 'fast_index_tests_http_blocked', 'Blocked in tests: ' . $url );
}
tests_add_filter( 'pre_http_request', 'fast_index_tests_block_http', PHP_INT_MAX, 3 );

require $fast_index_tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/class-fast-index-test-redirect.php';
require_once __DIR__ . '/class-fast-index-test-freemius.php';
require_once __DIR__ . '/class-fast-index-test-case.php';
