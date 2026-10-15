<?php
/**
 * SEC-FI-05: History keeps a limited time; a daily event removes older entries.
 *
 * @package FastIndex
 */

/**
 * Fast_Index_Log::prune() and its daily event.
 */
class Fast_Index_Test_Prune extends Fast_Index_Test_Case {

	/**
	 * Adds a History entry dated some days ago.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $code    Answer.
	 * @param int    $days    Age in days.
	 * @param string $channel One of the CHANNEL_* constants.
	 * @return int Entry ID.
	 */
	private static function entry( $post_id, $code, $days, $channel = Fast_Index_Log::CHANNEL_GOOGLE ) {
		$url = $post_id ? get_permalink( $post_id ) : 'https://example.org/gone/';
		$id  = Fast_Index_Log::CHANNEL_INDEXNOW === $channel
			? Fast_Index_Log::add_indexnow( $post_id, $url, $code, 'URL_UPDATED' )
			: Fast_Index_Log::add( $post_id, $url, $code, 'URL_UPDATED' );
		$gmt = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		wp_update_post(
			array(
				'ID'            => $id,
				'post_date'     => get_date_from_gmt( $gmt ),
				'post_date_gmt' => $gmt,
			)
		);

		return $id;
	}

	/**
	 * Whether an entry still exists.
	 *
	 * @param int $id Entry ID.
	 * @return bool
	 */
	private static function exists( $id ) {
		clean_post_cache( $id );

		return get_post( $id ) instanceof WP_Post;
	}

	/**
	 * Old entries go; the newest accepted entry of each post, waiting entries and recent entries stay.
	 *
	 * @return void
	 */
	public function test_prune_removes_old_entries_and_keeps_what_the_counters_need() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$other   = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::clear_queue();

		$old_sent    = self::entry( $post_id, 200, 200 );
		$newer_sent  = self::entry( $post_id, 200, 120 );
		$old_failed  = self::entry( $post_id, 429, 100 );
		$recent_sent = self::entry( $other, 200, 10 );
		$waiting     = self::entry( $other, Fast_Index_Submitter::RESTING, 95 );
		$no_post     = self::entry( 0, 200, 95 );
		$indexnow    = self::entry( $post_id, 200, 91, Fast_Index_Log::CHANNEL_INDEXNOW );
		$new_now     = self::entry( $post_id, 200, 1, Fast_Index_Log::CHANNEL_INDEXNOW );
		Fast_Index_Log::hold( $waiting, time() );
		update_post_meta( $old_sent, '_fast_index_index_state', 'indexed' );

		$this->assertSame( 0, Fast_Index_Log::count_waiting( Fast_Index_Settings::get() ) );

		$this->assertSame( 4, Fast_Index_Log::prune() );

		$this->assertFalse( self::exists( $old_sent ), 'An older accepted entry of the same post goes.' );
		$this->assertSame( '', get_post_meta( $old_sent, '_fast_index_index_state', true ), 'Its meta goes with it.' );
		$this->assertTrue( self::exists( $newer_sent ), 'The newest accepted entry of a post stays, however old.' );
		$this->assertFalse( self::exists( $old_failed ) );
		$this->assertTrue( self::exists( $recent_sent ) );
		$this->assertTrue( self::exists( $waiting ), 'An entry that waits for a key stays.' );
		$this->assertFalse( self::exists( $no_post ) );
		$this->assertFalse( self::exists( $indexnow ) );
		$this->assertTrue( self::exists( $new_now ) );

		Fast_Index_Log::flush_stats();
		$this->assertSame( 0, Fast_Index_Log::count_waiting( Fast_Index_Settings::get() ), 'No post waits again after the clean-up.' );
		$this->assertSame( 0, Fast_Index_Log::prune(), 'A second run has nothing to do.' );
	}

	/**
	 * The filter sets the number of days; 0 keeps everything.
	 *
	 * @return void
	 */
	public function test_retention_filter() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::clear_queue();
		$failed = self::entry( $post_id, 429, 40 );

		$this->assertSame( 90, Fast_Index_Log::retention_days() );
		$this->assertSame( 0, Fast_Index_Log::prune() );

		$keep_forever = function () {
			return 0;
		};
		add_filter( 'fast_index_history_retention_days', $keep_forever );
		$this->assertSame( 0, Fast_Index_Log::prune( time() + 10 * YEAR_IN_SECONDS ) );
		$this->assertTrue( self::exists( $failed ) );
		remove_filter( 'fast_index_history_retention_days', $keep_forever );

		$thirty = function () {
			return 30;
		};
		add_filter( 'fast_index_history_retention_days', $thirty );
		$this->assertSame( 1, Fast_Index_Log::prune() );
		$this->assertFalse( self::exists( $failed ) );
		remove_filter( 'fast_index_history_retention_days', $thirty );
	}

	/**
	 * The daily event is planned on activation and by the hourly check, removed on deactivation, and runs prune().
	 *
	 * @return void
	 */
	public function test_daily_event() {
		Fast_Index_Cron::unschedule();
		$this->assertFalse( wp_next_scheduled( Fast_Index_Cron::PRUNE_HOOK ) );

		( new Fast_Index_Cron() )->run_hourly();
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::PRUNE_HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( Fast_Index_Cron::PRUNE_HOOK ) );

		Fast_Index_Cron::unschedule();
		Fast_Index_Cron::schedule();
		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::PRUNE_HOOK ) );
		$this->assertSame( 10, has_action( Fast_Index_Cron::PRUNE_HOOK, array( 'Fast_Index_Log', 'prune' ) ) );
		$this->assertContains( Fast_Index_Cron::PRUNE_HOOK, Fast_Index_Uninstaller::cron_hooks() );
	}

	/**
	 * The History help tab says how long History keeps its entries.
	 *
	 * @return void
	 */
	public function test_history_help_names_the_retention() {
		$help = Fast_Index_Help_Content::screen_help( 'history' );

		$this->assertStringContainsString( 'History keeps the entries of the last 90 days', $help['content'] );
		$this->assertStringNotContainsString( 'History keeps', Fast_Index_Help_Content::screen_help( 'settings' )['content'] );
	}
}
