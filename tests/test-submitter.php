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
	 * A quota error rests the account and the next account sends the URL.
	 *
	 * @return void
	 */
	public function test_quota_error_moves_to_next_account() {
		$first  = $this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$second = $this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );

		$this->publish_codes = array( 429, 200 );
		$this->fake_google();

		$post = self::factory()->post->create();
		$code = ( new Fast_Index_Submitter() )->submit( 'https://example.org/a/', 'URL_UPDATED', $post );

		$this->assertSame( 200, $code );

		$statuses = Fast_Index_Accounts::statuses();
		sort( $statuses );
		$this->assertSame( array( 200, 429 ), $statuses );
		$this->assertCount( 1, Fast_Index_Accounts::available() );

		$entries = self::log_entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( Fast_Index_Log::STATUS_SENT, $entries[0]['status'] );
		$this->assertSame( 200, $entries[0]['code'] );
		$this->assertContains( $first, array_keys( Fast_Index_Accounts::all() ) );
		$this->assertContains( $second, array_keys( Fast_Index_Accounts::all() ) );
	}

	/**
	 * When every account is out of quota the 429 is logged and the accounts rest.
	 *
	 * @return void
	 */
	public function test_quota_error_on_every_account_is_logged() {
		$this->add_account( 'one@example-project.iam.gserviceaccount.com', 'one' );
		$this->add_account( 'two@example-project.iam.gserviceaccount.com', 'two' );

		$this->publish_codes = array( 429 );
		$this->fake_google();

		$post      = self::factory()->post->create();
		$submitter = new Fast_Index_Submitter();

		$this->assertSame( 429, $submitter->submit( 'https://example.org/a/', 'URL_UPDATED', $post ) );

		$entries = self::log_entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( Fast_Index_Log::STATUS_FAILED, $entries[0]['status'] );
		$this->assertSame( 429, $entries[0]['code'] );
		$this->assertSame( 0, Fast_Index_Log::count_sent() );

		// Both accounts rest for six hours: the next URL is not attempted.
		$this->assertSame( Fast_Index_Submitter::NO_ACCOUNT, $submitter->submit( 'https://example.org/b/', 'URL_UPDATED', $post ) );
		$this->assertContains( $post, Fast_Index_Log::backlog_ids( Fast_Index_Settings::get(), 10 ) );
	}

	/**
	 * A Google outage stops the attempt without resting accounts.
	 *
	 * @return void
	 */
	public function test_server_error_does_not_rest_accounts() {
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
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, $args ) );

		( new Fast_Index_Cron() )->run_submit( $args[0], $args[1], $args[2] );

		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
	}

	/**
	 * Trashing a published post queues URL_DELETED with the URL it had before.
	 *
	 * @return void
	 */
	public function test_trash_queues_deleted_with_old_url() {
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
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, array( $url, 'URL_DELETED', $post_id ) ) );
	}

	/**
	 * Passive status, disabled events, other post types and excluded categories queue nothing.
	 *
	 * @return void
	 */
	public function test_settings_prevent_queueing() {
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

		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK ) );
		$this->assertSame( array(), _get_cron_array() ? self::submit_events() : array() );
	}

	/**
	 * Queued submit events.
	 *
	 * @return array[]
	 */
	private static function submit_events() {
		$events = array();

		foreach ( _get_cron_array() as $hooks ) {
			if ( isset( $hooks[ Fast_Index_Cron::SUBMIT_HOOK ] ) ) {
				$events[] = $hooks[ Fast_Index_Cron::SUBMIT_HOOK ];
			}
		}

		return $events;
	}
}
