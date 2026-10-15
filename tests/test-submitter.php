<?php
/**
 * Account rotation, quota handling, logging and queuing on post save.
 *
 * @package FastIndex
 */

/**
 * Submitter and post watcher.
 */
class Fast_Index_Test_Submitter extends Fast_Index_Test_Case {

	/**
	 * Log entries with their status and code.
	 *
	 * @return array[]
	 */
	private static function log_entries() {
		$entries = array();

		foreach ( get_posts(
			array(
				'post_type'   => Fast_Index_Log::POST_TYPE,
				'post_status' => array( Fast_Index_Log::STATUS_SENT, Fast_Index_Log::STATUS_FAILED ),
				'numberposts' => -1,
			)
		) as $post ) {
			$entries[] = array(
				'status' => $post->post_status,
				'code'   => (int) get_post_meta( $post->ID, Fast_Index_Log::META_CODE, true ),
				'url'    => $post->post_content,
			);
		}

		return $entries;
	}

	/**
	 * Without keys nothing is sent and nothing is logged.
	 *
	 * @return void
	 */
	public function test_no_account() {
		$this->fake_google();

		$this->assertSame( Fast_Index_Submitter::NO_ACCOUNT, ( new Fast_Index_Submitter() )->submit( 'https://example.org/a/', 'URL_UPDATED' ) );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), self::log_entries() );
	}

	/**
	 * FI-R01, premium: a 429 on the main key pauses every key until the quota resets; the backup key is not tried,
	 * and History says that the daily quota was reached.
	 *
	 * @return void
	 */
	public function test_quota_error_pauses_every_key_and_tries_no_backup() {
		$this->with_premium();

		$first  = $this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$second = $this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );

		$this->publish_codes = array( 429, 200 );
		$this->fake_google();

		$post      = self::factory()->post->create();
		$submitter = new Fast_Index_Submitter();
		$before    = time();

		$this->assertSame( 429, $submitter->submit( 'https://example.org/a/', 'URL_UPDATED', $post ) );

		// One token request and one publish request, both with the main key.
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$tokens = $this->requests_to( Fast_Index_Google_Client::TOKEN_URL );
		$this->assertCount( 1, $tokens );
		$this->assertSame( 'one@example-project.iam.gserviceaccount.com', self::jwt_issuer( $tokens[0] ) );
		$this->assertSame( array( $first => 429 ), Fast_Index_Accounts::statuses(), 'The backup key was never used.' );

		// Every key waits for the reset at midnight Pacific Time.
		$reset = Fast_Index_Accounts::quota_reset_time( $before );
		$this->assertSame( array(), Fast_Index_Accounts::available() );
		$this->assertSame( $reset, Fast_Index_Accounts::quota_pause_end() );
		$this->assertSame( $reset, Fast_Index_Accounts::paused_until( $second ) );
		$this->assertSame( $reset, Fast_Index_Accounts::next_ready_time() );
		$this->assertSame( Fast_Index_Submitter::RESTING, $submitter->submit( 'https://example.org/b/', 'URL_UPDATED', $post ) );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ), 'Nothing is tried during the pause.' );

		$entries = self::log_entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( Fast_Index_Log::STATUS_FAILED, $entries[0]['status'] );
		$this->assertSame( 429, $entries[0]['code'] );

		$this->login_as( 'administrator' );
		ob_start();
		( new Fast_Index_Admin() )->render_history();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Daily quota reached', $html );
		$this->assertStringContainsString( 'fi-history-note', $html );
	}

	/**
	 * FI-R01, premium: when Google rejects the main key (403), the key is paused and the backup key sends.
	 *
	 * @return void
	 */
	public function test_rejected_main_key_tries_the_backup() {
		$this->with_premium();

		$first  = $this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$second = $this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );

		$this->publish_codes = array( 403, 200 );
		$this->fake_google();

		$post = self::factory()->post->create();
		$this->assertSame( 200, ( new Fast_Index_Submitter() )->submit( 'https://example.org/a/', 'URL_UPDATED', $post ) );

		$tokens = $this->requests_to( Fast_Index_Google_Client::TOKEN_URL );
		$this->assertCount( 2, $tokens );
		$this->assertSame( 'one@example-project.iam.gserviceaccount.com', self::jwt_issuer( $tokens[0] ) );
		$this->assertSame( 'two@example-project.iam.gserviceaccount.com', self::jwt_issuer( $tokens[1] ) );
		$this->assertSame(
			array(
				$first  => 403,
				$second => 200,
			),
			Fast_Index_Accounts::statuses()
		);
		$this->assertSame( array( $second ), array_keys( Fast_Index_Accounts::available() ), 'The rejected key is paused.' );
		$this->assertSame( 0, Fast_Index_Accounts::quota_pause_end() );
		$this->assertSame( Fast_Index_Log::STATUS_SENT, self::log_entries()[0]['status'] );

		// A 401 on the main key works the same way; the main key is always tried first.
		Fast_Index_Accounts::delete( $first );
		$third = $this->add_account( 'three@example-project.iam.gserviceaccount.com', 'three' );
		$this->assertSame( array( $second, $third ), array_keys( Fast_Index_Accounts::available() ) );
	}

	/**
	 * The quota resets at the next midnight Pacific Time, in summer and in winter time.
	 *
	 * @return void
	 */
	public function test_quota_reset_is_midnight_pacific_time() {
		// 2026-07-01 10:00 UTC = 03:00 PDT (UTC-7): next reset 2026-07-02 00:00 PDT = 07:00 UTC.
		$this->assertSame( gmmktime( 7, 0, 0, 7, 2, 2026 ), Fast_Index_Accounts::quota_reset_time( gmmktime( 10, 0, 0, 7, 1, 2026 ) ) );
		// 2026-01-15 07:59 UTC = 23:59 PST (UTC-8) on the 14th: next reset 2026-01-15 00:00 PST = 08:00 UTC.
		$this->assertSame( gmmktime( 8, 0, 0, 1, 15, 2026 ), Fast_Index_Accounts::quota_reset_time( gmmktime( 7, 59, 0, 1, 15, 2026 ) ) );
		// Exactly at midnight the next reset is a day later.
		$this->assertSame( gmmktime( 8, 0, 0, 1, 16, 2026 ), Fast_Index_Accounts::quota_reset_time( gmmktime( 8, 0, 0, 1, 15, 2026 ) ) );
	}

	/**
	 * Client email in the signed JWT of a token request.
	 *
	 * @param array $request Request recorded by the fake Google endpoint.
	 * @return string
	 */
	private static function jwt_issuer( array $request ) {
		$parts  = explode( '.', (string) $request['args']['body']['assertion'] );
		$claims = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reads a JWT in a test.

		return (string) $claims['iss'];
	}

	/**
	 * Premium: when the daily quota is used up the 429 is logged once and every key waits.
	 *
	 * @return void
	 */
	public function test_quota_error_on_every_account_is_logged() {
		$this->with_premium();

		$this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );

		$this->publish_codes = array( 429 );
		$this->fake_google();

		$post      = self::factory()->post->create();
		$submitter = new Fast_Index_Submitter();

		$this->assertSame( 429, $submitter->submit( 'https://example.org/a/', 'URL_UPDATED', $post ) );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );

		$entries = self::log_entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( Fast_Index_Log::STATUS_FAILED, $entries[0]['status'] );
		$this->assertSame( 429, $entries[0]['code'] );
		$this->assertSame( 0, Fast_Index_Log::count_sent() );

		// Every key waits for the quota reset: the next URL is not attempted.
		$this->assertSame( Fast_Index_Submitter::RESTING, $submitter->submit( 'https://example.org/b/', 'URL_UPDATED', $post ) );
		$this->assertContains( $post, Fast_Index_Premium_Backlog::ids( Fast_Index_Settings::get(), 10 ) );
	}

	/**
	 * Premium: a Google outage stops the attempt without resting accounts.
	 *
	 * @return void
	 */
	public function test_server_error_does_not_rest_accounts() {
		$this->with_premium();

		$this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );

		$this->publish_codes = array( 503 );
		$this->fake_google();

		$this->assertSame( 503, ( new Fast_Index_Submitter() )->submit( 'https://example.org/a/', 'URL_UPDATED' ) );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertCount( 2, Fast_Index_Accounts::available() );
	}

	/**
	 * Publishing a post queues a cron event; nothing is sent while the post is saved.
	 *
	 * @return void
	 */
	public function test_publish_queues_instead_of_sending() {
		$this->add_account();
		$this->fake_google();

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$args    = array( get_permalink( $post_id ), 'URL_UPDATED', $post_id );

		$this->assertSame( array(), $this->requests, 'Saving a post must not call Google.' );
		$this->assertTrue( self::is_queued( $args[0], $args[1], $args[2] ) );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );

		Fast_Index_Queue::run();

		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
	}

	/**
	 * Free version, several stored keys: only the first key is tried; a 429 on it pauses it and the other keys
	 * are not used.
	 *
	 * @return void
	 */
	public function test_free_version_sends_with_the_first_key_only() {
		$first = $this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );

		$this->publish_codes = array( 429, 200 );
		$this->fake_google();

		$submitter = new Fast_Index_Submitter();

		$this->assertSame( array( $first ), array_keys( Fast_Index_Accounts::in_use() ) );
		$this->assertSame( 429, $submitter->submit( 'https://example.org/a/', 'URL_UPDATED' ) );
		$this->assertGreaterThan( time(), Fast_Index_Accounts::quota_pause_end() );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertSame( array( $first => 429 ), Fast_Index_Accounts::statuses() );
		$this->assertSame( Fast_Index_Submitter::RESTING, $submitter->submit( 'https://example.org/b/', 'URL_UPDATED' ) );
		$this->assertCount( 2, Fast_Index_Accounts::all(), 'The second key is kept.' );
	}

	/**
	 * Premium: trashing a published post queues URL_DELETED with the URL it had before.
	 *
	 * @return void
	 */
	public function test_trash_queues_deleted_with_old_url() {
		$this->with_premium();

		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'   => array( 'post' => '1' ),
				'post_status' => array( 'trash' => '1' ),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'going-away',
			)
		);
		$url     = get_permalink( $post_id );

		wp_trash_post( $post_id );

		$this->assertStringNotContainsString( '__trashed', $url );
		$this->assertTrue( self::is_queued( $url, 'URL_DELETED', $post_id ) );
	}

	/**
	 * Premium: passive status, disabled events, other post types and excluded categories queue nothing.
	 *
	 * @return void
	 */
	public function test_settings_prevent_queueing() {
		$this->with_premium();

		$category = self::factory()->category->create();

		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'        => array( 'post' => '1' ),
				'post_status'      => array( 'publish' => '1' ),
				'exclude_category' => array( $category => $category ),
			)
		);

		self::factory()->post->create( array( 'post_category' => array( $category ) ) );
		self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( Fast_Index_Settings::OPTION, array( 'status' => '2' ) );
		self::factory()->post->create();

		$this->assertFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );
		$this->assertSame( array(), self::queued_args() );
	}
}
