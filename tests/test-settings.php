<?php
/**
 * Option sanitization and SQL building.
 *
 * @package FastIndex
 */

/**
 * Settings keys are allowlisted; stored values never reach SQL unprepared.
 */
class Fast_Index_Test_Settings extends Fast_Index_Test_Case {

	/**
	 * SQL statements seen by the query filter.
	 *
	 * @var string[]
	 */
	private $queries = array();

	/**
	 * Records SQL.
	 *
	 * @param string $sql Query.
	 * @return string
	 */
	public function record_query( $sql ) {
		$this->queries[] = $sql;

		return $sql;
	}

	/**
	 * Flag map keys that sanitize_key() would change are dropped, not rewritten.
	 *
	 * @return void
	 */
	public function test_normalize_drops_unclean_keys() {
		$clean = Fast_Index_Settings::normalize(
			array(
				'status'           => '9',
				'post_type'        => array(
					'post'                             => '1',
					"post' OR 1=1 -- "                 => '1',
					'Page'                             => '1',
					'a_post_type_name_too_long_for_wp' => '1',
					'book'                             => '0',
				),
				'post_status'      => array(
					'publish' => '1',
					'draft'   => '1',
				),
				'exclude_category' => array(
					'1 OR 1=1' => '1',
					'7'        => '7',
					-3         => '-3',
				),
				'old_post_number'  => '-5',
				'json_file'        => array( 'a' => 'b' ),
			)
		);

		$this->assertSame( Fast_Index_Settings::STATUS_ACTIVE, $clean['status'] );
		$this->assertSame( array( 'post' => '1' ), $clean['post_type'] );
		$this->assertSame( array( 'publish' => '1' ), $clean['post_status'] );
		$this->assertSame( array( 7 => 7 ), $clean['exclude_category'] );
		$this->assertSame( 5, $clean['old_post_number'] );
		$this->assertArrayNotHasKey( 'json_file', $clean );
	}

	/**
	 * Missing maps fall back to the 2.x defaults; empty maps stay empty.
	 *
	 * @return void
	 */
	public function test_normalize_defaults() {
		$this->assertSame( Fast_Index_Settings::defaults(), Fast_Index_Settings::normalize( array() ) );
		$this->assertSame( array(), Fast_Index_Settings::normalize( array( 'post_type' => array() ) )['post_type'] );
	}

	/**
	 * The form is checked against registered public post types and existing categories.
	 *
	 * @return void
	 */
	public function test_sanitize_input_allowlists_types_and_categories() {
		$category = self::factory()->category->create();

		$clean = Fast_Index_Settings::sanitize_input(
			array(
				'post_type'        => array(
					'post'       => '1',
					'page'       => '1',
					'attachment' => '1',
					'revision'   => '1',
				),
				'post_status'      => array(
					'edit'  => '1',
					'trash' => '1',
				),
				'exclude_category' => array(
					$category => $category,
					999999    => 999999,
				),
				'old_post_number'  => '50',
			),
			true
		);

		$this->assertSame(
			array(
				'post' => '1',
				'page' => '1',
			),
			$clean['post_type']
		);
		$this->assertSame( array( $category => $category ), $clean['exclude_category'] );
		$this->assertSame( 50, $clean['old_post_number'] );
	}

	/**
	 * The free plan keeps the 2.x limits.
	 *
	 * @return void
	 */
	public function test_free_plan_limits() {
		$clean = Fast_Index_Settings::apply_plan_limits(
			array(
				'status'           => '1',
				'post_type'        => array(
					'post' => '1',
					'page' => '1',
				),
				'post_status'      => array(
					'publish' => '1',
					'edit'    => '1',
				),
				'old_post_number'  => 20,
				'exclude_category' => array( 3 => 3 ),
				'delete_data'      => true,
			),
			false
		);

		$this->assertSame( array( 'post' => '1' ), $clean['post_type'] );
		$this->assertSame( array( 'publish' => '1' ), $clean['post_status'] );
		$this->assertSame( 0, $clean['old_post_number'] );
		$this->assertSame( array(), $clean['exclude_category'] );
		$this->assertTrue( $clean['delete_data'] );
	}

	/**
	 * Malicious values stored by 2.x (written without the sanitizer) never reach the SQL.
	 *
	 * @return void
	 */
	public function test_stored_injection_never_reaches_sql() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes the option without the 3.x sanitizer, the way 2.x could have stored it.
		$wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => Fast_Index_Settings::OPTION,
				'option_value' => maybe_serialize(
					array(
						'status'           => '1',
						'post_type'        => array(
							"post' OR '1'='1" => '1',
							'post'            => '1',
						),
						'exclude_category' => array( "1') OR ('1'='1" => '1' ),
						'old_post_number'  => '10',
					)
				),
				'autoload'     => 'yes',
			)
		);

		wp_cache_delete( Fast_Index_Settings::OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		add_filter( 'query', array( $this, 'record_query' ) );
		Fast_Index_Log::backlog_ids( Fast_Index_Settings::get(), 5 );
		Fast_Index_Log::count_waiting( Fast_Index_Settings::get() );
		remove_filter( 'query', array( $this, 'record_query' ) );

		$sql = implode( "\n", $this->queries );

		$this->assertStringNotContainsString( "OR '1'='1", $sql );
		$this->assertStringNotContainsString( "OR ('1'='1", $sql );
	}

	/**
	 * One placeholder is generated per post type and per excluded term.
	 *
	 * @return void
	 */
	public function test_backlog_sql_lists_every_value() {
		$category = self::factory()->category->create();
		$term     = get_term( $category, 'category' );

		$settings = Fast_Index_Settings::normalize(
			array(
				'post_type'        => array(
					'post' => '1',
					'page' => '1',
				),
				'exclude_category' => array( $category => $category ),
			)
		);

		add_filter( 'query', array( $this, 'record_query' ) );
		Fast_Index_Log::backlog_ids( $settings, 3 );
		remove_filter( 'query', array( $this, 'record_query' ) );

		$sql = end( $this->queries );

		$this->assertStringContainsString( "p.post_type IN ( 'post', 'page' )", $sql );
		$this->assertStringContainsString( 'tr.term_taxonomy_id IN ( ' . (int) $term->term_taxonomy_id . ' )', $sql );
		$this->assertStringContainsString( 'LIMIT 3', $sql );
	}

	/**
	 * The backlog skips sent posts, excluded categories and other post types.
	 *
	 * @return void
	 */
	public function test_backlog_selects_unsent_posts() {
		$excluded = self::factory()->category->create();
		$sent     = self::factory()->post->create();
		$fresh    = self::factory()->post->create();
		$skipped  = self::factory()->post->create( array( 'post_category' => array( $excluded ) ) );
		$failed   = self::factory()->post->create();
		$page     = self::factory()->post->create( array( 'post_type' => 'page' ) );

		Fast_Index_Log::add( $sent, 'https://example.org/sent/', 200, Fast_Index_Google_Client::TYPE_UPDATED );
		Fast_Index_Log::add( $failed, 'https://example.org/failed/', 429, Fast_Index_Google_Client::TYPE_UPDATED );

		$settings = Fast_Index_Settings::normalize(
			array(
				'post_type'        => array( 'post' => '1' ),
				'exclude_category' => array( $excluded => $excluded ),
			)
		);

		$ids = Fast_Index_Log::backlog_ids( $settings, 10 );

		$this->assertContains( $fresh, $ids );
		$this->assertContains( $failed, $ids, 'A failed submission must be retried.' );
		$this->assertNotContains( $sent, $ids );
		$this->assertNotContains( $skipped, $ids );
		$this->assertNotContains( $page, $ids );
		$this->assertSame( count( $ids ), Fast_Index_Log::count_waiting( $settings ) );
	}

	/**
	 * "Sent today" counts successful entries of the last 24 hours in UTC.
	 *
	 * @return void
	 */
	public function test_count_sent_since_uses_gmt() {
		$post = self::factory()->post->create();

		Fast_Index_Log::add( $post, 'https://example.org/a/', 200, Fast_Index_Google_Client::TYPE_UPDATED );
		Fast_Index_Log::add( $post, 'https://example.org/b/', 429, Fast_Index_Google_Client::TYPE_UPDATED );

		$old = Fast_Index_Log::add( $post, 'https://example.org/c/', 200, Fast_Index_Google_Client::TYPE_UPDATED );
		wp_update_post(
			array(
				'ID'            => $old,
				'post_date'     => '2001-01-01 00:00:00',
				'post_date_gmt' => '2001-01-01 00:00:00',
			)
		);

		$this->assertSame( 1, Fast_Index_Log::count_sent_since( time() - DAY_IN_SECONDS ) );
	}

	/**
	 * The batch size follows the 2.x rule and never exceeds what is left of the daily limit.
	 *
	 * @return void
	 */
	public function test_backlog_batch_size() {
		$this->assertSame( 0, Fast_Index_Backlog::batch_size( 0, 0 ) );
		$this->assertSame( 0, Fast_Index_Backlog::batch_size( 10, 10 ) );

		for ( $i = 0; $i < 20; $i++ ) {
			$size = Fast_Index_Backlog::batch_size( 30, 28 );
			$this->assertGreaterThanOrEqual( 0, $size );
			$this->assertLessThanOrEqual( 2, $size );
		}
	}
}
