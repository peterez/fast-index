<?php
/**
 * Admin screens: no secret in the output, validation errors, notices.
 *
 * @package FastIndex
 */

/**
 * Screen rendering.
 */
class Fast_Index_Test_Admin_Screens extends Fast_Index_Test_Case {

	/**
	 * Renders a screen and returns its HTML.
	 *
	 * @param string $screen settings, history or run (the premium "Send Old Posts Now" screen).
	 * @return string
	 */
	private static function render( $screen ) {
		$admin = new Fast_Index_Admin();

		ob_start();

		if ( 'settings' === $screen ) {
			$admin->render_settings();
		} elseif ( 'history' === $screen ) {
			$admin->render_history();
		} else {
			Fast_Index_Premium_Backlog::render();
		}

		return (string) ob_get_clean();
	}

	/**
	 * The settings screen shows the client email and key ID, never the private key.
	 *
	 * @return void
	 */
	public function test_settings_screen_never_prints_private_key() {
		$this->login_as( 'administrator' );
		$this->add_account();

		$html = self::render( 'settings' );

		$this->assertStringContainsString( 'indexer@example-project.iam.gserviceaccount.com', $html );
		$this->assertStringContainsString( 'abc123' . md5( 'default' ), $html );
		$this->assertStringNotContainsString( 'PRIVATE KEY', $html );
		$this->assertStringNotContainsString( substr( preg_replace( '/-----[A-Z ]+-----|\s/', '', self::private_key() ), 10, 40 ), $html );
		$this->assertStringContainsString( 'name="fast_index_nonce"', $html );
	}

	/**
	 * Screens refuse users without manage_options.
	 *
	 * @return void
	 */
	public function test_screens_require_capability() {
		$this->with_premium();
		$this->login_as( 'editor' );

		foreach ( array( 'settings', 'history', 'run' ) as $screen ) {
			try {
				self::render( $screen );
				$this->fail( $screen . ' rendered for an editor.' );
			} catch ( WPDieException $e ) {
				ob_end_clean();
				$this->assertStringContainsString( 'not allowed', $e->getMessage() );
			}
		}
	}

	/**
	 * The history screen lists entries with their status and builds page links.
	 *
	 * @return void
	 */
	public function test_history_screen() {
		$this->login_as( 'administrator' );

		$post = self::factory()->post->create( array( 'post_title' => 'Hello <b>there</b>' ) );

		for ( $i = 0; $i < Fast_Index_Log::PER_PAGE + 1; $i++ ) {
			Fast_Index_Log::add( $post, 'https://example.org/p' . $i . '/', Fast_Index_Log::PER_PAGE === $i ? 429 : 200, 'URL_UPDATED' );
		}

		$html = self::render( 'history' );

		$this->assertStringContainsString( '429 Too Many Requests', $html );
		$this->assertStringContainsString( 'page=fast-index-history&#038;pn=1', $html );
		$this->assertStringNotContainsString( '<b>there</b>', $html );
	}

	/**
	 * Premium: invalid settings are rejected with a message and nothing is saved.
	 *
	 * @return void
	 */
	public function test_invalid_settings_are_rejected() {
		$this->with_premium();
		$this->catch_redirects();
		$this->login_as( 'administrator' );
		update_option( Fast_Index_Settings::OPTION, Fast_Index_Settings::defaults() );

		self::set_nonce( Fast_Index_Admin_Actions::SAVE );
		$_POST['fast_index_options'] = array(
			'status'          => '1',
			'old_post_number' => 'abc',
		);

		try {
			( new Fast_Index_Admin_Actions() )->save_settings();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$messages = wp_list_pluck( Fast_Index_Admin::take_flash(), 'message' );

		$this->assertContains( 'Select at least one post type.', $messages );
		$this->assertCount( 2, $messages );
		$this->assertSame( Fast_Index_Settings::defaults(), Fast_Index_Settings::get() );
	}

	/**
	 * The migration notices appear on the dashboard with a dismiss link, only for administrators.
	 *
	 * @return void
	 */
	public function test_notices_on_dashboard() {
		Fast_Index_Notices::flag_key_rotation();
		Fast_Index_Notices::add_issues(
			array(
				array(
					'code'  => Fast_Index_Migration::ISSUE_MISSING,
					'email' => '<script>x</script>@example.com',
					'file'  => '2026/10/a.json',
				),
			)
		);
		set_current_screen( 'dashboard' );

		$this->login_as( 'editor' );
		ob_start();
		( new Fast_Index_Notices() )->render();
		$this->assertSame( '', ob_get_clean() );

		$this->login_as( 'administrator' );
		ob_start();
		( new Fast_Index_Notices() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'publicly accessible folder', $html );
		$this->assertStringContainsString( 'action=fast_index_dismiss_notice', $html );
		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringNotContainsString( '<script>x</script>', $html );

		Fast_Index_Notices::dismiss( Fast_Index_Notices::KEY_ROTATION );
		Fast_Index_Notices::dismiss( Fast_Index_Notices::ISSUES );

		ob_start();
		( new Fast_Index_Notices() )->render();
		$this->assertSame( '', ob_get_clean() );
	}
}
