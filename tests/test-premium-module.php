<?php
/**
 * The premium module with a license (Freemius replaced by a test double).
 *
 * @package FastIndex
 */

/**
 * Premium behaviour: loading, events, post types, keys, old posts and its screen.
 */
class Fast_Index_Test_Premium_Module extends Fast_Index_Test_Case {

	/**
	 * The module loads only when Freemius allows the premium code, and then attaches to the free hooks.
	 *
	 * @return void
	 */
	public function test_module_loads_only_with_a_license() {
		$this->fake_freemius( false );
		$this->assertFalse( Fast_Index_Premium::maybe_boot() );
		$this->assertFalse( has_filter( 'fast_index_post_events' ) );

		$this->with_premium();

		$this->assertTrue( Fast_Index_Plugin::premium_loaded() );
		$this->assertNotFalse( has_filter( 'fast_index_post_events' ) );
		$this->assertNotFalse( has_action( 'fast_index_hourly' ) );
		$this->assertTrue( Fast_Index_Accounts::several_keys() );
		$this->assertSame( array( 'publish', 'edit', 'trash' ), array_keys( Fast_Index_Settings::event_choices() ) );
		$this->assertContains( 'page', array_keys( Fast_Index_Settings::post_type_choices() ) );
		$this->assertNotContains( 'attachment', array_keys( Fast_Index_Settings::post_type_choices() ) );
	}

	/**
	 * Edit and other post types are sent; the settings screen has the premium fields and no Premium box.
	 *
	 * @return void
	 */
	public function test_edits_and_other_post_types_are_sent() {
		$this->with_premium();

		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'   => array(
					'post' => '1',
					'page' => '1',
				),
				'post_status' => array(
					'publish' => '1',
					'edit'    => '1',
				),
			)
		);

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$url     = get_permalink( $post_id );
		wp_clear_scheduled_hook( Fast_Index_Cron::SUBMIT_HOOK, array( $url, 'URL_UPDATED', $post_id ) );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Changed.',
			)
		);
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, array( $url, 'URL_UPDATED', $post_id ) ), 'Edit is sent.' );

		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, array( get_permalink( $page ), 'URL_UPDATED', $page ) ), 'Pages are sent.' );

		$this->login_as( 'administrator' );
		ob_start();
		( new Fast_Index_Admin() )->render_settings();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="fast_index_options[old_post_number]"', $html );
		$this->assertStringContainsString( 'name="fast_index_options[post_status][trash]"', $html );
		$this->assertStringContainsString( 'name="fast_index_options[post_type][page]"', $html );
		$this->assertStringContainsString( 'multiple', $html );
		$this->assertStringNotContainsString( 'fi-premium-card', $html );
		$this->assertStringNotContainsString( 'fi-unused', $html );
	}

	/**
	 * Settings saved by the premium form are read back; values outside the range are rejected.
	 *
	 * @return void
	 */
	public function test_premium_fields_are_saved_and_validated() {
		$this->with_premium();
		$this->catch_redirects();
		$this->login_as( 'administrator' );

		$category = self::factory()->category->create();

		self::set_nonce( Fast_Index_Admin_Actions::SAVE );
		$_POST['fast_index_options'] = array(
			'status'           => '1',
			'post_type'        => array( 'post' => '1' ),
			'post_status'      => array( 'trash' => '1' ),
			'old_post_number'  => '25',
			'exclude_category' => array( $category => $category ),
		);

		try {
			( new Fast_Index_Admin_Actions() )->save_settings();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$saved = Fast_Index_Settings::get();
		$this->assertSame( 25, $saved['old_post_number'] );
		$this->assertSame( array( $category => $category ), $saved['exclude_category'] );
		$this->assertSame( array( 'trash' => '1' ), $saved['post_status'] );

		$errors = Fast_Index_Settings::validate_input(
			array(
				'status'          => '1',
				'post_type'       => array( 'post' => '1' ),
				'old_post_number' => '10001',
			)
		);
		$this->assertCount( 1, $errors );
	}

	/**
	 * The hourly event sends a batch of old posts within the daily limit.
	 *
	 * @return void
	 */
	public function test_hourly_event_sends_old_posts() {
		$this->with_premium();
		$this->add_account();
		$this->fake_google();

		$old = self::factory()->post->create_many( 3 );
		wp_clear_scheduled_hook( Fast_Index_Cron::SUBMIT_HOOK );
		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'       => array( 'post' => '1' ),
				'post_status'     => array( 'publish' => '1' ),
				'old_post_number' => 9,
			)
		);

		$sent = 0;

		// The batch size is random (0 to a third of the limit): run until something was sent.
		for ( $i = 0; $i < 30 && 0 === $sent; $i++ ) {
			( new Fast_Index_Cron() )->run_hourly();
			$sent = Fast_Index_Log::count_sent();
		}

		$this->assertGreaterThan( 0, $sent );
		$this->assertLessThanOrEqual( 3, $sent );
		$this->assertCount( $sent, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertSame( 3 - $sent, Fast_Index_Log::stats( Fast_Index_Settings::get() )['waiting'] );
		$this->assertCount( 3, $old );
	}

	/**
	 * The "Send Old Posts Now" page is in the menu, and its handler checks capability and nonce.
	 *
	 * @return void
	 */
	public function test_run_page_and_handler() {
		global $menu, $submenu;

		$this->with_premium();
		$this->catch_redirects();
		$this->login_as( 'administrator' );
		$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Earlier tests filled the global menu.
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same.
		( new Fast_Index_Admin() )->add_menu();

		$this->assertSame(
			array( Fast_Index_Admin::SLUG, Fast_Index_Admin::SLUG_HISTORY, Fast_Index_Premium_Backlog::PAGE_SLUG, Fast_Index_Help::SLUG ),
			wp_list_pluck( $submenu[ Fast_Index_Admin::SLUG ], 2 )
		);

		// No nonce.
		try {
			Fast_Index_Premium_Backlog::handle_run();
			$this->fail( 'The handler ran without a nonce.' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		// Valid request: the results are kept for the next view of the page.
		self::set_nonce( Fast_Index_Premium_Backlog::RUN_ACTION );

		try {
			Fast_Index_Premium_Backlog::handle_run();
			$this->fail( 'Expected a redirect.' );
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			$this->assertStringContainsString( 'page=fast-index-run', $redirect->getMessage() );
		}

		ob_start();
		Fast_Index_Premium_Backlog::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'There are no posts to send now.', $html );

		// A user without the capability is refused.
		$this->login_as( 'editor' );
		self::set_nonce( Fast_Index_Premium_Backlog::RUN_ACTION );
		$this->expectException( 'WPDieException' );
		Fast_Index_Premium_Backlog::handle_run();
	}
}
