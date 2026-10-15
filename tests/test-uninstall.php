<?php
/**
 * Uninstall routine: data is kept by default and removed only on request.
 *
 * @package FastIndex
 */

/**
 * Uninstall routine.
 */
class Fast_Index_Test_Uninstall extends Fast_Index_Test_Case {

	/**
	 * Fills the current site with plugin data.
	 *
	 * @param bool $delete_data The "delete data" preference.
	 * @return int Log entry ID.
	 */
	private function seed( $delete_data ) {
		$settings                = Fast_Index_Settings::defaults();
		$settings['delete_data'] = $delete_data;

		update_option( Fast_Index_Settings::OPTION, $settings );
		update_option( Fast_Index_Installer::DB_VERSION_OPTION, FAST_INDEX_DB_VERSION );
		Fast_Index_Notices::flag_key_rotation();
		$this->add_account();
		Fast_Index_Accounts::set_status( Fast_Index_Accounts::id_for( 'indexer@example-project.iam.gserviceaccount.com' ), 429 );
		set_transient( 'fast_index_token_test', 'secret-token', HOUR_IN_SECONDS );
		Fast_Index_Cron::schedule();
		wp_schedule_event( time(), 'hourly', 'fiDailyCronHook' );

		return Fast_Index_Log::add( self::factory()->post->create(), 'https://example.org/a/', 200, 'URL_UPDATED' );
	}

	/**
	 * Default: settings, keys and log stay; events, transients and locks go.
	 *
	 * @return void
	 */
	public function test_uninstall_keeps_data_by_default() {
		$log_id = $this->seed( false );
		Fast_Index_Queue::add( 'https://example.org/queued/', 'URL_UPDATED', 0 );

		Fast_Index_Uninstaller::run();
		wp_cache_flush();

		$this->assertIsArray( get_option( Fast_Index_Settings::OPTION ) );
		$this->assertCount( 1, Fast_Index_Accounts::all() );
		$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
		$this->assertNotNull( get_post( $log_id ) );

		$this->assertFalse( get_transient( 'fast_index_token_test' ) );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
		$this->assertFalse( wp_next_scheduled( 'fiDailyCronHook' ) );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );
		$this->assertSame( 0, Fast_Index_Queue::count(), 'The queue is temporary data.' );
	}

	/**
	 * With "delete data" on, everything of the plugin is removed.
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_everything_on_request() {
		$log_id = $this->seed( true );

		Fast_Index_Uninstaller::run();
		wp_cache_flush();

		foreach ( Fast_Index_Uninstaller::options() as $option ) {
			$this->assertFalse( get_option( $option ), $option . ' must be deleted.' );
		}

		$this->assertNull( get_post( $log_id ) );
		$this->assertSame( '', get_post_meta( $log_id, Fast_Index_Log::META_CODE, true ) );
		$this->assertFalse( get_transient( 'fast_index_token_test' ) );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
	}

	/**
	 * On multisite every site is cleaned with its own preference.
	 *
	 * @return void
	 */
	public function test_uninstall_walks_every_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$keep   = self::factory()->blog->create();
		$delete = self::factory()->blog->create();

		switch_to_blog( $keep );
		$this->seed( false );
		restore_current_blog();

		switch_to_blog( $delete );
		$this->seed( true );
		restore_current_blog();

		update_site_option( Fast_Index_Installer::NETWORK_OPTION, array( 'version' => FAST_INDEX_DB_VERSION ) );

		Fast_Index_Uninstaller::run();
		wp_cache_flush();

		switch_to_blog( $keep );
		$this->assertIsArray( get_option( Fast_Index_Settings::OPTION ) );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
		restore_current_blog();

		switch_to_blog( $delete );
		$this->assertFalse( get_option( Fast_Index_Settings::OPTION ) );
		$this->assertFalse( get_option( Fast_Index_Accounts::OPTION ) );
		restore_current_blog();

		$this->assertFalse( get_site_option( Fast_Index_Installer::NETWORK_OPTION ) );
	}

	/**
	 * There is no uninstall.php (it would keep WordPress from calling the Freemius uninstall hook). The routine is
	 * attached to the Freemius after_uninstall action, to the core uninstall action of the plugin file as a fallback,
	 * and registered as the uninstall callback on activation; it runs once per request.
	 *
	 * @return void
	 */
	public function test_uninstall_runs_once_through_the_hooks() {
		$this->assertFileDoesNotExist( dirname( __DIR__ ) . '/uninstall.php' );

		$basename = plugin_basename( FAST_INDEX_FILE );
		$callback = array( 'Fast_Index_Uninstaller', 'run_once' );

		$this->assertSame( 20, has_action( 'uninstall_' . $basename, $callback ) );
		$this->assertNotFalse( figi_fs()->has_filter( 'after_uninstall', $callback ) );

		delete_option( 'uninstall_plugins' );
		Fast_Index_Installer::activate( false );
		$registered = (array) get_option( 'uninstall_plugins' );
		$this->assertSame( $callback, $registered[ $basename ] );

		$this->seed( true );
		$reflection = new ReflectionProperty( 'Fast_Index_Uninstaller', 'done' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, false );

		do_action( 'uninstall_' . $basename ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook that WordPress fires when the plugin is deleted.
		wp_cache_flush();
		$this->assertFalse( get_option( Fast_Index_Settings::OPTION ) );

		// A second call in the same request does nothing.
		$this->seed( true );
		Fast_Index_Uninstaller::run_once();
		wp_cache_flush();
		$this->assertIsArray( get_option( Fast_Index_Settings::OPTION ) );

		$reflection->setValue( null, false );
	}

	/**
	 * Deleting one copy while the other one (free or premium package) is active removes nothing: both use the same
	 * data.
	 *
	 * @return void
	 */
	public function test_uninstall_waits_while_the_other_copy_is_active() {
		$this->seed( true );
		$reflection = new ReflectionProperty( 'Fast_Index_Uninstaller', 'done' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, false );

		$active = get_option( 'active_plugins' );
		update_option( 'active_plugins', array( 'fast-index-premium/index.php' ) );

		$this->assertTrue( Fast_Index_Uninstaller::other_copy_is_active() );
		Fast_Index_Uninstaller::run_once();
		wp_cache_flush();

		$this->assertIsArray( get_option( Fast_Index_Settings::OPTION ) );
		$this->assertCount( 1, Fast_Index_Accounts::all() );

		update_option( 'active_plugins', $active );
		$reflection->setValue( null, false );
	}

	/**
	 * The dismissed notices of the users go with the data; they stay while data is kept.
	 *
	 * @return void
	 */
	public function test_user_notice_record_follows_the_data() {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->seed( false );
		Fast_Index_Upgrade::dismiss( $user, Fast_Index_Upgrade::NOTICE_OFFER );
		Fast_Index_Uninstaller::run();
		$this->assertNotSame( array(), Fast_Index_Upgrade::dismissed( $user ) );

		if ( ! is_multisite() ) {
			$this->seed( true );
			update_option( Fast_Index_Upgrade::FIRST_USE_OPTION, time() );
			Fast_Index_Uninstaller::run();
			wp_cache_flush();
			$this->assertSame( array(), Fast_Index_Upgrade::dismissed( $user ) );
			$this->assertFalse( get_option( Fast_Index_Upgrade::FIRST_USE_OPTION ) );
		}
	}
}
