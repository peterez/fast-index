<?php
/**
 * SEC-FI-02: password-protected posts never reach Google or IndexNow.
 *
 * @package FastIndex
 */

/**
 * Every path that sends a post leaves password-protected posts out.
 */
class Fast_Index_Test_Password_Protected extends Fast_Index_Test_Case {

	/**
	 * IndexNow requests seen.
	 *
	 * @var int
	 */
	private $indexnow = 0;

	/**
	 * Starts with a key, the fake Google endpoint, a fake IndexNow endpoint and IndexNow on.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->add_account();
		$this->fake_google();
		add_filter( 'pre_http_request', array( $this, 'answer_indexnow' ), 9, 3 );
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
					'trash'   => '1',
				),
				'indexnow'    => '1',
			)
		);
		self::clear_queue();
	}

	/**
	 * Fake IndexNow endpoint.
	 *
	 * @param false|array $response Short-circuit value.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return false|array
	 */
	public function answer_indexnow( $response, $args, $url ) {
		if ( Fast_Index_IndexNow::ENDPOINT !== $url ) {
			return $response;
		}

		++$this->indexnow;

		return self::http_response( 200, '' );
	}

	/**
	 * A published post with a password.
	 *
	 * @param array $args Extra post fields.
	 * @return int
	 */
	private static function protected_post( $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_title'    => 'Offer for a client',
				),
				$args
			)
		);
	}

	/**
	 * Publish, edit and trash of a protected post queue nothing; the filter can still allow it.
	 *
	 * @return void
	 */
	public function test_post_events_skip_protected_posts() {
		$post_id = self::protected_post();
		$post    = get_post( $post_id );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Changed.',
			)
		);
		wp_trash_post( $post_id );
		self::protected_post( array( 'post_type' => 'page' ) );

		$this->assertSame( array(), self::queued_args() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );

		foreach ( array( 'publish', 'edit', 'trash' ) as $event ) {
			$this->assertFalse( Fast_Index_Post_Watcher::should_notify( Fast_Index_Settings::get(), $post, $event ), $event );
		}

		add_filter( 'fast_index_should_notify', '__return_true' );
		$this->assertTrue( Fast_Index_Post_Watcher::should_notify( Fast_Index_Settings::get(), $post, 'publish' ), 'The filter can send it.' );
		remove_filter( 'fast_index_should_notify', '__return_true' );
	}

	/**
	 * A post that gets a password after it was queued is not sent by the queue.
	 *
	 * @return void
	 */
	public function test_queue_drops_a_post_that_got_a_password() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->assertSame( 1, Fast_Index_Queue::count() );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Sets the password without a save event.
		$wpdb->update( $wpdb->posts, array( 'post_password' => 'secret' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		Fast_Index_Queue::run();

		$this->assertSame( 0, Fast_Index_Queue::count() );
		$this->assertCount( 0, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertSame( 0, $this->indexnow );
	}

	/**
	 * A queued removal is still sent: it tells Google that an address is gone.
	 *
	 * @return void
	 */
	public function test_queued_removal_is_still_sent() {
		$this->assertTrue( Fast_Index_Post_Watcher::still_public( Fast_Index_Google_Client::TYPE_DELETED, self::protected_post() ) );
		$this->assertFalse( Fast_Index_Post_Watcher::still_public( Fast_Index_Google_Client::TYPE_UPDATED, self::protected_post() ) );
	}

	/**
	 * The row and bulk actions leave protected posts out and say so.
	 *
	 * @return void
	 */
	public function test_manual_action_skips_protected_posts() {
		$this->login_as( 'administrator' );
		$open   = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$secret = self::protected_post();
		self::clear_queue();

		$result = Fast_Index_Manual::send( array( $secret, $open ) );

		$this->assertSame( 1, $result['protected'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertSame( 1, $result['google_accepted'] );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertSame( get_permalink( $open ), json_decode( $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL )[0]['args']['body'], true )['url'] );
		$this->assertContains( '1 selected item is password protected and was not sent.', Fast_Index_Manual::result_lines( $result ) );

		$result = Fast_Index_Manual::send( array( $secret ) );
		$this->assertSame( 0, $result['google_accepted'] );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ), 'Nothing more was sent.' );
	}

	/**
	 * Protected posts never count as waiting for Google.
	 *
	 * @return void
	 */
	public function test_protected_posts_do_not_wait() {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::protected_post();
		Fast_Index_Log::flush_stats();

		$this->assertSame( 1, Fast_Index_Log::count_waiting( Fast_Index_Settings::get() ) );
	}

	/**
	 * Premium: the old-post batches and their counter leave protected posts out.
	 *
	 * @return void
	 */
	public function test_old_post_batches_skip_protected_posts() {
		$this->with_premium();

		$open   = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$secret = self::protected_post();
		self::clear_queue();

		$settings = Fast_Index_Settings::get();
		$ids      = Fast_Index_Premium_Backlog::ids( $settings, 10 );

		$this->assertContains( $open, $ids );
		$this->assertNotContains( $secret, $ids );
		$this->assertSame( 1, Fast_Index_Premium_Backlog::count_waiting( $settings ) );
	}
}
