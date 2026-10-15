<?php
/**
 * SEC-FI-01: the Upgrade page loads no Google Analytics.
 *
 * @package FastIndex
 */

/**
 * The guard that keeps the Google Analytics script of the Freemius pricing script off the Upgrade page.
 */
class Fast_Index_Test_Freemius_Analytics extends Fast_Index_Test_Case {

	/**
	 * Removes the fake pricing script.
	 *
	 * @return void
	 */
	public function tear_down() {
		wp_dequeue_script( 'freemius-pricing' );
		wp_deregister_script( 'freemius-pricing' );
		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Registers and enqueues a stand-in for the pricing script of the SDK.
	 *
	 * @return void
	 */
	private static function enqueue_pricing_script() {
		wp_register_script( 'freemius-pricing', 'https://example.org/freemius-pricing.js', array(), '1', true );
		wp_enqueue_script( 'freemius-pricing' );
	}

	/**
	 * The inline script printed before the pricing script.
	 *
	 * @return string
	 */
	private static function before() {
		return implode( '', (array) wp_scripts()->get_data( 'freemius-pricing', 'before' ) );
	}

	/**
	 * The guard is attached to the footer scripts, before WordPress prints them.
	 *
	 * @return void
	 */
	public function test_guard_is_attached_before_the_footer_scripts() {
		$this->assertSame( 1, has_action( 'admin_print_footer_scripts', array( 'Fast_Index_Freemius', 'block_pricing_analytics' ) ) );
	}

	/**
	 * The Upgrade page of Fast Index gets the guard, in the site and in the network admin.
	 *
	 * @return void
	 */
	public function test_upgrade_page_gets_the_guard() {
		$hook = get_plugin_page_hookname( Fast_Index_Freemius::PRICING_SLUG, Fast_Index_Admin::SLUG );

		foreach ( array( $hook, $hook . '-network' ) as $screen ) {
			self::enqueue_pricing_script();
			set_current_screen( $screen );
			Fast_Index_Freemius::block_pricing_analytics();

			$before = self::before();
			$this->assertStringContainsString( 'google-analytics', $before, $screen );
			$this->assertStringContainsString( 'insertBefore', $before, $screen );

			wp_dequeue_script( 'freemius-pricing' );
			wp_deregister_script( 'freemius-pricing' );
		}
	}

	/**
	 * Other screens, and the Upgrade page of another plugin, keep their scripts as they are.
	 *
	 * @return void
	 */
	public function test_other_screens_are_left_alone() {
		self::enqueue_pricing_script();

		foreach ( array( 'dashboard', get_plugin_page_hookname( 'other-plugin-pricing', 'other-plugin' ), get_plugin_page_hookname( Fast_Index_Admin::SLUG, '' ) ) as $screen ) {
			set_current_screen( $screen );
			Fast_Index_Freemius::block_pricing_analytics();
			$this->assertSame( '', self::before(), $screen );
		}
	}

	/**
	 * Without the pricing script on the page nothing is added.
	 *
	 * @return void
	 */
	public function test_nothing_without_the_pricing_script() {
		set_current_screen( get_plugin_page_hookname( Fast_Index_Freemius::PRICING_SLUG, Fast_Index_Admin::SLUG ) );
		Fast_Index_Freemius::block_pricing_analytics();

		$this->assertFalse( wp_script_is( 'freemius-pricing', 'registered' ) );
	}

	/**
	 * The guard stops script elements of google-analytics.com and its subdomains only.
	 *
	 * @return void
	 */
	public function test_guard_matches_google_analytics_only() {
		$guard = Fast_Index_Freemius::analytics_guard();

		$this->assertStringContainsString( '/(^|\\.)google-analytics\\.com$/', $guard );
		$this->assertStringContainsString( '"SCRIPT" === node.nodeName', $guard );
		$this->assertStringContainsString( 'return insert.call( this, node, child );', $guard );
	}

	/**
	 * The bundled pricing script still contains the code the guard stops. When an SDK update removes it, this test
	 * fails and the guard can go.
	 *
	 * @return void
	 */
	public function test_bundled_pricing_script_still_loads_google_analytics() {
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/freemius-sdk/assets/js/pricing/freemius-pricing.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local file in a test.

		$this->assertStringContainsString( '//www.google-analytics.com/analytics.js', $script );
		$this->assertStringContainsString( '.insertBefore(r,i)', $script );
	}
}
