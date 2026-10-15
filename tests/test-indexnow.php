<?php
/**
 * IndexNow (FI-R03): off by default, key and key file, one request per notification, History channel.
 *
 * @package FastIndex
 */

/**
 * Fast_Index_IndexNow, Fast_Index_Notifier and the IndexNow channel of History.
 */
class Fast_Index_Test_IndexNow extends Fast_Index_Test_Case {

	/**
	 * Every outgoing request of the test, whatever its host.
	 *
	 * @var array[]
	 */
	private $outgoing = array();

	/**
	 * Status code the fake IndexNow endpoint answers with; 0 answers with a connection error.
	 *
	 * @var int
	 */
	private $indexnow_code = 200;

	/**
	 * Records every request and answers IndexNow; Google is answered by the parent's fake when it is on.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'record' ), 1, 3 );
		add_filter( 'pre_http_request', array( $this, 'answer_indexnow' ), 9, 3 );
	}

	/**
	 * Records an outgoing request.
	 *
	 * @param false|array $response Short-circuit value.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return false|array
	 */
	public function record( $response, $args, $url ) {
		$this->outgoing[] = array(
			'url'  => $url,
			'args' => $args,
		);

		return $response;
	}

	/**
	 * Fake IndexNow endpoint.
	 *
	 * @param false|array $response Short-circuit value.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return false|array|WP_Error
	 */
	public function answer_indexnow( $response, $args, $url ) {
		if ( Fast_Index_IndexNow::ENDPOINT !== $url ) {
			return $response;
		}

		return 0 === $this->indexnow_code ? new WP_Error( 'http_request_failed', 'Timeout' ) : self::http_response( $this->indexnow_code, '' );
	}

	/**
	 * Outgoing requests to one URL.
	 *
	 * @param string $url URL.
	 * @return array[]
	 */
	private function outgoing_to( $url ) {
		return array_values(
			array_filter(
				$this->outgoing,
				function ( $request ) use ( $url ) {
					return $url === $request['url'];
				}
			)
		);
	}

	/**
	 * Turns IndexNow on or off, keeping the other settings.
	 *
	 * @param bool $on Whether IndexNow is on.
	 * @return void
	 */
	private static function indexnow( $on ) {
		$settings             = Fast_Index_Settings::get();
		$settings['indexnow'] = $on;
		Fast_Index_Settings::save( $settings );
	}

	/**
	 * Publishes a post and runs its queued notification, as WP-Cron would.
	 *
	 * @return array Keys: post_id, url.
	 */
	private function publish_and_run() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$url     = get_permalink( $post_id );
		$args    = array( $url, 'URL_UPDATED', $post_id );

		$this->assertNotFalse( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, $args ) );
		wp_unschedule_event( wp_next_scheduled( Fast_Index_Cron::SUBMIT_HOOK, $args ), Fast_Index_Cron::SUBMIT_HOOK, $args );
		do_action_ref_array( Fast_Index_Cron::SUBMIT_HOOK, $args );

		return array(
			'post_id' => $post_id,
			'url'     => $url,
		);
	}

	/**
	 * IndexNow entries of History.
	 *
	 * @return array[] Keys: status, code, url.
	 */
	private static function indexnow_entries() {
		$entries = array();

		foreach ( get_posts(
			array(
				'post_type'   => Fast_Index_Log::INDEXNOW_POST_TYPE,
				'post_status' => array( Fast_Index_Log::STATUS_SENT, Fast_Index_Log::STATUS_FAILED ),
				'numberposts' => 100,
				'orderby'     => 'ID',
				'order'       => 'ASC',
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
	 * Off by default: no request at all, no key, no key file, nothing in the IndexNow channel.
	 *
	 * @return void
	 */
	public function test_off_by_default_sends_nothing() {
		$this->assertFalse( Fast_Index_Settings::defaults()['indexnow'] );
		$this->assertFalse( Fast_Index_IndexNow::is_enabled() );

		// No Google key either: not a single outgoing request.
		$this->publish_and_run();
		$this->assertSame( array(), $this->outgoing );
		$this->assertSame( '', Fast_Index_IndexNow::key() );

		// With a Google key only Google is called.
		$this->add_account();
		$this->fake_google();
		$this->publish_and_run();
		$this->assertCount( 0, $this->outgoing_to( Fast_Index_IndexNow::ENDPOINT ) );
		$this->assertCount( 1, $this->outgoing_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertSame( array(), self::indexnow_entries() );

		// A stored key does not answer while IndexNow is off.
		update_option( Fast_Index_IndexNow::KEY_OPTION, 'abcdefgh12345678abcdefgh12345678', false );
		$this->assertNull( Fast_Index_IndexNow::key_file_body( '/abcdefgh12345678abcdefgh12345678.txt' ) );
	}

	/**
	 * The key: 32 letters and digits, created once, kept, and answered at the home path of the site.
	 *
	 * @return void
	 */
	public function test_key_and_key_file() {
		self::indexnow( true );

		$key = Fast_Index_IndexNow::key( true );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $key );
		$this->assertSame( $key, Fast_Index_IndexNow::key( true ), 'The key is created once.' );
		$this->assertFalse( wp_load_alloptions()[ Fast_Index_IndexNow::KEY_OPTION ] ?? false, 'The key is not autoloaded.' );

		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$this->assertSame( $key, Fast_Index_IndexNow::key_file_body( $home . $key . '.txt' ) );
		$this->assertSame( home_url( '/' . $key . '.txt' ), Fast_Index_IndexNow::key_url() );
		$this->assertNull( Fast_Index_IndexNow::key_file_body( $home . strrev( $key ) . '.txt' ), 'Another key.' );
		$this->assertNull( Fast_Index_IndexNow::key_file_body( $home . 'sub/' . $key . '.txt' ), 'Another folder.' );
		$this->assertNull( Fast_Index_IndexNow::key_file_body( $home . 'robots.txt' ) );
		$this->assertNull( Fast_Index_IndexNow::key_file_body( $home . $key . '.html' ) );
		$this->assertSame( array(), $this->outgoing );
	}

	/**
	 * Saving the settings with IndexNow on creates the key, so the key file answers before the first request.
	 *
	 * @return void
	 */
	public function test_saving_the_setting_creates_the_key() {
		$this->catch_redirects();
		$this->login_as( 'administrator' );
		self::set_nonce( Fast_Index_Admin_Actions::SAVE );
		$_POST['fast_index_options'] = array(
			'status'      => '1',
			'post_type'   => array( 'post' => '1' ),
			'post_status' => array( 'publish' => '1' ),
			'indexnow'    => '1',
		);

		try {
			( new Fast_Index_Admin_Actions() )->save_settings();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$this->assertTrue( Fast_Index_IndexNow::is_enabled() );
		$this->assertNotSame( '', Fast_Index_IndexNow::key() );

		ob_start();
		( new Fast_Index_Admin() )->render_settings();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="fast_index_options[indexnow]" value="1"  checked=\'checked\'', $html );
		$this->assertStringContainsString( esc_url( Fast_Index_IndexNow::key_url() ), $html );
		$this->assertSame( array(), $this->outgoing, 'Saving makes no request.' );
	}

	/**
	 * Publishing with IndexNow on and no Google key: exactly one POST to IndexNow with host, key, keyLocation and
	 * urlList, nothing to Google, and an accepted entry in the IndexNow channel.
	 *
	 * @return void
	 */
	public function test_publish_sends_one_request() {
		self::indexnow( true );

		$post = $this->publish_and_run();

		$this->assertCount( 1, $this->outgoing, 'One request, to IndexNow only.' );
		$request = $this->outgoing_to( Fast_Index_IndexNow::ENDPOINT )[0];
		$body    = json_decode( $request['args']['body'], true );

		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'application/json; charset=utf-8', $request['args']['headers']['Content-Type'] );
		$this->assertSame( (string) wp_parse_url( home_url(), PHP_URL_HOST ), $body['host'] );
		$this->assertSame( Fast_Index_IndexNow::key(), $body['key'] );
		$this->assertSame( Fast_Index_IndexNow::key_url(), $body['keyLocation'] );
		$this->assertSame( array( $post['url'] ), $body['urlList'] );

		$entries = self::indexnow_entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( Fast_Index_Log::STATUS_SENT, $entries[0]['status'] );
		$this->assertSame( 200, $entries[0]['code'] );
		$this->assertSame( 0, Fast_Index_Log::count_sent(), 'Google counters do not see IndexNow.' );
		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type'   => Fast_Index_Log::POST_TYPE,
					'post_status' => 'any',
				)
			),
			'No Google entry without a Google key.'
		);
		$this->assertGreaterThan( 0, (int) get_option( Fast_Index_Upgrade::FIRST_INDEXNOW_OPTION ) );
	}

	/**
	 * With a Google key and IndexNow on, both channels get the notification once.
	 *
	 * @return void
	 */
	public function test_both_channels() {
		self::indexnow( true );
		$this->add_account();
		$this->fake_google();
		$this->indexnow_code = 202;

		$post = $this->publish_and_run();

		$this->assertCount( 1, $this->outgoing_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertCount( 1, $this->outgoing_to( Fast_Index_IndexNow::ENDPOINT ) );
		$this->assertSame( 1, Fast_Index_Log::count_sent() );

		$entries = self::indexnow_entries();
		$this->assertSame( Fast_Index_Log::STATUS_SENT, $entries[0]['status'], '202 counts as accepted.' );
		$this->assertSame( $post['url'], $entries[0]['url'] );

		$stats = Fast_Index_Log::stats( Fast_Index_Settings::get() );
		$this->assertSame( 1, $stats['indexnow_today'] );
		$this->assertSame( 1, $stats['today'] );
	}

	/**
	 * Refusals and failures are kept in History with their code and a reason; nothing is retried.
	 *
	 * @return void
	 */
	public function test_errors_are_recorded_with_a_reason() {
		self::indexnow( true );
		$this->login_as( 'administrator' );

		foreach ( array( 403, 422, 429, 0 ) as $code ) {
			$this->indexnow_code = $code;
			$this->publish_and_run();
		}

		$this->assertSame( array( 403, 422, 429, 0 ), wp_list_pluck( self::indexnow_entries(), 'code' ) );
		$this->assertSame( array( 'draft' ), array_values( array_unique( wp_list_pluck( self::indexnow_entries(), 'status' ) ) ) );
		$this->assertFalse( wp_next_scheduled( Fast_Index_Retry::HOOK ) );
		$this->assertCount( 4, $this->outgoing_to( Fast_Index_IndexNow::ENDPOINT ) );

		ob_start();
		( new Fast_Index_Admin() )->render_history();
		$html = (string) ob_get_clean();

		foreach ( array( 403, 422, 429 ) as $code ) {
			$this->assertStringContainsString( esc_html( Fast_Index_Admin::status_note( $code, Fast_Index_Log::CHANNEL_INDEXNOW ) ), $html, (string) $code );
		}
		$this->assertStringContainsString( 'No answer from IndexNow', $html );
		$this->assertStringNotContainsString( 'Daily quota reached', $html, 'The Google quota note is for Google only.' );
	}

	/**
	 * Addresses of another host are never sent: IndexNow would refuse them with 422.
	 *
	 * @return void
	 */
	public function test_foreign_hosts_are_left_out() {
		self::indexnow( true );

		$result = Fast_Index_IndexNow::submit(
			array(
				array(
					'url'     => 'https://elsewhere.example/page/',
					'type'    => 'URL_UPDATED',
					'post_id' => 0,
				),
			)
		);

		$this->assertSame( -1, $result['code'] );
		$this->assertSame( array(), $this->outgoing );
	}

	/**
	 * History: Channel column, and a filter that lists one channel.
	 *
	 * @return void
	 */
	public function test_history_channel_column_and_filter() {
		$this->login_as( 'administrator' );
		Fast_Index_Log::add( 0, home_url( '/google-entry/' ), 200, 'URL_UPDATED' );
		Fast_Index_Log::add_indexnow( 0, home_url( '/indexnow-entry/' ), 202, 'URL_UPDATED' );

		$this->assertCount( 2, Fast_Index_Log::page( 0 )['entries'] );
		$this->assertSame( array( 'indexnow' ), wp_list_pluck( Fast_Index_Log::page( 0, 'indexnow' )['entries'], 'channel' ) );
		$this->assertSame( array( 'google' ), wp_list_pluck( Fast_Index_Log::page( 0, 'google' )['entries'], 'channel' ) );

		$render = function () {
			ob_start();
			( new Fast_Index_Admin() )->render_history();
			return (string) ob_get_clean();
		};

		$all = $render();
		$this->assertStringContainsString( 'data-colname="Channel"', $all );
		$this->assertStringContainsString( 'google-entry', $all );
		$this->assertStringContainsString( 'indexnow-entry', $all );
		$this->assertStringContainsString( 'channel=indexnow', $all );

		$_GET['channel'] = 'indexnow';
		$only            = $render();
		$this->assertStringNotContainsString( 'google-entry', $only );
		$this->assertStringContainsString( 'indexnow-entry', $only );
		$this->assertMatchesRegularExpression( '/channel=indexnow" class="current" aria-current="page">IndexNow</', $only );

		$_GET['channel'] = 'bogus';
		$this->assertStringContainsString( 'google-entry', $render(), 'An unknown channel shows every channel.' );
	}

	/**
	 * Multisite: each site has its own key, answered under its own path.
	 *
	 * @return void
	 */
	public function test_key_per_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		self::indexnow( true );
		$main = Fast_Index_IndexNow::key( true );

		$site = self::factory()->blog->create( array( 'path' => '/second/' ) );
		switch_to_blog( $site );
		self::indexnow( true );
		$second = Fast_Index_IndexNow::key( true );
		$path   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		$this->assertNotSame( $main, $second );
		$this->assertStringEndsWith( '/second/', $path );
		$this->assertSame( $second, Fast_Index_IndexNow::key_file_body( $path . $second . '.txt' ) );
		$this->assertNull( Fast_Index_IndexNow::key_file_body( $path . $main . '.txt' ) );
		restore_current_blog();

		$this->assertSame( $main, Fast_Index_IndexNow::key() );
	}

	/**
	 * Deleting the plugin with "delete data" removes the IndexNow entries and the key.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_indexnow_data() {
		update_option( Fast_Index_Settings::OPTION, array( 'delete_data' => true ) );
		update_option( Fast_Index_IndexNow::KEY_OPTION, 'abcdefgh12345678abcdefgh12345678', false );
		Fast_Index_Log::add_indexnow( 0, home_url( '/x/' ), 200, 'URL_UPDATED' );

		Fast_Index_Uninstaller::uninstall_site();

		$this->assertFalse( get_option( Fast_Index_IndexNow::KEY_OPTION ) );
		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type'   => Fast_Index_Log::INDEXNOW_POST_TYPE,
					'post_status' => 'any',
				)
			)
		);
	}
}
