<?php
/**
 * Upgrade from 2.x: key files in uploads move into the database and are deleted.
 *
 * @package FastIndex
 */

/**
 * Migration and upgrade routine.
 */
class Fast_Index_Test_Migration extends Fast_Index_Test_Case {

	/**
	 * Files created by a test.
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * Removes left-over fixture files.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}

		parent::tear_down();
	}

	/**
	 * Writes a key file where 2.x wrote it (uploads/YYYY/MM) and returns its path.
	 *
	 * @param string $content File content.
	 * @param string $name    File name.
	 * @return string
	 */
	private function legacy_file( $content, $name = 'service-account.json' ) {
		$uploads = wp_upload_dir( '2026/10' );

		wp_mkdir_p( $uploads['path'] );

		$path          = trailingslashit( $uploads['path'] ) . wp_unique_filename( $uploads['path'], $name );
		$this->files[] = $path;

		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture, like the file 2.x wrote.

		return $path;
	}

	/**
	 * Stores the option shape of 2.x.
	 *
	 * @param array $json_files Value of json_file.
	 * @return void
	 */
	private static function legacy_option( array $json_files ) {
		global $wpdb;

		$value = array(
			'status'           => '1',
			'post_type'        => array(
				'post' => '1',
				'page' => '1',
			),
			'old_post_number'  => '25',
			'post_status'      => array(
				'publish' => '1',
				'edit'    => '1',
			),
			'exclude_category' => array( 4 => '4' ),
			'json_file'        => $json_files,
		);

		// Written without the 3.x sanitizer, exactly as 2.x stored it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test fixture; the cache is flushed below.
		$wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => Fast_Index_Settings::OPTION,
				'option_value' => maybe_serialize( $value ),
				'autoload'     => 'yes',
			)
		);

		delete_option( Fast_Index_Installer::DB_VERSION_OPTION );
		wp_cache_flush();
	}

	/**
	 * Runs the upgrade like the next request after the update.
	 *
	 * @return void
	 */
	private static function upgrade() {
		Fast_Index_Installer::maybe_upgrade();
		wp_cache_flush();
	}

	/**
	 * The key moves into the non-autoloaded option, the file is deleted, settings stay.
	 *
	 * @return void
	 */
	public function test_key_file_is_moved_and_deleted() {
		$email = 'indexer@example-project.iam.gserviceaccount.com';
		$path  = $this->legacy_file( self::key_json( $email ) );

		self::legacy_option(
			array(
				md5( $email ) => array(
					'file'   => $path,
					'status' => 200,
					'mail'   => $email,
				),
			)
		);
		wp_schedule_event( time(), 'hourly', 'fiDailyCronHook' );

		self::upgrade();

		$this->assertFileDoesNotExist( $path );

		$accounts = Fast_Index_Accounts::all();
		$this->assertCount( 1, $accounts );
		$account = reset( $accounts );
		$this->assertSame( $email, $account['client_email'] );
		$this->assertSame( trim( self::private_key() ), $account['private_key'] );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the autoload column, which get_option() does not expose.
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Fast_Index_Accounts::OPTION ) );
		$this->assertContains( $autoload, array( 'no', 'off' ) );

		$settings = get_option( Fast_Index_Settings::OPTION );
		$this->assertArrayNotHasKey( 'json_file', $settings );
		$this->assertSame(
			array(
				'post' => '1',
				'page' => '1',
			),
			$settings['post_type']
		);
		$this->assertSame( 25, $settings['old_post_number'] );
		$this->assertSame( array( 4 => 4 ), $settings['exclude_category'] );

		$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
		$this->assertSame( '1', get_option( Fast_Index_Notices::KEY_ROTATION_OPTION ) );
		$this->assertSame( array(), Fast_Index_Notices::issues() );
		$this->assertFalse( wp_next_scheduled( 'fiDailyCronHook' ) );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
	}

	/**
	 * Running the migration again changes nothing and reports nothing.
	 *
	 * @return void
	 */
	public function test_migration_is_idempotent() {
		$email = 'indexer@example-project.iam.gserviceaccount.com';
		$path  = $this->legacy_file( self::key_json( $email ) );
		$entry = array(
			'file'   => $path,
			'status' => 200,
			'mail'   => $email,
		);

		self::legacy_option( array( md5( $email ) => $entry ) );
		self::upgrade();

		$accounts = Fast_Index_Accounts::all();

		// An interrupted run would see the same entry again: the stored key counts as migrated.
		$this->assertNull( Fast_Index_Migration::migrate_entry( $entry ) );
		$this->assertTrue( Fast_Index_Migration::to_3_0_0() );
		self::upgrade();

		$this->assertSame( $accounts, Fast_Index_Accounts::all() );
		$this->assertSame( array(), Fast_Index_Notices::issues() );
	}

	/**
	 * Problems are reported to the admin; files that cannot be verified are not touched.
	 *
	 * @return void
	 */
	public function test_failures_are_reported() {
		$invalid = $this->legacy_file( '{"type":"authorized_user"}', 'not-a-key.json' );
		$outside = wp_tempnam( 'outside.json' );

		$this->files[] = $outside;

		file_put_contents( $outside, self::key_json() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$uploads = wp_upload_dir();

		self::legacy_option(
			array(
				'a' => array(
					'file' => trailingslashit( $uploads['basedir'] ) . '2026/10/gone.json',
					'mail' => 'gone@example-project.iam.gserviceaccount.com',
				),
				'b' => array(
					'file' => $invalid,
					'mail' => 'invalid@example-project.iam.gserviceaccount.com',
				),
				'c' => array(
					'file' => $outside,
					'mail' => 'outside@example-project.iam.gserviceaccount.com',
				),
				'd' => array(
					'file' => trailingslashit( $uploads['basedir'] ) . '../wp-config.php',
					'mail' => 'traversal@example-project.iam.gserviceaccount.com',
				),
			)
		);

		self::upgrade();

		$codes = wp_list_pluck( Fast_Index_Notices::issues(), 'code' );
		sort( $codes );

		$this->assertSame( array( 'invalid', 'missing', 'outside_uploads', 'outside_uploads' ), $codes );
		$this->assertFileExists( $invalid, 'An unverified file is never deleted.' );
		$this->assertFileExists( $outside, 'A file outside uploads is never touched.' );
		$this->assertSame( array(), Fast_Index_Accounts::all() );
		$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
	}

	/**
	 * A new install gets the version and the event, but no notice.
	 *
	 * @return void
	 */
	public function test_new_install() {
		delete_option( Fast_Index_Settings::OPTION );
		delete_option( Fast_Index_Installer::DB_VERSION_OPTION );

		self::upgrade();

		$this->assertSame( FAST_INDEX_DB_VERSION, get_option( Fast_Index_Installer::DB_VERSION_OPTION ) );
		$this->assertFalse( get_option( Fast_Index_Notices::KEY_ROTATION_OPTION ) );
		$this->assertFalse( get_option( Fast_Index_Settings::OPTION ) );
	}

	/**
	 * Deactivation removes scheduled events only and keeps all data.
	 *
	 * @return void
	 */
	public function test_deactivation_keeps_data() {
		Fast_Index_Installer::activate( false );
		$this->add_account();
		update_option( Fast_Index_Settings::OPTION, Fast_Index_Settings::defaults() );
		Fast_Index_Cron::queue_submission( 'https://example.org/a/', 'URL_UPDATED', 1 );
		wp_schedule_event( time(), 'hourly', 'fiDailyCronHook' );

		Fast_Index_Installer::deactivate( false );

		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
		$this->assertFalse( wp_next_scheduled( 'fiDailyCronHook' ) );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );
		$this->assertSame( 1, Fast_Index_Queue::count(), 'Deactivation keeps the queue.' );
		$this->assertCount( 1, Fast_Index_Accounts::all() );
		$this->assertIsArray( get_option( Fast_Index_Settings::OPTION ) );

		Fast_Index_Installer::activate( false );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::HOURLY_HOOK ) );
	}
}
