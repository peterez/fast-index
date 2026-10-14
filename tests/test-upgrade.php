<?php
/**
 * Where the free version points to the premium plan (KARARLAR (20), (21)), and the Freemius settings around it.
 *
 * @package FastIndex
 */

/**
 * Premium box, hints, History line, key replacement message, Plugins screen links, the two notices, Freemius hooks.
 */
class Fast_Index_Test_Upgrade extends Fast_Index_Test_Case {

	/**
	 * Fills the menu, so the plugin screens are known.
	 *
	 * @return void
	 */
	private static function build_menu() {
		global $menu, $submenu;

		$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Earlier tests filled the global menu.
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same.
		( new Fast_Index_Admin() )->add_menu();
	}

	/**
	 * Output of the notice hook on a screen.
	 *
	 * @param string $screen_id Screen ID.
	 * @return string
	 */
	private static function notices_on( $screen_id ) {
		set_current_screen( $screen_id );

		ob_start();
		Fast_Index_Upgrade::render_notice();
		$html = (string) ob_get_clean();

		set_current_screen( 'front' );

		return $html;
	}

	/**
	 * Renders a plugin screen.
	 *
	 * @param string $method render_settings or render_history.
	 * @return string
	 */
	private static function render( $method ) {
		ob_start();
		( new Fast_Index_Admin() )->$method();

		return (string) ob_get_clean();
	}

	/**
	 * Resets the per-site timestamps and the user records of the notices.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Fast_Index_Upgrade::FIRST_USE_OPTION );
		delete_option( Fast_Index_Upgrade::FIRST_SUCCESS_OPTION );
		delete_metadata( 'user', 0, Fast_Index_Upgrade::USER_META, '', true );
	}

	/**
	 * The Premium box compares both versions in a table with real rows; the free column has what the free version
	 * does, and nothing is a locked control.
	 *
	 * @return void
	 */
	public function test_premium_box_has_a_comparison_table() {
		$this->fake_freemius( false );
		$this->login_as( 'administrator' );

		$html = self::render( 'render_settings' );
		$rows = Fast_Index_Upgrade::comparison();

		$this->assertSame( 1, substr_count( $html, 'class="fi-compare"' ) );
		$this->assertSame( count( $rows ), substr_count( $html, '<th scope="row">' ) );
		$this->assertTrue( $rows[0]['free'], 'Publishing is part of the free version.' );
		$this->assertContains( false, wp_list_pluck( $rows, 'free' ) );
		$this->assertNotContains( false, wp_list_pluck( $rows, 'premium' ) );
		$this->assertDoesNotMatchRegularExpression( '/<(input|select|button)[^>]*\b(disabled|readonly)\b/', $html );
		$this->assertDoesNotMatchRegularExpression( '/discount|% off|today only|limited time|\d+%/i', wp_strip_all_tags( $html ) );
	}

	/**
	 * The "Premium" hints sit next to the post types, the post events and the key upload. The post type hint names
	 * the site's real post types that the free version does not send.
	 *
	 * @return void
	 */
	public function test_hints_name_real_post_types() {
		register_post_type(
			'fi_product',
			array(
				'public' => true,
				'label'  => 'Products',
			)
		);
		$this->fake_freemius( false );
		$this->login_as( 'administrator' );

		$html = self::render( 'render_settings' );

		$this->assertSame( 3, substr_count( $html, '<details class="fi-premium-hint">' ) );
		$this->assertStringContainsString( 'Pages', Fast_Index_Upgrade::hint( 'post_type' ) );
		$this->assertStringContainsString( 'Products', Fast_Index_Upgrade::hint( 'post_type' ) );
		$this->assertStringContainsString( esc_html( Fast_Index_Upgrade::hint( 'post_type' ) ), $html );
		$this->assertStringNotContainsString( 'name="fast_index_options[post_type][fi_product]"', $html, 'The free version offers no checkbox for it.' );
		$this->assertSame( '', Fast_Index_Upgrade::hint( 'unknown' ) );

		unregister_post_type( 'fi_product' );
	}

	/**
	 * History says how many published posts Google has not accepted yet: the real number, and nothing at zero.
	 *
	 * @return void
	 */
	public function test_history_line_uses_the_real_number() {
		$this->fake_freemius( false );
		$this->login_as( 'administrator' );

		$this->assertStringNotContainsString( 'fi-upgrade-line', self::render( 'render_history' ) );

		self::factory()->post->create_many( 3 );
		$sent = self::factory()->post->create();
		Fast_Index_Log::add( $sent, get_permalink( $sent ), 200, Fast_Index_Google_Client::TYPE_UPDATED );
		Fast_Index_Log::flush_stats();

		$html = self::render( 'render_history' );

		$this->assertStringContainsString( 'fi-upgrade-line', $html );
		$this->assertStringContainsString( '3 published posts have not been accepted by Google yet.', $html );
		$this->assertSame( '', Fast_Index_Upgrade::waiting_line( 0 ) );
		$this->assertStringStartsWith( '1 published post has', Fast_Index_Upgrade::waiting_line( 1 ) );
	}

	/**
	 * On phone screens WordPress shows only the primary column of a list table: the History rows have the title as
	 * primary column, a toggle button and column names for the other cells.
	 *
	 * @return void
	 */
	public function test_history_rows_open_on_phone_screens() {
		$this->login_as( 'administrator' );
		$post = self::factory()->post->create();
		Fast_Index_Log::add( $post, get_permalink( $post ), 200, Fast_Index_Google_Client::TYPE_UPDATED );

		$html = self::render( 'render_history' );

		$this->assertStringContainsString( '<th scope="col" class="manage-column column-title column-primary">', $html );
		$this->assertSame( 1, substr_count( $html, '<button type="button" class="toggle-row">' ) );
		$this->assertStringContainsString( 'data-colname="Date"', $html );
		$this->assertStringContainsString( 'data-colname="Send Status"', $html );
		$this->assertStringContainsString( 'data-colname="Post"', $html );
	}

	/**
	 * A key of another account replaces the only key of the free version; the message says so and where several
	 * keys come from. The same account gives the usual message.
	 *
	 * @return void
	 */
	public function test_second_key_is_explained_with_a_link() {
		$this->fake_freemius( false );
		$this->login_as( 'administrator' );
		$this->add_account( 'first@example-project.iam.gserviceaccount.com', 'first' );
		Fast_Index_Admin::take_flash();

		Fast_Index_Admin_Actions::store_account( Fast_Index_Accounts::parse_key_json( self::key_json( 'second@example-project.iam.gserviceaccount.com', 'second' ) ), false );
		$messages = Fast_Index_Admin::take_flash();

		$this->assertCount( 1, Fast_Index_Accounts::all() );
		$this->assertStringContainsString( 'replaced the key of first@example-project.iam.gserviceaccount.com', $messages[0]['message'] );
		$this->assertStringContainsString( 'several keys', $messages[0]['message'] );
		$this->assertSame( admin_url( 'admin.php?page=fast-index-pricing' ), $messages[0]['link_url'] );

		Fast_Index_Admin_Actions::store_account( Fast_Index_Accounts::parse_key_json( self::key_json( 'second@example-project.iam.gserviceaccount.com', 'second' ) ), false );
		$messages = Fast_Index_Admin::take_flash();

		$this->assertSame( 'The key of second@example-project.iam.gserviceaccount.com was saved.', $messages[0]['message'] );
		$this->assertSame( '', $messages[0]['link_url'] );
	}

	/**
	 * The Plugins screen gets Settings and one Upgrade link (the one Freemius adds under the same key is replaced),
	 * before Deactivate.
	 *
	 * @return void
	 */
	public function test_plugins_screen_links() {
		$this->fake_freemius( false );
		$this->login_as( 'administrator' );

		( new Fast_Index_Admin() )->register_hooks();
		$this->assertSame( 20, has_filter( 'plugin_action_links_' . plugin_basename( FAST_INDEX_FILE ), array( 'Fast_Index_Upgrade', 'action_links' ) ) );
		$this->assertSame( 10, has_action( 'admin_notices', array( 'Fast_Index_Upgrade', 'render_notice' ) ) );

		$links = Fast_Index_Upgrade::action_links(
			array(
				'upgrade'    => '<a href="#freemius">Upgrade</a>',
				'deactivate' => '<a href="#deactivate">Deactivate</a>',
			)
		);

		$this->assertSame( array( 'settings', 'upgrade', 'deactivate' ), array_keys( $links ) );
		$this->assertStringContainsString( 'page=fast-index"', $links['settings'] );
		$this->assertStringContainsString( 'page=fast-index-pricing', $links['upgrade'] );
		$this->assertSame( 1, substr_count( implode( '', $links ), '>Upgrade<' ) );
	}

	/**
	 * The offer notice: not before seven days, only on the Fast Index screens (never on the dashboard), and never
	 * again once the user dismissed it.
	 *
	 * @return void
	 */
	public function test_offer_notice_after_a_week_until_dismissed() {
		$this->fake_freemius( false );
		$user = $this->login_as( 'administrator' );
		self::build_menu();

		$now = time();
		update_option( Fast_Index_Upgrade::FIRST_USE_OPTION, $now );

		$this->assertSame( '', Fast_Index_Upgrade::due_notice( $user, $now + 6 * DAY_IN_SECONDS ) );
		$this->assertSame( Fast_Index_Upgrade::NOTICE_OFFER, Fast_Index_Upgrade::due_notice( $user, $now + WEEK_IN_SECONDS ) );

		update_option( Fast_Index_Upgrade::FIRST_USE_OPTION, $now - 8 * DAY_IN_SECONDS );

		$html = self::notices_on( 'toplevel_page_fast-index' );
		$this->assertStringContainsString( 'data-notice="premium_offer"', $html );
		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringContainsString( 'page=fast-index-pricing', $html );
		$this->assertStringContainsString( 'Do not show this again', $html );
		$this->assertSame( '', self::notices_on( 'dashboard' ) );
		$this->assertSame( '', self::notices_on( 'plugins' ) );

		Fast_Index_Notices::dismiss( Fast_Index_Upgrade::NOTICE_OFFER );

		$this->assertSame( '', self::notices_on( 'toplevel_page_fast-index' ) );
		$this->assertSame( '', Fast_Index_Upgrade::due_notice( $user, $now + YEAR_IN_SECONDS ) );

		// Another administrator still gets it once.
		$other = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertSame( Fast_Index_Upgrade::NOTICE_OFFER, Fast_Index_Upgrade::due_notice( $other, $now ) );
	}

	/**
	 * The week starts when Fast Index is set up, or on the first visit of a site that predates this version.
	 *
	 * @return void
	 */
	public function test_first_use_is_remembered_once() {
		$this->assertFalse( get_option( Fast_Index_Upgrade::FIRST_USE_OPTION ) );

		Fast_Index_Installer::install_site();
		$first = (int) get_option( Fast_Index_Upgrade::FIRST_USE_OPTION );
		$this->assertGreaterThan( 0, $first );

		update_option( Fast_Index_Upgrade::FIRST_USE_OPTION, $first - 100 );
		Fast_Index_Upgrade::remember_first_use();
		$this->assertSame( $first - 100, (int) get_option( Fast_Index_Upgrade::FIRST_USE_OPTION ) );
	}

	/**
	 * The thank-you: after Google accepted a notification, on the Fast Index screens, with a review request (no
	 * reward) and the premium line; once dismissed, never again. It comes before the offer.
	 *
	 * @return void
	 */
	public function test_thank_you_after_the_first_accepted_notification() {
		$this->fake_freemius( false );
		$user = $this->login_as( 'administrator' );
		self::build_menu();
		update_option( Fast_Index_Upgrade::FIRST_USE_OPTION, time() - 30 * DAY_IN_SECONDS );

		$post = self::factory()->post->create();
		Fast_Index_Log::add( $post, get_permalink( $post ), 403, Fast_Index_Google_Client::TYPE_UPDATED );
		$this->assertFalse( get_option( Fast_Index_Upgrade::FIRST_SUCCESS_OPTION ), 'A refused notification is no success.' );

		Fast_Index_Log::add( $post, get_permalink( $post ), 200, Fast_Index_Google_Client::TYPE_UPDATED );
		$this->assertGreaterThan( 0, (int) get_option( Fast_Index_Upgrade::FIRST_SUCCESS_OPTION ) );

		$html = self::notices_on( 'fast-index_page_fast-index-history' );
		$this->assertStringContainsString( 'data-notice="first_success"', $html );
		$this->assertStringContainsString( 'Google accepted a notification from this site.', $html );
		$this->assertStringContainsString( Fast_Index_Upgrade::REVIEWS_URL, $html );
		$this->assertStringContainsString( esc_html( Fast_Index_Upgrade::summary() ), $html );
		$this->assertStringNotContainsString( 'data-notice="premium_offer"', $html, 'One notice at a time.' );
		$this->assertDoesNotMatchRegularExpression( '/coupon|discount|free month|in exchange|reward/i', wp_strip_all_tags( $html ) );
		$this->assertSame( '', self::notices_on( 'dashboard' ) );

		Fast_Index_Notices::dismiss( Fast_Index_Upgrade::NOTICE_SUCCESS );
		$this->assertSame( Fast_Index_Upgrade::NOTICE_OFFER, Fast_Index_Upgrade::due_notice( $user, time() ) );

		Fast_Index_Notices::dismiss( Fast_Index_Upgrade::NOTICE_OFFER );
		$this->assertSame( '', self::notices_on( 'fast-index_page_fast-index-history' ) );
	}

	/**
	 * The dismiss link goes through the nonce-protected handler and records the notice for the user.
	 *
	 * @return void
	 */
	public function test_dismiss_handler_records_the_notice() {
		$user = $this->login_as( 'administrator' );
		$this->catch_redirects();

		$url = html_entity_decode( Fast_Index_Notices::dismiss_url( Fast_Index_Upgrade::NOTICE_OFFER ), ENT_QUOTES );
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$_GET     = $query;
		$_REQUEST = $query;

		try {
			( new Fast_Index_Admin_Actions() )->dismiss_notice();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$this->assertArrayHasKey( Fast_Index_Upgrade::NOTICE_OFFER, Fast_Index_Upgrade::dismissed( $user ) );
	}

	/**
	 * With the premium module loaded nothing is offered: no box, no hint, no History line, no Upgrade link, no
	 * offer notice. The thank-you stays, without the premium line.
	 *
	 * @return void
	 */
	public function test_nothing_is_offered_with_the_premium_module() {
		$this->with_premium();
		$user = $this->login_as( 'administrator' );
		self::build_menu();
		self::factory()->post->create_many( 2 );
		update_option( Fast_Index_Upgrade::FIRST_USE_OPTION, time() - 30 * DAY_IN_SECONDS );

		$this->assertFalse( Fast_Index_Upgrade::is_offered() );

		$settings = self::render( 'render_settings' );
		$this->assertStringNotContainsString( 'fi-premium-card', $settings );
		$this->assertStringNotContainsString( 'fi-premium-hint', $settings );
		$this->assertStringNotContainsString( 'fi-upgrade-line', self::render( 'render_history' ) );
		$this->assertArrayNotHasKey( 'upgrade', Fast_Index_Upgrade::action_links( array( 'upgrade' => 'x' ) ) );
		$this->assertSame( '', Fast_Index_Upgrade::due_notice( $user, time() ) );

		Fast_Index_Upgrade::remember_success();
		$html = self::notices_on( 'toplevel_page_fast-index' );
		$this->assertStringContainsString( 'data-notice="first_success"', $html );
		$this->assertStringNotContainsString( esc_html( Fast_Index_Upgrade::summary() ), $html );
	}

	/**
	 * Network activation: Freemius keeps the plans page in the network admin, so only a super admin is sent there.
	 *
	 * @return void
	 */
	public function test_network_plans_page_is_offered_to_super_admins_only() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$freemius              = $this->fake_freemius( false );
		$freemius->upgrade_url = network_admin_url( 'admin.php?page=fast-index-pricing' );

		$this->login_as( 'administrator' );
		$this->assertTrue( Fast_Index_Upgrade::is_offered() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( Fast_Index_Upgrade::is_offered() );
		$this->assertSame( '', Fast_Index_Upgrade::waiting_line( 5 ) );
	}

	/**
	 * Freemius settings around the offers: no trial or affiliate promotion, its own advertising notices hidden, the
	 * site menus kept during a network activation, the plugin's own words for the texts Help & FAQ names.
	 *
	 * @return void
	 */
	public function test_freemius_hooks() {
		$fs = figi_fs();

		$this->assertNotFalse( $fs->has_filter( 'show_trial', '__return_false' ) );
		$this->assertNotFalse( $fs->has_filter( 'show_affiliate_program_notice', '__return_false' ) );
		$this->assertNotFalse( $fs->has_filter( 'should_hide_site_admin_settings_on_network_activation_mode', '__return_false' ) );
		$this->assertNotFalse( $fs->has_filter( 'plugin_icon', array( 'Fast_Index_Freemius', 'icon' ) ) );

		$this->assertFalse( Fast_Index_Freemius::show_admin_notice( true, array( 'id' => 'trial_promotion' ) ) );
		$this->assertFalse( Fast_Index_Freemius::show_admin_notice( true, array( 'id' => 'affiliate_program' ) ) );
		$this->assertTrue( Fast_Index_Freemius::show_admin_notice( true, array( 'id' => 'activation_complete' ) ) );

		global $fs_text_overrides;
		Fast_Index_Freemius::translate_texts();
		$this->assertSame( 'Upgrade', $fs_text_overrides['fast-index']['upgrade'] );
		$this->assertSame( 'Skip', $fs_text_overrides['fast-index']['skip'] );
		$this->assertSame( 'Allow & Continue', $fs_text_overrides['fast-index']['opt-in-connect'] );
	}
}
