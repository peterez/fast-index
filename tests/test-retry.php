<?php
/**
 * Notifications that no key can take: History entry, retry queue, limits and clean-up (FI-044).
 *
 * @package FastIndex
 */

/**
 * Fast_Index_Retry and the not-sent History entries.
 */
class Fast_Index_Test_Retry extends Fast_Index_Test_Case {

	/**
	 * Default account email of the tests.
	 */
	const EMAIL = 'indexer@example-project.iam.gserviceaccount.com';

	/**
	 * Clears the retry event left by a test.
	 *
	 * @return void
	 */
	public function tear_down() {
		wp_unschedule_hook( Fast_Index_Retry::HOOK );

		parent::tear_down();
	}

	/**
	 * Log entries of one address, oldest first.
	 *
	 * @param string $url Address.
	 * @return array[] Keys: id, status, code, type, retry.
	 */
	private static function entries_for( $url ) {
		$entries = array();

		foreach ( get_posts(
			array(
				'post_type'   => Fast_Index_Log::POST_TYPE,
				'post_status' => array( Fast_Index_Log::STATUS_SENT, Fast_Index_Log::STATUS_FAILED ),
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- Test: reads every entry of the capped queue (MAX + a few).
				'numberposts' => 500,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		) as $post ) {
			if ( $post->post_content !== $url ) {
				continue;
			}

			$entries[] = array(
				'id'     => $post->ID,
				'status' => $post->post_status,
				'code'   => (int) get_post_meta( $post->ID, Fast_Index_Log::META_CODE, true ),
				'type'   => get_post_meta( $post->ID, Fast_Index_Log::META_TYPE, true ),
				'retry'  => '' !== get_post_meta( $post->ID, Fast_Index_Log::META_RETRY, true ),
			);
		}

		return $entries;
	}

	/**
	 * Publishes a post and runs its queued notification, as WP-Cron would.
	 *
	 * @return array Keys: post_id, url.
	 */
	private function publish_and_run() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$url     = get_permalink( $post_id );

		$this->run_queued( $url, 'URL_UPDATED', $post_id );

		return array(
			'post_id' => $post_id,
			'url'     => $url,
		);
	}

	/**
	 * Runs a queued notification event, as WP-Cron would.
	 *
	 * @param string $url     Address.
	 * @param string $type    Notification type.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function run_queued( $url, $type, $post_id ) {
		$args = array( $url, $type, $post_id );

		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, $args ), 'The save queued the notification.' );
		wp_unschedule_event( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, $args ), Fast_Index_Cron::SUBMIT_HOOK, $args );

		do_action_ref_array( Fast_Index_Cron::SUBMIT_HOOK, $args );
	}

	/**
	 * Runs the planned retry event, as WP-Cron would: the event leaves the schedule before it runs.
	 *
	 * @return void
	 */
	private static function cron_retry() {
		$planned = wp_next_scheduled( Fast_Index_Retry::HOOK );

		if ( false !== $planned ) {
			wp_unschedule_event( $planned, Fast_Index_Retry::HOOK );
		}

		do_action( Fast_Index_Retry::HOOK );
	}

	/**
	 * Stores an account that rests after a 429.
	 *
	 * @return string Account ID.
	 */
	private function resting_account() {
		$id = $this->add_account();
		Fast_Index_Accounts::set_status( $id, 429 );

		return $id;
	}

	/**
	 * Ends every pause, as if six hours had passed and the daily quota had been reset.
	 *
	 * @return void
	 */
	private static function end_rests() {
		delete_transient( Fast_Index_Accounts::QUOTA_TRANSIENT );

		foreach ( array_keys( Fast_Index_Accounts::all() ) as $id ) {
			set_transient(
				Fast_Index_Accounts::STATUS_TRANSIENT_PREFIX . $id,
				array(
					'code'  => 429,
					'until' => time() - 1,
				),
				HOUR_IN_SECONDS
			);
		}
	}

	/**
	 * Without a key the notification is recorded in History as not sent, and nothing waits.
	 *
	 * @return void
	 */
	public function test_publish_without_key_is_recorded_not_queued() {
		$this->fake_google();

		$post = $this->publish_and_run();
		$rows = self::entries_for( $post['url'] );

		$this->assertSame( array(), $this->requests, 'Nothing can be sent without a key.' );
		$this->assertCount( 1, $rows );
		$this->assertSame( Fast_Index_Log::STATUS_FAILED, $rows[0]['status'] );
		$this->assertSame( Fast_Index_Submitter::NO_ACCOUNT, $rows[0]['code'] );
		$this->assertFalse( $rows[0]['retry'], 'Without a key nothing waits.' );
		$this->assertSame( 0, Fast_Index_Log::count_held() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );
		$this->assertSame( 0, Fast_Index_Log::count_sent() );
		$this->assertSame( 1, Fast_Index_Log::count_waiting( Fast_Index_Settings::get() ), 'The post still counts as waiting on the History screen.' );

		// The History screen says why.
		$this->login_as( 'administrator' );
		ob_start();
		( new Fast_Index_Admin() )->render_history();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Not sent: no service account key', $html );
		$this->assertStringNotContainsString( Fast_Index_Admin::retry_note(), $html );
	}

	/**
	 * Premium (Edit event): saving the same post again without a key keeps one not-sent line for its address.
	 *
	 * @return void
	 */
	public function test_repeated_events_without_key_keep_one_line() {
		$this->with_premium();

		$post = $this->publish_and_run();

		wp_update_post(
			array(
				'ID'           => $post['post_id'],
				'post_content' => 'Changed.',
			)
		);
		$this->run_queued( $post['url'], 'URL_UPDATED', $post['post_id'] );

		$this->assertCount( 1, self::entries_for( $post['url'] ) );
	}

	/**
	 * While every key rests, the notification waits and goes out when the rest ends.
	 *
	 * @return void
	 */
	public function test_resting_keys_queue_until_the_rest_ends() {
		$this->resting_account();
		$this->fake_google();

		$before = time();
		$post   = $this->publish_and_run();
		$rows   = self::entries_for( $post['url'] );

		$this->assertSame( array(), $this->requests );
		$this->assertCount( 1, $rows );
		$this->assertSame( Fast_Index_Submitter::RESTING, $rows[0]['code'] );
		$this->assertTrue( $rows[0]['retry'] );
		$this->assertSame( 1, Fast_Index_Log::count_held() );

		// Planned for the end of the rest, not earlier.
		$planned = wp_next_scheduled( Fast_Index_Retry::HOOK );
		$this->assertNotFalse( $planned );
		$this->assertGreaterThanOrEqual( $before + Fast_Index_Accounts::STATUS_TTL, $planned );
		$this->assertLessThanOrEqual( time() + Fast_Index_Accounts::STATUS_TTL + Fast_Index_Retry::MARGIN, $planned );

		// A run before the end of the rest sends nothing and plans the next one.
		self::cron_retry();
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 1, Fast_Index_Log::count_held() );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );

		// Six hours later.
		self::end_rests();
		self::cron_retry();

		$publish = $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL );
		$this->assertCount( 1, $publish );
		$body = json_decode( $publish[0]['args']['body'], true );
		$this->assertSame( $post['url'], $body['url'] );
		$this->assertSame( 'URL_UPDATED', $body['type'] );

		$rows = self::entries_for( $post['url'] );
		$this->assertCount( 2, $rows, 'The not-sent line stays, the attempt is added.' );
		$this->assertFalse( $rows[0]['retry'] );
		$this->assertSame( Fast_Index_Log::STATUS_SENT, $rows[1]['status'] );
		$this->assertSame( 200, $rows[1]['code'] );
		$this->assertSame( 0, Fast_Index_Log::count_held() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );
	}

	/**
	 * The History screen marks a waiting notification.
	 *
	 * @return void
	 */
	public function test_history_shows_waiting_note() {
		$this->resting_account();
		$this->publish_and_run();
		$this->login_as( 'administrator' );

		ob_start();
		( new Fast_Index_Admin() )->render_history();
		$html = (string) ob_get_clean();

		// The status is an isolated element, so a code and its name keep their order on right-to-left screens.
		$this->assertStringContainsString( '<bdi dir="auto">Not sent: every key was paused</bdi>', $html );
		$this->assertStringContainsString( Fast_Index_Admin::retry_note(), $html );
		$this->assertStringContainsString( 'fi-history-status--not-sent', $html );
	}

	/**
	 * Premium (Edit and Trash events): several events of one address while keys rest: one waiting
	 * notification, the newest event wins.
	 *
	 * @return void
	 */
	public function test_same_address_waits_once_and_newest_event_wins() {
		$this->with_premium();

		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'   => array( 'post' => '1' ),
				'post_status' => array(
					'publish' => '1',
					'edit'    => '1',
					'trash'   => '1',
				),
			)
		);
		$this->resting_account();

		$post = $this->publish_and_run();

		wp_update_post(
			array(
				'ID'           => $post['post_id'],
				'post_content' => 'Changed.',
			)
		);
		$this->run_queued( $post['url'], 'URL_UPDATED', $post['post_id'] );

		wp_trash_post( $post['post_id'] );
		$this->run_queued( $post['url'], 'URL_DELETED', $post['post_id'] );

		$rows = self::entries_for( $post['url'] );
		$this->assertCount( 1, $rows, 'One History line for the address.' );
		$this->assertSame( 'URL_DELETED', $rows[0]['type'] );
		$this->assertTrue( $rows[0]['retry'] );
		$this->assertSame( 1, Fast_Index_Log::count_held() );

		// When the rest ends the removal is sent once.
		$this->fake_google();
		self::end_rests();
		self::cron_retry();

		$publish = $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL );
		$this->assertCount( 1, $publish );
		$this->assertSame( 'URL_DELETED', json_decode( $publish[0]['args']['body'], true )['type'] );
	}

	/**
	 * A 429 from every key during the attempt keeps the notification, with the History entry of the attempt.
	 *
	 * @return void
	 */
	public function test_quota_error_on_every_key_waits_for_the_rest() {
		$this->add_account();
		$this->publish_codes = array( 429 );
		$this->fake_google();

		$post = $this->publish_and_run();
		$rows = self::entries_for( $post['url'] );

		$this->assertCount( 1, $rows );
		$this->assertSame( 429, $rows[0]['code'] );
		$this->assertTrue( $rows[0]['retry'] );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );

		$this->publish_codes = array( 200 );
		self::end_rests();
		self::cron_retry();

		$rows = self::entries_for( $post['url'] );
		$this->assertCount( 2, $rows );
		$this->assertFalse( $rows[0]['retry'] );
		$this->assertSame( 200, $rows[1]['code'] );
		$this->assertSame( 0, Fast_Index_Log::count_held() );
	}

	/**
	 * A retry that hits 429 again keeps waiting with its first time, so it still ages out.
	 *
	 * @return void
	 */
	public function test_failed_retry_keeps_its_age() {
		$this->resting_account();
		$post  = $this->publish_and_run();
		$since = time() - DAY_IN_SECONDS;
		$held  = Fast_Index_Log::held( 1 );

		Fast_Index_Log::hold( $held[0]['id'], $since );

		$this->publish_codes = array( 429 );
		$this->fake_google();
		self::end_rests();
		self::cron_retry();

		$held = Fast_Index_Log::held( 5 );
		$this->assertCount( 1, $held );
		$this->assertSame( $since, $held[0]['since'] );
		$this->assertSame( $post['url'], $held[0]['url'] );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
	}

	/**
	 * At most MAX notifications wait; the oldest leave the queue first and stay in History.
	 *
	 * @return void
	 */
	public function test_queue_is_capped() {
		$this->resting_account();
		$extra = 3;

		for ( $i = 0; $i < Fast_Index_Retry::MAX + $extra; $i++ ) {
			Fast_Index_Retry::deliver( 'https://example.org/capped-' . $i . '/', 'URL_UPDATED', 0 );
		}

		$this->assertSame( Fast_Index_Retry::MAX, Fast_Index_Log::count_held() );

		for ( $i = 0; $i < $extra; $i++ ) {
			$rows = self::entries_for( 'https://example.org/capped-' . $i . '/' );
			$this->assertCount( 1, $rows, 'A dropped notification stays in History.' );
			$this->assertFalse( $rows[0]['retry'], 'The oldest notifications leave the queue.' );
		}

		$newest = self::entries_for( 'https://example.org/capped-' . ( Fast_Index_Retry::MAX + $extra - 1 ) . '/' );
		$this->assertTrue( $newest[0]['retry'] );
	}

	/**
	 * Notifications older than MAX_AGE leave the queue without being sent.
	 *
	 * @return void
	 */
	public function test_old_notifications_expire() {
		$this->resting_account();
		$this->publish_and_run();
		$held = Fast_Index_Log::held( 1 );

		Fast_Index_Log::hold( $held[0]['id'], time() - Fast_Index_Retry::MAX_AGE - 1 );

		$this->fake_google();
		self::end_rests();
		self::cron_retry();

		$this->assertSame( array(), $this->requests );
		$this->assertSame( 0, Fast_Index_Log::count_held() );
		$this->assertNotNull( get_post( $held[0]['id'] ), 'The History line stays.' );
	}

	/**
	 * An update is not sent for a post that is no longer published.
	 *
	 * @return void
	 */
	public function test_update_of_unpublished_post_is_dropped() {
		$this->resting_account();
		$post = $this->publish_and_run();

		wp_update_post(
			array(
				'ID'          => $post['post_id'],
				'post_status' => 'draft',
			)
		);

		$this->fake_google();
		self::end_rests();
		self::cron_retry();

		$this->assertSame( array(), $this->requests );
		$this->assertSame( 0, Fast_Index_Log::count_held() );
	}

	/**
	 * Passive status keeps the notifications waiting without sending them.
	 *
	 * @return void
	 */
	public function test_passive_status_sends_nothing() {
		$this->resting_account();
		$this->publish_and_run();

		update_option( Fast_Index_Settings::OPTION, array( 'status' => Fast_Index_Settings::STATUS_PASSIVE ) );

		$this->fake_google();
		self::end_rests();
		self::cron_retry();

		$this->assertSame( array(), $this->requests );
		$this->assertSame( 1, Fast_Index_Log::count_held() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ), 'Nothing is planned while Passive; the hourly check looks again.' );
	}

	/**
	 * When the keys are deleted meanwhile, the run stops without planning a loop, and a key upload resumes it.
	 *
	 * @return void
	 */
	public function test_deleted_keys_stop_the_run_and_upload_resumes() {
		$id = $this->resting_account();
		$this->publish_and_run();

		Fast_Index_Accounts::delete( $id );
		self::cron_retry();

		$this->assertSame( 1, Fast_Index_Log::count_held() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );

		// A new key: the queue runs at once.
		$this->add_account( 'fresh@example-project.iam.gserviceaccount.com', 'fresh' );
		Fast_Index_Retry::resume();

		$planned = wp_next_scheduled( Fast_Index_Retry::HOOK );
		$this->assertNotFalse( $planned );
		$this->assertLessThanOrEqual( time(), $planned );

		$this->fake_google();
		self::cron_retry();
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertSame( 0, Fast_Index_Log::count_held() );
	}

	/**
	 * A successful notification sends the waiting ones without waiting for the planned run.
	 *
	 * @return void
	 */
	public function test_success_brings_the_run_forward() {
		$this->resting_account();
		$this->publish_and_run();

		self::end_rests();
		$this->fake_google();
		$this->publish_and_run();

		$planned = wp_next_scheduled( Fast_Index_Retry::HOOK );
		$this->assertNotFalse( $planned );
		$this->assertLessThanOrEqual( time(), $planned );
	}

	/**
	 * The hourly check plans a lost run.
	 *
	 * @return void
	 */
	public function test_hourly_check_plans_a_lost_run() {
		$this->resting_account();
		$this->publish_and_run();
		wp_unschedule_hook( Fast_Index_Retry::HOOK );

		( new Fast_Index_Cron() )->run_hourly();

		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );
	}

	/**
	 * Deactivation removes the retry event and keeps the queue; activation plans it again.
	 *
	 * @return void
	 */
	public function test_deactivation_removes_the_event() {
		$this->resting_account();
		$this->publish_and_run();
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );

		Fast_Index_Installer::deactivate();

		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );
		$this->assertSame( 1, Fast_Index_Log::count_held() );

		Fast_Index_Installer::activate();

		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );
	}

	/**
	 * Uninstall removes the queue and its event, also when the data is kept.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_the_queue() {
		$this->resting_account();
		$post = $this->publish_and_run();

		Fast_Index_Uninstaller::run();
		wp_cache_flush();

		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );
		$this->assertSame( 0, Fast_Index_Log::count_held() );

		$rows = self::entries_for( $post['url'] );
		$this->assertCount( 1, $rows, 'History is kept by default.' );
		$this->assertFalse( $rows[0]['retry'] );
	}

	/**
	 * The queue belongs to each site of a network.
	 *
	 * @return void
	 */
	public function test_queue_is_per_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$this->resting_account();
		$this->publish_and_run();

		$other = self::factory()->blog->create();
		switch_to_blog( $other );

		$this->assertSame( 0, Fast_Index_Log::count_held() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );

		restore_current_blog();

		$this->assertSame( 1, Fast_Index_Log::count_held() );
	}
}
