<?php
/**
 * PERF-FI-01: one queue and one event instead of one cron event per address.
 *
 * @package FastIndex
 */

/**
 * The queue of notifications (Fast_Index_Queue).
 */
class Fast_Index_Test_Queue extends Fast_Index_Test_Case {

	/**
	 * IndexNow requests seen, with their address lists.
	 *
	 * @var array[]
	 */
	private $indexnow = array();

	/**
	 * Starts every test with an empty queue and a fake IndexNow endpoint.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		self::clear_queue();
		add_filter( 'pre_http_request', array( $this, 'answer_indexnow' ), 9, 3 );
	}

	/**
	 * Fake IndexNow endpoint: 200 and the address list recorded.
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

		$body             = json_decode( (string) $args['body'], true );
		$this->indexnow[] = $body['urlList'];

		return self::http_response( 200, '' );
	}

	/**
	 * Size of the cron option as it is stored.
	 *
	 * @return int Bytes.
	 */
	private static function cron_bytes() {
		return strlen( maybe_serialize( _get_cron_array() ) );
	}

	/**
	 * Number of planned queue events.
	 *
	 * @return int
	 */
	private static function queue_events() {
		$count = 0;

		foreach ( (array) _get_cron_array() as $hooks ) {
			$count += isset( $hooks[ Fast_Index_Queue::HOOK ] ) ? count( $hooks[ Fast_Index_Queue::HOOK ] ) : 0;
		}

		return $count;
	}

	/**
	 * Saving many posts keeps the cron option at the same size: one queue event, one queue row per address.
	 *
	 * @return void
	 */
	public function test_many_posts_keep_the_cron_option_size() {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$bytes = self::cron_bytes();

		self::factory()->post->create_many( 299, array( 'post_status' => 'publish' ) );

		$this->assertSame( $bytes, self::cron_bytes(), 'The cron option keeps its size.' );
		$this->assertSame( 1, self::queue_events() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK ) );
		$this->assertSame( 300, Fast_Index_Queue::count() );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test reads the rows.
		$autoload = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT autoload FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( Fast_Index_Queue::PREFIX ) . '%' ) );
		$this->assertSame( array( 'no' ), $autoload, 'Queue rows are not autoloaded.' );
	}

	/**
	 * The newest event of an address wins and keeps the place of the address.
	 *
	 * @return void
	 */
	public function test_newest_event_of_an_address_wins() {
		$first  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$second = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$url    = get_permalink( $first );

		wp_update_post(
			array(
				'ID'           => $first,
				'post_content' => 'Changed.',
			)
		);
		Fast_Index_Queue::add( $url, 'URL_DELETED', $first );

		$this->assertSame(
			array(
				array( $url, 'URL_DELETED', $first ),
				array( get_permalink( $second ), 'URL_UPDATED', $second ),
			),
			self::queued_args()
		);
	}

	/**
	 * Addresses that are not http(s), and unknown types, never enter the queue.
	 *
	 * @return void
	 */
	public function test_invalid_notifications_are_refused() {
		$this->assertFalse( Fast_Index_Queue::add( 'ftp://example.org/a/', 'URL_UPDATED', 1 ) );
		$this->assertFalse( Fast_Index_Queue::add( 'https://example.org/a/', 'URL_GONE', 1 ) );
		$this->assertSame( 0, Fast_Index_Queue::count() );
	}

	/**
	 * One run sends one batch: Google one by one, IndexNow all addresses in one request; the rest waits for the
	 * next run, which is planned right away.
	 *
	 * @return void
	 */
	public function test_run_sends_one_batch_and_plans_the_next() {
		$this->add_account();
		$this->fake_google();
		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type' => array( 'post' => '1' ),
				'indexnow'  => '1',
			)
		);

		self::factory()->post->create_many( Fast_Index_Queue::BATCH + 5, array( 'post_status' => 'publish' ) );
		wp_unschedule_hook( Fast_Index_Queue::HOOK );

		$run = Fast_Index_Queue::run();

		$this->assertSame( Fast_Index_Queue::BATCH, $run['taken'] );
		$this->assertCount( Fast_Index_Queue::BATCH, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertCount( 1, $this->indexnow, 'IndexNow gets the batch in one request.' );
		$this->assertCount( Fast_Index_Queue::BATCH, $this->indexnow[0] );
		$this->assertSame( 5, Fast_Index_Queue::count() );
		$this->assertLessThanOrEqual( time(), wp_next_scheduled( Fast_Index_Queue::HOOK ) );

		Fast_Index_Queue::run();

		$this->assertSame( 0, Fast_Index_Queue::count() );
		$this->assertCount( Fast_Index_Queue::BATCH + 5, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertCount( 2, $this->indexnow );
		$this->assertSame( Fast_Index_Queue::BATCH + 5, Fast_Index_Log::count_sent() );
	}

	/**
	 * When Google has a server error, the run stops asking Google and the rest waits OUTAGE_DELAY.
	 *
	 * @return void
	 */
	public function test_google_outage_stops_the_run() {
		$this->add_account();
		$this->fake_google();
		$this->publish_codes = array( 503 );

		self::factory()->post->create_many( 4, array( 'post_status' => 'publish' ) );
		wp_unschedule_hook( Fast_Index_Queue::HOOK );

		$run = Fast_Index_Queue::run();

		$this->assertTrue( $run['result']['outage'] );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ), 'One request, not one per address.' );
		$this->assertSame( 3, Fast_Index_Queue::count() );
		$this->assertGreaterThanOrEqual( time() + Fast_Index_Queue::OUTAGE_DELAY - 5, wp_next_scheduled( Fast_Index_Queue::HOOK ) );
	}

	/**
	 * An update is dropped when the post is no longer published by the time the queue runs.
	 *
	 * @return void
	 */
	public function test_update_of_an_unpublished_post_is_dropped() {
		$this->add_account();
		$this->fake_google();

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->assertSame( 1, Fast_Index_Queue::count() );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$run = Fast_Index_Queue::run();

		$this->assertSame( 0, $run['taken'] );
		$this->assertSame( 0, Fast_Index_Queue::count() );
		$this->assertCount( 0, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
	}

	/**
	 * A row that a newer event replaced after it was read is not taken: the newer event stays queued.
	 *
	 * @return void
	 */
	public function test_take_returns_the_queued_notification_once() {
		Fast_Index_Queue::add( 'https://example.org/a/', 'URL_UPDATED', 0 );

		$this->assertSame(
			array(
				'url'     => 'https://example.org/a/',
				'type'    => 'URL_UPDATED',
				'post_id' => 0,
			),
			Fast_Index_Queue::take( 'https://example.org/a/' )
		);
		$this->assertNull( Fast_Index_Queue::take( 'https://example.org/a/' ) );
	}

	/**
	 * Upgrade from a development build: every single event per address moves into the queue and leaves the cron
	 * option; the next one that runs on its own does the same.
	 *
	 * @return void
	 */
	public function test_legacy_events_move_into_the_queue() {
		$legacy = array(
			array( 'https://example.org/one/', 'URL_UPDATED', 11 ),
			array( 'https://example.org/two/', 'URL_DELETED', 12 ),
			array( 'https://example.org/three/', 'URL_UPDATED', 13 ),
		);

		foreach ( $legacy as $args ) {
			wp_schedule_single_event( time() - 60, Fast_Index_Cron::SUBMIT_HOOK, $args );
		}
		$bytes = self::cron_bytes();

		Fast_Index_Cron::schedule();

		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, $legacy[0] ) );
		$this->assertSame( $legacy, self::queued_args() );
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );
		$this->assertLessThan( $bytes, self::cron_bytes() );

		// An event that WP-Cron runs before the upgrade step goes into the queue with every other such event.
		self::clear_queue();
		wp_schedule_single_event( time() - 60, Fast_Index_Cron::SUBMIT_HOOK, $legacy[1] );
		do_action_ref_array( Fast_Index_Cron::SUBMIT_HOOK, $legacy[0] );

		$this->assertSame( array( $legacy[0], $legacy[1] ), self::queued_args() );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, $legacy[1] ) );
	}

	/**
	 * Deactivation removes the event and keeps the queue; activation plans the run again.
	 *
	 * @return void
	 */
	public function test_deactivation_keeps_the_queue_and_activation_resumes_it() {
		Fast_Index_Queue::add( 'https://example.org/a/', 'URL_UPDATED', 0 );

		Fast_Index_Cron::unschedule();
		$this->assertFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );
		$this->assertSame( 1, Fast_Index_Queue::count() );

		Fast_Index_Cron::schedule();
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );
	}

	/**
	 * The hourly check plans a lost run.
	 *
	 * @return void
	 */
	public function test_hourly_check_plans_a_lost_run() {
		Fast_Index_Queue::add( 'https://example.org/a/', 'URL_UPDATED', 0 );
		wp_unschedule_hook( Fast_Index_Queue::HOOK );

		( new Fast_Index_Cron() )->run_hourly();

		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Queue::HOOK ) );
	}
}
