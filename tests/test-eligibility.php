<?php
/**
 * Google eligibility (FI-R07): JobPosting or BroadcastEvent in the saved content, History mark, the option that
 * sends only those pages to Google.
 *
 * @package FastIndex
 */

/**
 * Fast_Index_Eligibility and its use by the notifier, History and the old-post batches.
 */
class Fast_Index_Test_Eligibility extends Fast_Index_Test_Case {

	/**
	 * Requests to IndexNow.
	 *
	 * @var int
	 */
	private $indexnow = 0;

	/**
	 * Content of a job posting page (JSON-LD as SEO and job plugins save it in the content).
	 */
	const JOB = '<p>We are hiring.</p><script type="application/ld+json">{"@context":"https://schema.org/","@type":"JobPosting","title":"Editor","datePosted":"2026-10-01"}</script>';

	/**
	 * Answers IndexNow and counts the requests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		// JSON-LD scripts stay in the content only for users who may post unfiltered HTML.
		$this->login_as( 'administrator' );
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				if ( Fast_Index_IndexNow::ENDPOINT !== $url ) {
					return $response;
				}
				++$this->indexnow;
				return self::http_response( 200, '' );
			},
			9,
			3
		);
	}

	/**
	 * The types are found in JSON-LD (plain, @graph, @type lists, nested) and microdata; a mere word is not enough.
	 *
	 * @return void
	 */
	public function test_types_in_content() {
		$this->assertSame( array( 'JobPosting' ), Fast_Index_Eligibility::types_in( self::JOB ) );
		$this->assertSame( array( 'JobPosting' ), Fast_Index_Eligibility::types_in( '<script type=\'application/ld+json\'>{"@graph":[{"@type":"Organization"},{"@type":["Thing","JobPosting"]}]}</script>' ) );
		$this->assertSame( array( 'BroadcastEvent' ), Fast_Index_Eligibility::types_in( '<script type="application/ld+json">{"@type":"VideoObject","name":"Live","publication":[{"@type":"BroadcastEvent","isLiveBroadcast":true}]}</script>' ) );
		$this->assertSame( array( 'JobPosting' ), Fast_Index_Eligibility::types_in( '<div itemscope itemtype="https://schema.org/JobPosting"><h2 itemprop="title">Editor</h2></div>' ) );
		$this->assertSame( array(), Fast_Index_Eligibility::types_in( '<p>Our JobPosting guide explains the BroadcastEvent type.</p>' ) );
		$this->assertSame( array(), Fast_Index_Eligibility::types_in( '<script type="application/ld+json">{"@type":"Article","headline":"JobPosting tips"}</script>' ) );
		$this->assertSame( array(), Fast_Index_Eligibility::types_in( '<script type="application/ld+json">{broken JobPosting</script>' ) );
		$this->assertSame( array(), Fast_Index_Eligibility::types_in( '' ) );

		$job = self::factory()->post->create( array( 'post_content' => self::JOB ) );
		$this->assertTrue( Fast_Index_Eligibility::is_eligible( $job ) );
		$this->assertFalse( Fast_Index_Eligibility::is_eligible( self::factory()->post->create() ) );

		add_filter( 'fast_index_is_eligible', '__return_true' );
		$this->assertTrue( Fast_Index_Eligibility::is_eligible( self::factory()->post->create() ), 'Extensions can add data printed elsewhere.' );
	}

	/**
	 * History marks the entry of a job page and not the entry of a plain post; no request is made for the check.
	 *
	 * @return void
	 */
	public function test_history_marks_eligible_entries() {
		$this->login_as( 'administrator' );
		$job   = self::factory()->post->create( array( 'post_content' => self::JOB, 'post_title' => 'Job page' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test fixture.
		$plain = self::factory()->post->create( array( 'post_title' => 'Plain post' ) );

		Fast_Index_Log::add( $job, get_permalink( $job ), 200, 'URL_UPDATED' );
		Fast_Index_Log::add( $plain, get_permalink( $plain ), 200, 'URL_UPDATED' );

		ob_start();
		( new Fast_Index_Admin() )->render_history();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, 'class="fi-eligible"' ) );
		$this->assertMatchesRegularExpression( '/Job page<\/a>\s*<\/strong>\s*<span class="fi-eligible">Eligible for Google Indexing API<\/span>/', $html );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * With "Supported Pages Only" a plain post does not go to Google but still goes to IndexNow; a job page goes to
	 * both. Total Waiting counts only pages that can wait for Google.
	 *
	 * @return void
	 */
	public function test_only_supported_pages_go_to_google() {
		$this->add_account();
		$this->fake_google();
		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'     => array( 'post' => '1' ),
				'post_status'   => array( 'publish' => '1' ),
				'indexnow'      => '1',
				'eligible_only' => '1',
			)
		);

		$plain = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$job   = self::factory()->post->create( array( 'post_status' => 'publish', 'post_content' => self::JOB ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test fixture.

		$this->assertSame( 1, Fast_Index_Log::count_waiting( Fast_Index_Settings::get() ), 'Only the job page waits for Google.' );

		foreach ( array( $plain, $job ) as $id ) {
			do_action( Fast_Index_Cron::SUBMIT_HOOK, get_permalink( $id ), 'URL_UPDATED', $id );
		}

		$publish = $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL );
		$this->assertCount( 1, $publish );
		$this->assertSame( get_permalink( $job ), json_decode( $publish[0]['args']['body'], true )['url'] );
		$this->assertSame( 2, $this->indexnow, 'IndexNow gets both.' );
		$this->assertSame( array(), get_posts( array( 'post_type' => Fast_Index_Log::POST_TYPE, 'post_parent' => $plain, 'post_status' => 'any' ) ), 'No Google entry for the plain post.' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test query.

		// Off: every page goes to Google again.
		update_option( Fast_Index_Settings::OPTION, array( 'post_type' => array( 'post' => '1' ) ) );
		do_action( Fast_Index_Cron::SUBMIT_HOOK, get_permalink( $plain ), 'URL_UPDATED', $plain );
		$this->assertCount( 2, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
	}

	/**
	 * Premium: the old-post batches pick only supported pages while the option is on.
	 *
	 * @return void
	 */
	public function test_old_post_batches_respect_the_option() {
		$this->with_premium();
		$plain = self::factory()->post->create();
		$word  = self::factory()->post->create( array( 'post_content' => '<p>What is a JobPosting?</p>' ) );
		$job   = self::factory()->post->create( array( 'post_content' => self::JOB ) );
		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'       => array( 'post' => '1' ),
				'old_post_number' => 30,
				'eligible_only'   => '1',
			)
		);

		$ids = Fast_Index_Premium_Backlog::ids( Fast_Index_Settings::get(), 10 );
		$this->assertContains( $job, $ids );
		$this->assertContains( $word, $ids, 'The SQL filter finds the word; the PHP check decides.' );
		$this->assertNotContains( $plain, $ids );

		$this->add_account();
		$this->fake_google();
		$method = new ReflectionMethod( 'Fast_Index_Premium_Backlog', 'send' );
		$method->setAccessible( true );
		$results = $method->invoke( null, array( $word, $job ) );

		$this->assertSame( array( $job ), wp_list_pluck( $results, 'post_id' ) );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
	}

	/**
	 * WP Job Manager listings count when that plugin builds JobPosting data for them.
	 *
	 * @return void
	 */
	public function test_wp_job_manager_listings() {
		require_once __DIR__ . '/stub-wp-job-manager.php';

		register_post_type( 'job_listing', array( 'public' => true ) );
		$listing = self::factory()->post->create( array( 'post_type' => 'job_listing', 'post_title' => 'Editor' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test fixture.
		$empty   = self::factory()->post->create( array( 'post_type' => 'job_listing', 'post_title' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test fixture.

		$this->assertTrue( Fast_Index_Eligibility::is_eligible( $listing ) );
		$this->assertFalse( Fast_Index_Eligibility::is_eligible( $empty ) );
		$this->assertFalse( Fast_Index_Eligibility::is_eligible( self::factory()->post->create( array( 'post_title' => 'Editor' ) ) ), 'Only job listings.' );
		unregister_post_type( 'job_listing' );
	}
}
