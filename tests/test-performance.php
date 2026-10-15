<?php
/**
 * Fixes of the performance audit (PERF-FI-03, -04, -05, -07, -10) and the key file headers (SEC-FI-07).
 *
 * @package FastIndex
 */

/**
 * Fewer queries and writes in the hot paths.
 */
class Fast_Index_Test_Performance extends Fast_Index_Test_Case {

	/**
	 * PERF-FI-03: History entries written by scheduled tasks keep the cached counters; entries written in the admin
	 * forget them.
	 *
	 * @return void
	 */
	public function test_cron_entries_keep_the_cached_counters() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::clear_queue();
		Fast_Index_Log::stats( Fast_Index_Settings::get() );
		$this->assertNotFalse( get_transient( Fast_Index_Log::STATS_TRANSIENT ) );

		add_filter( 'wp_doing_cron', '__return_true' );
		Fast_Index_Log::add( $post_id, get_permalink( $post_id ), 200, 'URL_UPDATED' );
		remove_filter( 'wp_doing_cron', '__return_true' );
		$this->assertNotFalse( get_transient( Fast_Index_Log::STATS_TRANSIENT ), 'A scheduled task leaves the cache.' );

		Fast_Index_Log::add( $post_id, get_permalink( $post_id ), 200, 'URL_UPDATED' );
		$this->assertFalse( get_transient( Fast_Index_Log::STATS_TRANSIENT ), 'An admin action shows at once.' );
		$this->assertSame( 300, Fast_Index_Log::STATS_TTL );
	}

	/**
	 * PERF-FI-03: the premium card takes the accepted addresses from the cached History counters.
	 *
	 * @return void
	 */
	public function test_index_card_reuses_the_history_counter() {
		$this->with_premium();

		$this->assertSame( 7, Fast_Index_Premium_Inspection::counts( time() - 30 * DAY_IN_SECONDS, 7 )['accepted'] );
	}

	/**
	 * PERF-FI-04: without a daily limit the hourly old-post batch makes no query.
	 *
	 * @return void
	 */
	public function test_old_post_batch_without_a_limit_makes_no_query() {
		global $wpdb;

		$this->with_premium();
		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'       => array( 'post' => '1' ),
				'old_post_number' => 0,
			)
		);
		Fast_Index_Settings::get();

		$before = $wpdb->num_queries;
		$this->assertSame( array(), Fast_Index_Premium_Backlog::run() );
		$this->assertSame( $before, $wpdb->num_queries );
	}

	/**
	 * PERF-FI-05: checks due for more than three days are dropped; newer ones stay.
	 *
	 * @return void
	 */
	public function test_stale_index_checks_are_dropped() {
		$this->with_premium();

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::clear_queue();
		$old   = Fast_Index_Log::add( $post_id, get_permalink( $post_id ), 200, 'URL_UPDATED' );
		$fresh = Fast_Index_Log::add( 0, 'https://example.org/fresh/', 200, 'URL_UPDATED' );
		update_post_meta( $old, Fast_Index_Premium_Inspection::META_DUE, time() - 4 * DAY_IN_SECONDS );
		update_post_meta( $fresh, Fast_Index_Premium_Inspection::META_DUE, time() - DAY_IN_SECONDS );

		$this->assertSame( 1, Fast_Index_Premium_Inspection::drop_stale() );
		$this->assertSame( '', get_post_meta( $old, Fast_Index_Premium_Inspection::META_DUE, true ) );
		$this->assertNotSame( '', get_post_meta( $fresh, Fast_Index_Premium_Inspection::META_DUE, true ) );
		$this->assertSame( 0, Fast_Index_Premium_Inspection::drop_stale() );
	}

	/**
	 * PERF-FI-07: a working key does not rewrite its status on every accepted notification; an old rest is
	 * forgotten once.
	 *
	 * @return void
	 */
	public function test_working_key_writes_its_status_once() {
		$id = $this->add_account();
		$this->fake_google();

		$submitter = new Fast_Index_Submitter();
		$this->assertSame( 200, $submitter->submit( 'https://example.org/a/', 'URL_UPDATED' ) );
		$this->assertFalse( get_transient( Fast_Index_Accounts::STATUS_TRANSIENT_PREFIX . $id ) );
		$this->assertSame( 200, Fast_Index_Accounts::statuses()[ $id ] );

		$writes = 0;
		$count  = function () use ( &$writes ) {
			++$writes;
		};
		add_action( 'setted_transient', $count );
		add_action( 'updated_option', $count );
		$this->assertSame( 200, $submitter->submit( 'https://example.org/b/', 'URL_UPDATED' ) );
		remove_action( 'setted_transient', $count );
		remove_action( 'updated_option', $count );
		$this->assertSame( 0, $writes, 'No status write for a key that keeps working.' );

		// A rest that ended (the transient outlived it) is forgotten after the next success.
		set_transient(
			Fast_Index_Accounts::STATUS_TRANSIENT_PREFIX . $id,
			array(
				'code'  => 403,
				'until' => time() - 1,
			),
			HOUR_IN_SECONDS
		);
		$this->assertSame( 200, $submitter->submit( 'https://example.org/c/', 'URL_UPDATED' ) );
		$this->assertFalse( get_transient( Fast_Index_Accounts::STATUS_TRANSIENT_PREFIX . $id ) );
	}

	/**
	 * PERF-FI-10: on the dashboard, only administrators make Fast Index look for its notices.
	 *
	 * @return void
	 */
	public function test_dashboard_notice_options_are_read_for_administrators_only() {
		update_option( 'fast_index_notice_key_rotation', '1', false );
		$read  = 0;
		$count = function ( $value ) use ( &$read ) {
			++$read;
			return $value;
		};
		add_filter( 'option_fast_index_notice_key_rotation', $count );

		$this->login_as( 'editor' );
		( new Fast_Index_Admin() )->enqueue_assets( 'index.php' );
		$this->assertSame( 0, $read );
		$this->assertFalse( wp_script_is( 'fast-index-admin', 'enqueued' ) );

		$this->login_as( 'administrator' );
		( new Fast_Index_Admin() )->enqueue_assets( 'index.php' );
		$this->assertSame( 1, $read );
		$this->assertTrue( wp_script_is( 'fast-index-admin', 'enqueued' ) );

		remove_filter( 'option_fast_index_notice_key_rotation', $count );
		wp_dequeue_script( 'fast-index-admin' );
	}

	/**
	 * SEC-FI-07: the key file answer sends nosniff and a short cache time.
	 *
	 * @return void
	 */
	public function test_key_file_headers_are_in_the_code() {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-fast-index-indexnow.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local file in a test.

		$this->assertStringContainsString( "header( 'X-Content-Type-Options: nosniff' );", $source );
		$this->assertStringContainsString( "header( 'Cache-Control: public, max-age=300' );", $source );
	}
}
