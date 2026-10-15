<?php
/**
 * Multisite: network activation, new sites, per-site data.
 *
 * @package FastIndex
 */

/**
 * Multisite behaviour. Skipped on single site.
 */
class Fast_Index_Test_Multisite extends Fast_Index_Test_Case {

	/**
	 * Skips on single site.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
	}

	/**
	 * Marks the plugin as network active.
	 *
	 * @return void
	 */
	private static function network_activate() {
		update_site_option( 'active_sitewide_plugins', array( plugin_basename( FAST_INDEX_FILE ) => time() ) );
	}

	/**
	 * A site created after network activation is set up at once.
	 *
	 * @return void
	 */
	public function test_new_site_is_set_up_when_network_active() {
		self::network_activate();

		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );
		$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
		restore_current_blog();
	}

	/**
	 * A site added in Network Admin (wpmu_create_blog() keeps wp_installing() on) is set up too.
	 *
	 * @return void
	 */
	public function test_site_added_in_network_admin_is_set_up() {
		self::network_activate();

		$blog_id = wpmu_create_blog( WP_TESTS_DOMAIN, '/fast-index-network-admin/', 'Network admin site', 1 );
		wp_installing( false );

		$this->assertIsInt( $blog_id );

		switch_to_blog( $blog_id );
		$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
		restore_current_blog();
	}

	/**
	 * Without network activation a new site is left alone.
	 *
	 * @return void
	 */
	public function test_new_site_is_untouched_when_not_network_active() {
		update_site_option( 'active_sitewide_plugins', array() );

		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );
		$this->assertFalse( get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
		restore_current_blog();
	}

	/**
	 * Network activation sets up every site and migrates each site's legacy data.
	 *
	 * @return void
	 */
	public function test_network_activation_sets_up_every_site() {
		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );
		delete_option( Fast_Index_Installer::DB_VERSION_OPTION );
		update_option( Fast_Index_Settings::OPTION, array( 'status' => '2' ) );
		restore_current_blog();

		self::network_activate();
		Fast_Index_Installer::activate( true );

		foreach ( array( get_main_site_id(), $blog_id ) as $site_id ) {
			switch_to_blog( $site_id );
			$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
			$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
			restore_current_blog();
		}

		switch_to_blog( $blog_id );
		$this->assertSame( '2', Fast_Index_Settings::get()['status'], 'Settings are kept per site.' );
		restore_current_blog();

		Fast_Index_Installer::deactivate( true );

		switch_to_blog( $blog_id );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
		$this->assertIsArray( get_option( Fast_Index_Settings::OPTION ) );
		restore_current_blog();
	}

	/**
	 * PERF-FI-09: a large network is not walked site by site in the deactivation request.
	 *
	 * @return void
	 */
	public function test_large_network_deactivation_does_not_walk_the_sites() {
		$blog_id = self::factory()->blog->create();

		self::network_activate();
		Fast_Index_Installer::activate( true );

		add_filter( 'wp_is_large_network', '__return_true' );
		$switched = 0;
		$count    = function () use ( &$switched ) {
			++$switched;
		};
		add_action( 'switch_blog', $count );

		Fast_Index_Installer::deactivate( true );

		remove_action( 'switch_blog', $count );
		remove_filter( 'wp_is_large_network', '__return_true' );

		$this->assertSame( 0, $switched );
		switch_to_blog( $blog_id );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ), 'Left for the uninstall routine.' );
		restore_current_blog();
	}

	/**
	 * Keys and settings of one site are invisible to another.
	 *
	 * @return void
	 */
	public function test_data_is_per_site() {
		$blog_id = self::factory()->blog->create();

		$this->add_account();
		Fast_Index_Accounts::set_status( Fast_Index_Accounts::id_for( 'indexer@example-project.iam.gserviceaccount.com' ), 429 );

		switch_to_blog( $blog_id );
		$this->assertSame( array(), Fast_Index_Accounts::all() );
		$this->add_account();
		$this->assertCount( 1, Fast_Index_Accounts::available(), 'A resting account on one site does not rest on another.' );
		restore_current_blog();
	}

	/**
	 * The network sweep upgrades sites that never got a request.
	 *
	 * @return void
	 */
	public function test_network_sweep_upgrades_quiet_sites() {
		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );
		delete_option( Fast_Index_Installer::DB_VERSION_OPTION );
		restore_current_blog();

		self::network_activate();
		delete_site_option( Fast_Index_Installer::NETWORK_OPTION );

		Fast_Index_Installer::upgrade_network_batch();

		switch_to_blog( $blog_id );
		$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
		restore_current_blog();

		$state = get_site_option( Fast_Index_Installer::NETWORK_OPTION );
		$this->assertTrue( $state['done'] );
	}
}
