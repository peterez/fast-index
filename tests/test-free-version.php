<?php
/**
 * The free version: no premium code at work, no lock, FI-043, kept premium data, Freemius settings.
 *
 * @package FastIndex
 */

/**
 * What the WordPress.org package does without the premium module.
 */
class Fast_Index_Test_Free_Version extends Fast_Index_Test_Case {

	/**
	 * Hooks of the free version that only the premium module uses.
	 *
	 * @return string[]
	 */
	private static function extension_hooks() {
		return array(
			'fast_index_post_type_choices',
			'fast_index_post_events',
			'fast_index_default_settings',
			'fast_index_use_several_keys',
			'fast_index_unused_settings',
			'fast_index_should_notify',
			'fast_index_sanitize_settings',
			'fast_index_validate_settings',
			'fast_index_settings_rows',
			'fast_index_hourly',
			'fast_index_admin_menu',
			'fast_index_history_stats',
		);
	}

	/**
	 * Without the module nothing is attached to the extension hooks, and the module stays off without a license.
	 *
	 * @return void
	 */
	public function test_extension_hooks_are_empty_without_the_module() {
		foreach ( self::extension_hooks() as $hook ) {
			$this->assertFalse( has_filter( $hook ), $hook );
		}

		$this->assertFalse( Fast_Index_Plugin::premium_loaded() );

		$this->fake_freemius( false );
		$this->assertFalse( Fast_Index_Premium::maybe_boot(), 'No license: the module does not load.' );
		$this->assertFalse( Fast_Index_Plugin::premium_loaded() );

		foreach ( self::extension_hooks() as $hook ) {
			$this->assertFalse( has_filter( $hook ), $hook );
		}

		$this->assertSame( array( 'publish', 'edit', 'trash' ), array_keys( Fast_Index_Settings::event_choices() ) );
		$this->assertContains( 'page', array_keys( Fast_Index_Settings::post_type_choices() ) );
		$this->assertFalse( Fast_Index_Accounts::several_keys() );
	}

	/**
	 * The code of the free version has no license or plan check and no locked control.
	 *
	 * @return void
	 */
	public function test_free_code_has_no_license_check_or_lock() {
		$root  = dirname( __DIR__ );
		$files = array_merge(
			array( $root . '/index.php' ),
			glob( $root . '/includes/*.php' ),
			glob( $root . '/view/*.php' ),
			glob( $root . '/assets/*.js' ),
			glob( $root . '/assets/*.css' )
		);

		$this->assertGreaterThan( 25, count( $files ) );

		foreach ( $files as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local file in a test.

			foreach ( array( 'can_use_premium_code', 'is_paying', 'is__premium_only', 'is_plan', 'has_active_valid_license', 'apply_plan_limits', 'licenceAlert', 'upgradeMessage', 'disabled />' ) as $needle ) {
				$this->assertStringNotContainsString( $needle, $code, basename( $file ) . ': ' . $needle );
			}
		}
	}

	/**
	 * The settings screen: only free options, no locked or disabled control, one Premium box with the Freemius
	 * upgrade address, no premium sentence under a field.
	 *
	 * @return void
	 */
	public function test_settings_screen_shows_one_premium_box_and_no_lock() {
		$this->fake_freemius( false );
		$this->login_as( 'administrator' );
		self::factory()->category->create( array( 'name' => 'Hidden category' ) );

		ob_start();
		( new Fast_Index_Admin() )->render_settings();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, 'class="fi-premium-card"' ) );
		$this->assertStringContainsString( esc_url( admin_url( 'admin.php?page=fast-index-pricing' ) ), $html );
		$this->assertSame( count( Fast_Index_Settings::post_type_choices() ), preg_match_all( '/name="fast_index_options\[post_type\]\[/', $html ) );
		$this->assertStringContainsString( 'name="fast_index_options[post_type][page]"', $html );
		$this->assertSame( 3, preg_match_all( '/name="fast_index_options\[post_status\]\[/', $html ) );
		$this->assertStringContainsString( 'name="fast_index_options[post_status][trash]"', $html );
		$this->assertStringNotContainsString( 'old_post_number', $html );
		$this->assertStringNotContainsString( 'exclude_category', $html );
		$this->assertStringNotContainsString( 'Hidden category', $html );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]*\b(disabled|readonly)\b/', $html );
		$this->assertStringNotContainsString( 'licenceAlert', $html );
		$this->assertStringNotContainsString( 'multiple', $html );
		$this->assertStringNotContainsString( 'fi-unused', $html, 'Nothing stored is unused on a new site.' );
		$this->assertSame( 1, substr_count( strtolower( wp_strip_all_tags( $html ) ), 'upgrade to premium' ) );
	}

	/**
	 * The menu of the free version has no "Send Old Posts Now" page.
	 *
	 * @return void
	 */
	public function test_menu_has_no_premium_page() {
		global $menu, $submenu;

		$this->login_as( 'administrator' );
		$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Earlier tests filled the global menu.
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same.
		( new Fast_Index_Admin() )->add_menu();

		$this->assertSame(
			array( Fast_Index_Admin::SLUG, Fast_Index_Admin::SLUG_HISTORY, Fast_Index_Help::SLUG ),
			wp_list_pluck( $submenu[ Fast_Index_Admin::SLUG ], 2 )
		);
	}

	/**
	 * FI-R02 (reverses the FI-043 test of the first premium split): the free version sends pages and custom post
	 * types, and the Publish, Edit and Trash events, including a deletion without the trash. Each event queues one
	 * notification; a post deleted from the trash sends nothing more.
	 *
	 * @return void
	 */
	public function test_free_version_sends_edits_trash_deletion_and_pages() {
		register_post_type(
			'fi_job',
			array(
				'public' => true,
				'label'  => 'Jobs',
			)
		);

		// Defaults (never saved): Publish and Edit of Posts.
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$url     = get_permalink( $post_id );
		$this->assertSame( array( array( $url, 'URL_UPDATED', $post_id ) ), self::queued(), 'Publish is sent.' );
		self::clear_queue();

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Changed.',
			)
		);
		$this->assertSame( array( array( $url, 'URL_UPDATED', $post_id ) ), self::queued(), 'Edit is sent.' );
		self::clear_queue();

		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'   => array(
					'post'   => '1',
					'page'   => '1',
					'fi_job' => '1',
				),
				'post_status' => array(
					'publish' => '1',
					'edit'    => '1',
					'trash'   => '1',
				),
			)
		);

		$page     = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'about-us',
			)
		);
		$page_url = get_permalink( $page );
		$this->assertSame( array( array( $page_url, 'URL_UPDATED', $page ) ), self::queued(), 'A page is sent.' );
		self::clear_queue();

		$job = self::factory()->post->create(
			array(
				'post_type'   => 'fi_job',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( array( array( get_permalink( $job ), 'URL_UPDATED', $job ) ), self::queued(), 'A custom post type is sent.' );
		self::clear_queue();

		wp_trash_post( $page );
		$this->assertStringNotContainsString( '__trashed', $page_url );
		$this->assertSame( array( array( $page_url, 'URL_DELETED', $page ) ), self::queued(), 'Trash sends the old address.' );
		self::clear_queue();

		wp_delete_post( $page, true );
		$this->assertSame( array(), self::queued(), 'Deleting from the trash sends nothing more.' );

		wp_delete_post( $post_id, true );
		$this->assertSame( array( array( $url, 'URL_DELETED', $post_id ) ), self::queued(), 'Deleting a published post without the trash is sent.' );
		self::clear_queue();

		// Unchecked events and draft posts are not sent.
		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'   => array( 'post' => '1' ),
				'post_status' => array( 'publish' => '1' ),
			)
		);
		$other = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::clear_queue();
		wp_update_post(
			array(
				'ID'           => $other,
				'post_content' => 'Changed.',
			)
		);
		wp_trash_post( $other );
		self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$this->assertSame( array(), self::queued() );

		unregister_post_type( 'fi_job' );
	}

	/**
	 * Arguments of the queued notification events.
	 *
	 * @return array[]
	 */
	private static function queued() {
		$events = array();

		foreach ( (array) _get_cron_array() as $hooks ) {
			if ( isset( $hooks[ Fast_Index_Cron::SUBMIT_HOOK ] ) ) {
				foreach ( $hooks[ Fast_Index_Cron::SUBMIT_HOOK ] as $event ) {
					$events[] = $event['args'];
				}
			}
		}

		return $events;
	}

	/**
	 * Removes every queued notification event.
	 *
	 * @return void
	 */
	private static function clear_queue() {
		wp_unschedule_hook( Fast_Index_Cron::SUBMIT_HOOK );
	}

	/**
	 * Keys stored by the premium plan are kept; the first one is used and named on the screen. A new upload
	 * replaces only the key in use.
	 *
	 * @return void
	 */
	public function test_several_stored_keys_are_kept_and_the_first_is_used() {
		$first  = $this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$second = $this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );
		$third  = $this->add_account( 'three@example-project.iam.gserviceaccount.com', 'three' );

		$this->assertSame( array( $first ), array_keys( Fast_Index_Accounts::in_use() ) );
		$this->assertContains( 'Service account keys after the first one: 2', Fast_Index_Settings::unused( Fast_Index_Settings::get() ) );

		$this->login_as( 'administrator' );
		ob_start();
		( new Fast_Index_Admin() )->render_settings();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, 'Used for sending' ) );
		$this->assertSame( 2, substr_count( $html, 'Kept, not used by this version' ) );
		$this->assertStringContainsString( 'fi-unused', $html );
		$this->assertStringContainsString( 'one@example-project.iam.gserviceaccount.com', $html );

		$new = Fast_Index_Accounts::use_key( Fast_Index_Accounts::parse_key_json( self::key_json( 'four@example-project.iam.gserviceaccount.com', 'four' ) ) );

		$this->assertSame( array( $new, $second, $third ), array_keys( Fast_Index_Accounts::all() ), 'The new key replaces the key in use; the others stay.' );
		$this->assertSame( array( $new ), array_keys( Fast_Index_Accounts::in_use() ) );

		// With one stored key, an upload replaces it (2.x behaviour).
		Fast_Index_Accounts::delete( $second );
		Fast_Index_Accounts::delete( $third );
		$again = Fast_Index_Accounts::use_key( Fast_Index_Accounts::parse_key_json( self::key_json( 'five@example-project.iam.gserviceaccount.com', 'five' ) ) );
		$this->assertSame( array( $again ), array_keys( Fast_Index_Accounts::all() ) );

		// The premium module uses every stored key again.
		$this->add_account( 'six@example-project.iam.gserviceaccount.com', 'six' );
		$this->with_premium();
		$this->assertCount( 2, Fast_Index_Accounts::in_use() );
		$this->assertSame( array(), Fast_Index_Settings::unused( Fast_Index_Settings::get() ) );
	}

	/**
	 * Freemius settings of the WordPress.org package: not premium, no contact form (iframe), no forum link in
	 * the menu, anonymous use allowed, a premium slug that differs from the free one, a local icon.
	 *
	 * @return void
	 */
	public function test_freemius_settings_of_the_free_package() {
		$config = Fast_Index_Freemius::config( false );

		$this->assertFalse( $config['is_premium'] );
		$this->assertTrue( Fast_Index_Freemius::config( true )['is_premium'] );
		$this->assertTrue( $config['has_premium_version'] );
		$this->assertTrue( $config['has_paid_plans'] );
		$this->assertTrue( $config['is_org_compliant'] );
		$this->assertTrue( $config['enable_anonymous'] );
		$this->assertArrayNotHasKey( 'anonymous_mode', $config );
		$this->assertNotSame( $config['slug'], $config['premium_slug'] );
		$this->assertFalse( $config['menu']['contact'] );
		$this->assertFalse( $config['menu']['support'] );
		$this->assertFalse( $config['menu']['network'] );
		$this->assertSame( Fast_Index_Admin::SLUG, $config['menu']['slug'] );
		$this->assertFileExists( Fast_Index_Freemius::icon() );
		$this->assertSame( file_exists( FAST_INDEX_PREMIUM_LOADER ), Fast_Index_Freemius::has_premium_package() );
	}
}
