<?php
/**
 * Sending by hand (FI-R04): row action, bulk action, limit of 100, capability per post, result notice.
 *
 * @package FastIndex
 */

/**
 * Fast_Index_Manual.
 */
class Fast_Index_Test_Manual extends Fast_Index_Test_Case {

	/**
	 * Requests to IndexNow seen by the fake endpoint.
	 *
	 * @var array[]
	 */
	private $indexnow = array();

	/**
	 * Starts every test with a Google key and the fake Google endpoint.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->add_account();
		$this->fake_google();
		add_filter( 'pre_http_request', array( $this, 'answer_indexnow' ), 9, 3 );
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

		$this->indexnow[] = json_decode( $args['body'], true );

		return self::http_response( 200, '' );
	}

	/**
	 * Creates published posts without leaving their queued notifications behind.
	 *
	 * @param int   $count Number of posts.
	 * @param array $args  Post arguments.
	 * @return int[]
	 */
	private static function posts( $count, $args = array() ) {
		$ids = self::factory()->post->create_many( $count, array_merge( array( 'post_status' => 'publish' ), $args ) );
		self::clear_queue();

		return $ids;
	}

	/**
	 * Google History entries.
	 *
	 * @return WP_Post[]
	 */
	private static function google_entries() {
		return get_posts(
			array(
				'post_type'   => Fast_Index_Log::POST_TYPE,
				'post_status' => array( Fast_Index_Log::STATUS_SENT, Fast_Index_Log::STATUS_FAILED ),
				'numberposts' => 100,
			)
		);
	}

	/**
	 * The row action: published posts of a checked type that the user can edit, while Status is Active.
	 *
	 * @return void
	 */
	public function test_row_action_is_offered_only_where_it_can_send() {
		$admin     = $this->login_as( 'administrator' );
		$published = get_post( self::posts( 1 )[0] );
		$draft     = get_post( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );
		$page      = get_post( self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test fixture.

		$actions = Fast_Index_Manual::row_action( array(), $published );
		$this->assertArrayHasKey( 'fast_index_notify', $actions );
		$this->assertStringContainsString( 'action=fast_index_notify_post', $actions['fast_index_notify'] );
		$this->assertStringContainsString( '_wpnonce=', $actions['fast_index_notify'] );
		$this->assertArrayNotHasKey( 'fast_index_notify', Fast_Index_Manual::row_action( array(), $draft ) );
		$this->assertArrayNotHasKey( 'fast_index_notify', Fast_Index_Manual::row_action( array(), $page ), 'Pages are not checked by default.' );

		// An author cannot edit an administrator's post, and cannot send by hand at all.
		$author = $this->login_as( 'author' );
		$this->assertArrayNotHasKey( 'fast_index_notify', Fast_Index_Manual::row_action( array(), $published ) );
		$own = get_post( self::posts( 1, array( 'post_author' => $author ) )[0] );
		$this->assertArrayNotHasKey( 'fast_index_notify', Fast_Index_Manual::row_action( array(), $own ), 'Not even for their own post.' );

		// An editor can.
		$this->login_as( 'editor' );
		$this->assertArrayHasKey( 'fast_index_notify', Fast_Index_Manual::row_action( array(), $published ) );

		// Passive: nothing is offered.
		wp_set_current_user( $admin );
		update_option( Fast_Index_Settings::OPTION, array( 'status' => '2' ) );
		$this->assertArrayNotHasKey( 'fast_index_notify', Fast_Index_Manual::row_action( array(), $published ) );
		$this->assertSame( array(), Fast_Index_Manual::post_types() );
	}

	/**
	 * The row handler checks the capability for the post, then its own nonce, then sends once and goes back.
	 *
	 * @return void
	 */
	public function test_row_handler_checks_capability_and_nonce() {
		$this->catch_redirects();
		$post_id = self::posts( 1 )[0];

		// A subscriber is refused before the nonce is read.
		$this->login_as( 'subscriber' );
		$_GET['post'] = $post_id;
		try {
			Fast_Index_Manual::handle_row();
			$this->fail( 'A subscriber sent a post.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'not allowed', $e->getMessage() );
		}

		// An administrator without a nonce is refused.
		$this->login_as( 'administrator' );
		try {
			Fast_Index_Manual::handle_row();
			$this->fail( 'Sent without a nonce.' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertCount( 0, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );

		$_REQUEST['_wpnonce'] = wp_create_nonce( Fast_Index_Manual::ROW_ACTION . '_' . $post_id );
		try {
			Fast_Index_Manual::handle_row();
			$this->fail( 'Expected a redirect.' );
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			$this->assertStringContainsString( 'fast_index_notified=1', $redirect->getMessage() );
		}

		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertCount( 1, self::google_entries() );
	}

	/**
	 * Bulk action, 100 selected: 100 History entries, 100 requests to Google, no limit line.
	 *
	 * @return void
	 */
	public function test_bulk_sends_one_hundred() {
		$this->login_as( 'administrator' );
		$ids = self::posts( 100 );

		$url = Fast_Index_Manual::handle_bulk( admin_url( 'edit.php' ), Fast_Index_Manual::BULK_ACTION, $ids );

		$this->assertStringContainsString( 'fast_index_notified=1', $url );
		$this->assertCount( 100, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertCount( 100, self::google_entries() );
		$this->assertSame( 100, Fast_Index_Log::count_sent() );

		$result = get_transient( Fast_Index_Manual::RESULT_PREFIX . get_current_user_id() );
		$this->assertSame( 100, $result['google_accepted'] );
		$this->assertSame( 0, $result['over_limit'] );
		$this->assertSame( array( 'Google accepted 100 addresses.' ), Fast_Index_Manual::result_lines( $result ) );
	}

	/**
	 * Bulk action, 150 selected: the first 100 are sent, the result says so, and the free version adds the line
	 * about the premium plan once, with a link; with the module there is no such line.
	 *
	 * @return void
	 */
	public function test_bulk_over_the_limit_sends_the_first_hundred() {
		$this->fake_freemius( false );
		$this->login_as( 'administrator' );
		$ids = self::posts( 150 );

		Fast_Index_Manual::handle_bulk( admin_url( 'edit.php' ), Fast_Index_Manual::BULK_ACTION, $ids );

		$sent = wp_list_pluck( self::google_entries(), 'post_parent' );
		$this->assertCount( 100, $sent );
		$this->assertSame( array(), array_diff( array_slice( $ids, 0, 100 ), $sent ), 'The first 100 in list order.' );

		$html = self::render_result();
		$this->assertStringContainsString( 'Up to 100 items are sent at a time: the first 100 of the 150 selected items were handled', $html );
		$this->assertSame( 1, substr_count( $html, 'fi-upgrade-line' ) );
		$this->assertStringContainsString( esc_html( Fast_Index_Upgrade::limit_line() ), $html );
		$this->assertStringContainsString( 'page=fast-index-pricing', $html );
		$this->assertSame( '', self::render_result(), 'The result is shown once.' );

		// With the premium module: the same limit, no line about the plan.
		$this->with_premium();
		Fast_Index_Manual::handle_bulk( admin_url( 'edit.php' ), Fast_Index_Manual::BULK_ACTION, $ids );
		$html = self::render_result();
		$this->assertStringContainsString( 'Up to 100 items', $html );
		$this->assertStringNotContainsString( 'fi-upgrade-line', $html );
	}

	/**
	 * Daily quota used up: the selected posts are recorded as not sent and wait for the reset.
	 *
	 * @return void
	 */
	public function test_quota_full_records_not_sent() {
		$this->login_as( 'administrator' );
		$ids = self::posts( 3 );
		Fast_Index_Accounts::pause_for_quota();

		Fast_Index_Manual::handle_bulk( admin_url( 'edit.php' ), Fast_Index_Manual::BULK_ACTION, $ids );

		$this->assertCount( 0, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$entries = self::google_entries();
		$this->assertCount( 3, $entries );
		foreach ( $entries as $entry ) {
			$this->assertSame( Fast_Index_Submitter::RESTING, (int) get_post_meta( $entry->ID, Fast_Index_Log::META_CODE, true ) );
		}
		$this->assertSame( 3, Fast_Index_Log::count_held() );
		$this->assertStringContainsString( '3 addresses were not accepted by Google or could not be sent.', self::render_result() );
	}

	/**
	 * Each selected post is checked: posts the user cannot edit, drafts and unchecked types are not sent.
	 *
	 * @return void
	 */
	public function test_bulk_checks_each_post() {
		$admin  = $this->login_as( 'administrator' );
		$theirs = self::posts( 2 );
		$author = $this->login_as( 'author' );
		$mine   = self::posts( 1, array( 'post_author' => $author ) );
		$draft  = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $author,
			)
		);
		$all    = array_merge( $theirs, $mine, array( $draft, 999999 ) );

		// SEC-FI-04: an author cannot send by hand, not even their own post (it was sent when it was published).
		Fast_Index_Manual::handle_bulk( admin_url( 'edit.php' ), Fast_Index_Manual::BULK_ACTION, $all );
		$this->assertSame( array(), self::google_entries() );
		$this->assertSame( 5, get_transient( Fast_Index_Manual::RESULT_PREFIX . $author )['skipped'] );

		$editor = $this->login_as( 'editor' );
		Fast_Index_Manual::handle_bulk( admin_url( 'edit.php' ), Fast_Index_Manual::BULK_ACTION, $all );

		$sent = array_map( 'intval', wp_list_pluck( self::google_entries(), 'post_parent' ) );
		sort( $sent );
		$this->assertSame( array_merge( $theirs, $mine ), $sent );
		$this->assertSame( 2, get_transient( Fast_Index_Manual::RESULT_PREFIX . $editor )['skipped'] );
		unset( $admin );
	}

	/**
	 * The capability can be changed for a post type.
	 *
	 * @return void
	 */
	public function test_capability_filter() {
		$this->assertSame( 'edit_others_posts', Fast_Index_Manual::capability( 'post' ) );
		$this->assertSame( 'edit_others_pages', Fast_Index_Manual::capability( 'page' ) );

		$author = $this->login_as( 'author' );
		$mine   = self::posts( 1, array( 'post_author' => $author ) );
		$this->assertFalse( Fast_Index_Manual::user_can_send( 'post' ) );

		$publish = function () {
			return 'publish_posts';
		};
		add_filter( 'fast_index_manual_capability', $publish );
		$this->assertTrue( Fast_Index_Manual::user_can_send( 'post' ) );
		Fast_Index_Manual::send( $mine );
		$this->assertCount( 1, self::google_entries() );
		remove_filter( 'fast_index_manual_capability', $publish );
	}

	/**
	 * With IndexNow on, the handled addresses go to IndexNow in one request.
	 *
	 * @return void
	 */
	public function test_bulk_sends_one_indexnow_request() {
		$this->login_as( 'administrator' );
		update_option(
			Fast_Index_Settings::OPTION,
			array(
				'post_type'   => array( 'post' => '1' ),
				'post_status' => array( 'publish' => '1' ),
				'indexnow'    => '1',
			)
		);
		$ids = self::posts( 5 );

		Fast_Index_Manual::handle_bulk( admin_url( 'edit.php' ), Fast_Index_Manual::BULK_ACTION, $ids );

		$this->assertCount( 1, $this->indexnow );
		$this->assertCount( 5, $this->indexnow[0]['urlList'] );
		$this->assertCount( 5, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		$this->assertStringContainsString( 'IndexNow accepted 5 addresses.', self::render_result() );
	}

	/**
	 * When the time budget is spent, the rest is queued for the next scheduled-task run instead of being dropped.
	 *
	 * @return void
	 */
	public function test_spent_budget_queues_the_rest() {
		$ids   = self::posts( 3 );
		$items = array();
		foreach ( $ids as $id ) {
			$items[] = array(
				'url'     => get_permalink( $id ),
				'type'    => 'URL_UPDATED',
				'post_id' => $id,
			);
		}

		$result = Fast_Index_Notifier::deliver_many( $items, 0 );

		$this->assertSame( 3, $result['queued'] );
		$this->assertCount( 0, $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL ) );
		foreach ( $items as $item ) {
			$this->assertTrue( self::is_queued( $item['url'], 'URL_UPDATED', $item['post_id'] ) );
		}
	}

	/**
	 * The bulk action is offered on the list screens of the checked types only, and its handler ignores other
	 * actions.
	 *
	 * @return void
	 */
	public function test_bulk_action_registration() {
		$this->login_as( 'author' );
		Fast_Index_Manual::register_bulk_actions();
		$this->assertFalse( has_filter( 'bulk_actions-edit-post', array( 'Fast_Index_Manual', 'bulk_actions' ) ), 'Not for an author.' );

		$this->login_as( 'editor' );
		Fast_Index_Manual::register_bulk_actions();

		$this->assertNotFalse( has_filter( 'bulk_actions-edit-post', array( 'Fast_Index_Manual', 'bulk_actions' ) ) );
		$this->assertFalse( has_filter( 'bulk_actions-edit-page', array( 'Fast_Index_Manual', 'bulk_actions' ) ) );
		$this->assertSame( 'Notify search engines', Fast_Index_Manual::bulk_actions( array() )[ Fast_Index_Manual::BULK_ACTION ] );
		$this->assertSame( 'back', Fast_Index_Manual::handle_bulk( 'back', 'trash', array( 1 ) ) );
	}

	/**
	 * Renders the result notice on the Posts list screen.
	 *
	 * @return string
	 */
	private static function render_result() {
		set_current_screen( 'edit-post' );
		$_GET[ Fast_Index_Manual::RESULT_ARG ] = '1';

		ob_start();
		Fast_Index_Manual::render_result();
		$html = (string) ob_get_clean();

		set_current_screen( 'front' );

		return $html;
	}
}
