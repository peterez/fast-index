<?php
/**
 * Premium index status (FI-R06): URL Inspection API, property, daily counter, automatic checks, History card.
 *
 * @package FastIndex
 */

/**
 * Fast_Index_Premium_Inspection with a fake Search Console.
 */
class Fast_Index_Test_Inspection extends Fast_Index_Test_Case {

	/**
	 * Answer of sites.list: siteEntry list, or an HTTP code with an error body.
	 *
	 * @var array|int
	 */
	private $sites = array();

	/**
	 * Answers of index:inspect, in order; the last one repeats. Each: an array body (200) or an HTTP code.
	 *
	 * @var array
	 */
	private $inspections = array();

	/**
	 * Requests to Search Console.
	 *
	 * @var array[]
	 */
	private $console = array();

	/**
	 * Premium module, a key, the fake Google endpoints and a fake Search Console.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->with_premium();
		$this->add_account();
		$this->fake_google();
		$this->sites       = array(
			array(
				'siteUrl'         => 'sc-domain:' . wp_parse_url( home_url(), PHP_URL_HOST ),
				'permissionLevel' => 'siteOwner',
			),
		);
		$this->inspections = array( self::verdict( 'PASS', 'Submitted and indexed' ) );
		add_filter( 'pre_http_request', array( $this, 'answer_console' ), 9, 3 );
	}

	/**
	 * A successful inspection answer.
	 *
	 * @param string $verdict  Verdict.
	 * @param string $coverage Coverage state.
	 * @return array
	 */
	private static function verdict( $verdict, $coverage ) {
		return array(
			'inspectionResult' => array(
				'inspectionResultLink' => 'https://search.google.com/search-console/inspect?resource_id=x',
				'indexStatusResult'    => array(
					'verdict'       => $verdict,
					'coverageState' => $coverage,
					'lastCrawlTime' => '2026-10-14T15:01:23Z',
				),
			),
		);
	}

	/**
	 * Fake Search Console.
	 *
	 * @param false|array $response Short-circuit value.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return false|array
	 */
	public function answer_console( $response, $args, $url ) {
		if ( Fast_Index_Premium_Inspection::SITES_URL === $url ) {
			$this->console[] = array(
				'url'  => $url,
				'args' => $args,
			);

			return is_int( $this->sites ) ? self::http_response(
				$this->sites,
				array(
					'error' => array(
						'code'   => $this->sites,
						'status' => 'PERMISSION_DENIED',
					),
				)
			) : self::http_response( 200, array( 'siteEntry' => $this->sites ) );
		}

		if ( Fast_Index_Premium_Inspection::INSPECT_URL === $url ) {
			$this->console[] = array(
				'url'  => $url,
				'args' => $args,
			);
			$answer          = count( $this->inspections ) > 1 ? array_shift( $this->inspections ) : $this->inspections[0];

			if ( is_array( $answer ) ) {
				return self::http_response( 200, $answer );
			}

			$body = array(
				'error' => array(
					'code'    => $answer,
					'message' => 'Fake',
					'status'  => 'PERMISSION_DENIED',
				),
			);

			if ( 4031 === $answer ) {
				$answer                   = 403;
				$body['error']['code']    = 403;
				$body['error']['details'] = array(
					array(
						'@type'  => 'type.googleapis.com/google.rpc.ErrorInfo',
						'reason' => 'SERVICE_DISABLED',
					),
				);
			}

			return self::http_response( $answer, $body );
		}

		return $response;
	}

	/**
	 * Requests to one Search Console endpoint.
	 *
	 * @param string $url Endpoint.
	 * @return array[]
	 */
	private function console_to( $url ) {
		return array_values(
			array_filter(
				$this->console,
				function ( $request ) use ( $url ) {
					return $url === $request['url'];
				}
			)
		);
	}

	/**
	 * An accepted Google History entry of a new post.
	 *
	 * @param string $slug Post slug.
	 * @return int History entry ID.
	 */
	private static function accepted( $slug = 'inspected' ) {
		$post = self::factory()->post->create( array( 'post_name' => $slug ) );

		return Fast_Index_Log::add( $post, get_permalink( $post ), 200, 'URL_UPDATED' );
	}

	/**
	 * The Check button: the main key asks with the read-only scope, the property is found once, the result is stored
	 * and shown, and the request carries the address and the property.
	 *
	 * @return void
	 */
	public function test_check_inspects_and_stores_the_result() {
		$log_id = self::accepted();
		$result = Fast_Index_Premium_Inspection::inspect( $log_id );

		$this->assertIsArray( $result );
		$this->assertSame( 'indexed', $result['state'] );
		$this->assertSame( 'Submitted and indexed', $result['coverage'] );
		$this->assertSame( strtotime( '2026-10-14T15:01:23Z' ), $result['last_crawl'] );
		$this->assertSame( 'indexed', get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_STATE, true ) );

		$token  = $this->requests_to( Fast_Index_Google_Client::TOKEN_URL );
		$claims = json_decode( base64_decode( strtr( explode( '.', end( $token )['args']['body']['assertion'] )[1], '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reads a JWT in a test.
		$this->assertSame( Fast_Index_Premium_Inspection::SCOPE, $claims['scope'] );

		$inspect = $this->console_to( Fast_Index_Premium_Inspection::INSPECT_URL );
		$this->assertCount( 1, $inspect );
		$body = json_decode( $inspect[0]['args']['body'], true );
		$this->assertSame( get_post_field( 'post_content', $log_id ), $body['inspectionUrl'] );
		$this->assertSame( 'sc-domain:' . wp_parse_url( home_url(), PHP_URL_HOST ), $body['siteUrl'] );
		$this->assertSame( 'POST', $inspect[0]['args']['method'] );
		$this->assertStringStartsWith( 'Bearer ', $inspect[0]['args']['headers']['Authorization'] );

		// The property is remembered: a second check asks sites.list no more.
		Fast_Index_Premium_Inspection::inspect( $log_id );
		$this->assertCount( 1, $this->console_to( Fast_Index_Premium_Inspection::SITES_URL ) );
		$this->assertSame( 2, Fast_Index_Premium_Inspection::usage( $body['siteUrl'] )['all'] );

		$this->login_as( 'administrator' );
		ob_start();
		( new Fast_Index_Admin() )->render_history();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'data-colname="Index Status"', $html );
		$this->assertStringContainsString( 'fi-index-state--indexed', $html );
		$this->assertStringContainsString( 'Check again', $html );
		$this->assertStringContainsString( 'name="action" value="fast_index_inspect"', $html );
		$this->assertStringContainsString( 'Open in Search Console', $html );
	}

	/**
	 * The property: the one on the settings screen, else a Domain property of the host, else the longest URL-prefix
	 * property the site starts with; unverified entries never count.
	 *
	 * @return void
	 */
	public function test_property_choice() {
		$home = 'https://www.example.com/blog/';

		$this->assertSame(
			'sc-domain:example.com',
			Fast_Index_Premium_Inspection::best_property(
				array(
					array(
						'siteUrl'         => 'https://www.example.com/',
						'permissionLevel' => 'siteFullUser',
					),
					array(
						'siteUrl'         => 'sc-domain:example.com',
						'permissionLevel' => 'siteRestrictedUser',
					),
				),
				$home
			)
		);
		$this->assertSame(
			'https://www.example.com/blog/',
			Fast_Index_Premium_Inspection::best_property(
				array(
					array(
						'siteUrl'         => 'https://www.example.com/',
						'permissionLevel' => 'siteOwner',
					),
					array(
						'siteUrl'         => 'https://www.example.com/blog/',
						'permissionLevel' => 'siteOwner',
					),
					array(
						'siteUrl'         => 'sc-domain:other.com',
						'permissionLevel' => 'siteOwner',
					),
				),
				$home
			)
		);
		$this->assertSame(
			'',
			Fast_Index_Premium_Inspection::best_property(
				array(
					array(
						'siteUrl'         => 'sc-domain:example.com',
						'permissionLevel' => 'siteUnverifiedUser',
					),
					array(
						'siteUrl'         => 'http://www.example.com/',
						'permissionLevel' => 'siteOwner',
					),
				),
				$home
			)
		);

		update_option( Fast_Index_Settings::OPTION, array( 'sc_property' => 'https://www.example.com/blog' ) );
		$this->assertSame( 'https://www.example.com/blog/', Fast_Index_Settings::get()['sc_property'] );
		Fast_Index_Premium_Inspection::inspect( self::accepted() );
		$this->assertCount( 0, $this->console_to( Fast_Index_Premium_Inspection::SITES_URL ), 'A set property is used as it is.' );
		$this->assertSame( 'https://www.example.com/blog/', json_decode( $this->console_to( Fast_Index_Premium_Inspection::INSPECT_URL )[0]['args']['body'], true )['siteUrl'] );
	}

	/**
	 * Errors say what to do: no property for the service account, not a user of the property, API not enabled.
	 *
	 * @return void
	 */
	public function test_errors_explain_the_fix() {
		$log_id = self::accepted();

		$this->sites = array();
		$error       = Fast_Index_Premium_Inspection::inspect( $log_id );
		$this->assertWPError( $error );
		$this->assertStringContainsString( 'Add the service account to the property in Search Console', $error->get_error_message() );
		$this->assertCount( 0, $this->console_to( Fast_Index_Premium_Inspection::INSPECT_URL ) );

		update_option( Fast_Index_Settings::OPTION, array( 'sc_property' => 'sc-domain:example.org' ) );
		$this->inspections = array( 403 );
		$error             = Fast_Index_Premium_Inspection::inspect( $log_id );
		$this->assertStringContainsString( 'Search Console refused the check for sc-domain:example.org', $error->get_error_message() );

		$this->inspections = array( 4031 );
		$error             = Fast_Index_Premium_Inspection::inspect( $log_id );
		$this->assertStringContainsString( 'The Google Search Console API is not enabled', $error->get_error_message() );

		$this->assertSame( '', get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_STATE, true ), 'Nothing is stored for a failed check.' );
	}

	/**
	 * The daily counter of a property stops at 2,000; the automatic checks stop at half of it.
	 *
	 * @return void
	 */
	public function test_daily_counter_stops_at_the_quota() {
		$property = 'sc-domain:example.org';
		update_option( Fast_Index_Settings::OPTION, array( 'sc_property' => $property ) );
		$date = new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) );

		update_option(
			Fast_Index_Premium_Inspection::USAGE_OPTION,
			array(
				'day'    => $date->format( 'Y-m-d' ),
				'counts' => array(
					$property => array(
						'all'  => 1000,
						'auto' => 1000,
					),
				),
			)
		);

		$log_id = self::accepted();
		$this->assertWPError( Fast_Index_Premium_Inspection::inspect( $log_id, true ), 'Automatic checks use at most half.' );
		$this->assertIsArray( Fast_Index_Premium_Inspection::inspect( $log_id ), 'The Check button may use the rest.' );

		update_option(
			Fast_Index_Premium_Inspection::USAGE_OPTION,
			array(
				'day'    => $date->format( 'Y-m-d' ),
				'counts' => array(
					$property => array(
						'all'  => 2000,
						'auto' => 0,
					),
				),
			)
		);

		$requests = count( $this->console );
		$error    = Fast_Index_Premium_Inspection::inspect( $log_id );
		$this->assertWPError( $error );
		$this->assertStringContainsString( '2,000', $error->get_error_message() );
		$this->assertCount( $requests, $this->console, 'No request after the quota.' );

		// A new Pacific day starts from 0.
		update_option(
			Fast_Index_Premium_Inspection::USAGE_OPTION,
			array(
				'day'    => '2000-01-01',
				'counts' => array( $property => array( 'all' => 2000 ) ),
			)
		);
		$this->assertSame( 0, Fast_Index_Premium_Inspection::usage( $property )['all'] );
	}

	/**
	 * Automatic checks: 24 hours after the acceptance, again after 72 hours only if not indexed, never more than twice.
	 *
	 * @return void
	 */
	public function test_automatic_checks_run_at_most_twice() {
		$log_id = self::accepted();
		$due    = (int) get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_DUE, true );

		$this->assertGreaterThanOrEqual( time() + DAY_IN_SECONDS - 5, $due );
		$this->assertSame( 0, Fast_Index_Premium_Inspection::run_due(), 'Not before 24 hours.' );

		// 24 hours later: crawled, not indexed.
		$this->inspections = array( self::verdict( 'NEUTRAL', 'Crawled - currently not indexed' ) );
		update_post_meta( $log_id, Fast_Index_Premium_Inspection::META_DUE, time() - 1 );
		do_action( Fast_Index_Cron::HOURLY_HOOK );

		$this->assertSame( 'crawled', get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_STATE, true ) );
		$this->assertSame( 1, (int) get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_RUNS, true ) );
		$sent = (int) strtotime( get_post_field( 'post_date_gmt', $log_id ) . ' UTC' );
		$this->assertSame( $sent + 3 * DAY_IN_SECONDS, (int) get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_DUE, true ) );

		// 72 hours: the second and last check.
		update_post_meta( $log_id, Fast_Index_Premium_Inspection::META_DUE, time() - 1 );
		$this->assertSame( 1, Fast_Index_Premium_Inspection::run_due() );
		$this->assertSame( 2, (int) get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_RUNS, true ) );
		$this->assertSame( '', get_post_meta( $log_id, Fast_Index_Premium_Inspection::META_DUE, true ) );
		$this->assertSame( 0, Fast_Index_Premium_Inspection::run_due() );
		$this->assertCount( 2, $this->console_to( Fast_Index_Premium_Inspection::INSPECT_URL ) );

		// Indexed at the first check: no second one.
		$other             = self::accepted( 'other' );
		$this->inspections = array( self::verdict( 'PASS', 'Submitted and indexed' ) );
		update_post_meta( $other, Fast_Index_Premium_Inspection::META_DUE, time() - 1 );
		Fast_Index_Premium_Inspection::run_due();
		$this->assertSame( '', get_post_meta( $other, Fast_Index_Premium_Inspection::META_DUE, true ) );

		// A newer acceptance of the same post takes over the waiting check.
		$post  = (int) get_post_field( 'post_parent', $other );
		$newer = Fast_Index_Log::add( $post, get_permalink( $post ), 200, 'URL_UPDATED' );
		update_post_meta( $other, Fast_Index_Premium_Inspection::META_DUE, time() + 10 );
		Fast_Index_Log::add( $post, get_permalink( $post ), 200, 'URL_UPDATED' );
		$this->assertSame( '', get_post_meta( $other, Fast_Index_Premium_Inspection::META_DUE, true ) );
		$this->assertSame( '', get_post_meta( $newer, Fast_Index_Premium_Inspection::META_DUE, true ) );
	}

	/**
	 * The card counts the same History entries: accepted addresses of 30 days, checked ones, indexed ones.
	 *
	 * @return void
	 */
	public function test_card_numbers_come_from_history() {
		$indexed = self::accepted( 'indexed-one' );
		$crawled = self::accepted( 'crawled-one' );
		self::accepted( 'unchecked-one' );

		Fast_Index_Premium_Inspection::inspect( $indexed );
		$this->inspections = array( self::verdict( 'NEUTRAL', 'Crawled - currently not indexed' ) );
		Fast_Index_Premium_Inspection::inspect( $crawled );

		$this->assertSame(
			array(
				'accepted' => 3,
				'checked'  => 2,
				'indexed'  => 1,
			),
			Fast_Index_Premium_Inspection::counts( time() - 30 * DAY_IN_SECONDS )
		);

		$this->login_as( 'administrator' );
		ob_start();
		( new Fast_Index_Admin() )->render_history();
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<dt[^>]*>Accepted by Google, last 30 days<\/dt><dd[^>]*>3<\/dd>.*<dt[^>]*>Checked in Search Console<\/dt><dd[^>]*>2<\/dd>.*<dt[^>]*>Indexed<\/dt><dd[^>]*>1<\/dd>/s', $html );
	}

	/**
	 * The Check handler checks capability and nonce before it asks Google.
	 *
	 * @return void
	 */
	public function test_check_handler_needs_capability_and_nonce() {
		$this->catch_redirects();
		$log_id                  = self::accepted();
		$_POST['fast_index_log'] = $log_id;

		$this->login_as( 'editor' );
		try {
			Fast_Index_Premium_Inspection::handle_check();
			$this->fail( 'An editor checked an address.' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->login_as( 'administrator' );
		try {
			Fast_Index_Premium_Inspection::handle_check();
			$this->fail( 'Checked without a nonce.' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertCount( 0, $this->console );

		self::set_nonce( Fast_Index_Premium_Inspection::CHECK_ACTION );
		try {
			Fast_Index_Premium_Inspection::handle_check();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$messages = Fast_Index_Admin::take_flash();
		$this->assertSame( 'success', $messages[0]['type'] );
		$this->assertStringContainsString( ': Indexed.', $messages[0]['message'] );
	}
}
