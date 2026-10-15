<?php
/**
 * Premium weekly email report (FI-R08): numbers of History, language of the recipient, off switch, no request.
 *
 * @package FastIndex
 */

/**
 * Fast_Index_Premium_Report.
 */
class Fast_Index_Test_Report extends Fast_Index_Test_Case {

	/**
	 * Outgoing HTTP requests.
	 *
	 * @var int
	 */
	private $http = 0;

	/**
	 * Locale in force while wp_mail() ran.
	 *
	 * @var string
	 */
	private $mail_locale = '';

	/**
	 * Premium module, a fresh mailer, counters.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->with_premium();
		reset_phpmailer_instance();
		add_filter(
			'pre_http_request',
			function ( $response ) {
				++$this->http;
				return $response;
			},
			1
		);
		add_filter(
			'wp_mail',
			function ( $args ) {
				$this->mail_locale = get_locale();
				return $args;
			}
		);
	}

	/**
	 * Sent emails.
	 *
	 * @return array[]
	 */
	private static function sent() {
		return tests_retrieve_phpmailer_instance()->mock_sent;
	}

	/**
	 * The report has the numbers of History for the last seven days, links to History and to the settings, and goes
	 * to the administration address without any HTTP request.
	 *
	 * @return void
	 */
	public function test_report_has_the_numbers_of_history() {
		$posts = self::factory()->post->create_many( 4 );
		self::clear_queue();

		Fast_Index_Log::add( $posts[0], get_permalink( $posts[0] ), 200, 'URL_UPDATED' );
		Fast_Index_Log::add( $posts[1], get_permalink( $posts[1] ), 200, 'URL_UPDATED' );
		Fast_Index_Log::add( $posts[2], get_permalink( $posts[2] ), 429, 'URL_UPDATED' );
		Fast_Index_Log::add_indexnow( $posts[0], get_permalink( $posts[0] ), 202, 'URL_UPDATED' );
		$old = Fast_Index_Log::add( 0, home_url( '/last-month/' ), 200, 'URL_UPDATED' );
		wp_update_post(
			array(
				'ID'            => $old,
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ),
			)
		);

		$numbers = Fast_Index_Premium_Report::numbers( time() - WEEK_IN_SECONDS );
		$this->assertSame( 2, $numbers['google_accepted'] );
		$this->assertSame( 1, $numbers['google_failed'] );
		$this->assertSame( 1, $numbers['indexnow_accepted'] );
		$this->assertSame( Fast_Index_Log::count_waiting( Fast_Index_Settings::get() ), $numbers['waiting'] );

		$this->assertTrue( Fast_Index_Premium_Report::send() );

		$mail = self::sent();
		$this->assertCount( 1, $mail );
		$this->assertSame( get_option( 'admin_email' ), $mail[0]['to'][0][0] );
		$this->assertStringContainsString( 'Fast Index weekly report', $mail[0]['subject'] );
		$this->assertStringContainsString( 'Accepted by Google: 2', $mail[0]['body'] );
		$this->assertStringContainsString( 'Not accepted by Google or not sent: 1', $mail[0]['body'] );
		$this->assertStringContainsString( 'Accepted by IndexNow: 1', $mail[0]['body'] );
		$this->assertStringContainsString( 'page=fast-index-history', $mail[0]['body'] );
		$this->assertStringContainsString( 'uncheck Weekly Email Report on the settings screen: ' . admin_url( 'admin.php?page=fast-index' ), $mail[0]['body'] );
		$this->assertSame( 0, $this->http );
	}

	/**
	 * Turned off, or a week without any History entry: no email.
	 *
	 * @return void
	 */
	public function test_off_or_quiet_week_sends_nothing() {
		$this->assertFalse( Fast_Index_Premium_Report::send(), 'Nothing happened this week.' );

		Fast_Index_Log::add( 0, home_url( '/a/' ), 200, 'URL_UPDATED' );
		update_option( Fast_Index_Settings::OPTION, array( 'weekly_report' => false ) );
		$this->assertFalse( Fast_Index_Premium_Report::send() );
		$this->assertCount( 0, self::sent() );

		$this->assertTrue( Fast_Index_Settings::defaults()['weekly_report'], 'On by default.' );
	}

	/**
	 * The email is written in the language of the user who has the administration address; the request's language
	 * comes back afterwards.
	 *
	 * @return void
	 */
	public function test_report_uses_the_recipients_language() {
		$admin = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'owner@example.org',
			)
		);
		update_user_meta( $admin, 'locale', 'de_DE' );
		update_option( 'admin_email', 'owner@example.org' );
		reset_phpmailer_instance(); // WordPress itself mails about the changed administration address.
		Fast_Index_Log::add( 0, home_url( '/a/' ), 200, 'URL_UPDATED' );

		$before = get_locale();
		$this->assertTrue( Fast_Index_Premium_Report::send() );

		$this->assertSame( 'de_DE', $this->mail_locale );
		$this->assertSame( $before, get_locale() );

		if ( is_readable( FAST_INDEX_PATH . 'languages/fast-index-de_DE.mo' ) && '' !== self::translation( 'de_DE', 'Accepted by Google: %s' ) ) {
			$this->assertStringContainsString( sprintf( self::translation( 'de_DE', 'Accepted by Google: %s' ), '1' ), self::sent()[0]['body'] );
		}

		$this->assertSame( 'Settings saved.', __( 'Settings saved.', 'fast-index' ), 'The plugin speaks the request language again.' );
	}

	/**
	 * The weekly event is planned by the hourly run of the module and removed on deactivation; the free version
	 * plans nothing.
	 *
	 * @return void
	 */
	public function test_event_lifecycle() {
		do_action( 'fast_index_hourly' );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Premium_Report::HOOK ) );
		$this->assertSame( 'weekly', wp_get_schedule( Fast_Index_Premium_Report::HOOK ) );

		Fast_Index_Cron::unschedule();
		$this->assertFalse( wp_next_scheduled( Fast_Index_Premium_Report::HOOK ) );
		$this->assertContains( Fast_Index_Premium_Report::HOOK, Fast_Index_Uninstaller::cron_hooks() );
	}

	/**
	 * A translation from a bundled .mo file.
	 *
	 * @param string $locale Locale.
	 * @param string $text   Source string.
	 * @return string Empty when not translated.
	 */
	private static function translation( $locale, $text ) {
		$mo = new MO();
		$mo->import_from_file( FAST_INDEX_PATH . 'languages/fast-index-' . $locale . '.mo' );

		return isset( $mo->entries[ $text ] ) ? (string) $mo->entries[ $text ]->translations[0] : '';
	}
}
