<?php
/**
 * Help & FAQ: single source of the texts, the readme, the screen, help tabs and system information.
 *
 * @package FastIndex
 */

/**
 * Help content and screen.
 */
class Fast_Index_Test_Help extends Fast_Index_Test_Case {

	/**
	 * Renders the Help & FAQ screen and returns its HTML.
	 *
	 * @return string
	 */
	private static function render_help() {
		ob_start();
		( new Fast_Index_Help() )->render();

		return (string) ob_get_clean();
	}

	/**
	 * The readme FAQ has the same questions, in the same order, as the Help & FAQ screen.
	 *
	 * @return void
	 */
	public function test_readme_faq_matches_help_faq() {
		$file = dirname( __DIR__ ) . '/readme.txt';

		if ( ! file_exists( $file ) ) {
			$file = dirname( __DIR__ ) . '/README.txt';
		}

		$text = str_replace( "\r\n", "\n", (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local file in a test.
		$this->assertStringContainsString( '== Frequently Asked Questions ==', $text );

		$section = explode( '== Frequently Asked Questions ==', $text, 2 )[1];
		$section = preg_split( '/^== /m', $section, 2 )[0];

		preg_match_all( '/^= (.+) =$/m', $section, $matches );

		$this->assertSame( Fast_Index_Help_Content::faq_questions(), $matches[1] );
	}

	/**
	 * The content has the required amount and shape of entries.
	 *
	 * @return void
	 */
	public function test_content_is_complete() {
		$this->assertGreaterThanOrEqual( 10, count( Fast_Index_Help_Content::faq() ) );
		$this->assertGreaterThanOrEqual( 7, count( Fast_Index_Help_Content::troubleshooting() ) );
		$this->assertSame(
			array( 'getting-started', 'settings', 'troubleshooting', 'faq', 'support' ),
			array_keys( Fast_Index_Help_Content::sections() )
		);

		$faq_ids     = array();
		$problem_ids = array();

		foreach ( Fast_Index_Help_Content::faq() as $item ) {
			$this->assertNotSame( '', trim( $item['question'] ) );
			$this->assertNotSame( '', trim( wp_strip_all_tags( $item['answer'] ) ) );
			$faq_ids[] = $item['id'];
		}

		foreach ( Fast_Index_Help_Content::troubleshooting() as $item ) {
			$this->assertNotSame( '', trim( $item['symptom'] ) );
			$this->assertNotSame( '', trim( wp_strip_all_tags( $item['cause'] ) ) );
			$this->assertNotSame( '', trim( wp_strip_all_tags( $item['fix'] ) ) );
			$problem_ids[] = $item['id'];
		}

		$this->assertSame( $faq_ids, array_values( array_unique( $faq_ids ) ), 'FAQ IDs are unique.' );
		$this->assertSame( $problem_ids, array_values( array_unique( $problem_ids ) ), 'Troubleshooting IDs are unique.' );
	}

	/**
	 * Printing the texts through wp_kses() removes nothing: every tag and attribute used is allowed.
	 *
	 * @return void
	 */
	public function test_texts_survive_kses() {
		$allowed = Fast_Index_Help_Content::allowed_html();
		$texts   = array(
			Fast_Index_Help_Content::getting_started_notice(),
			Fast_Index_Help_Content::getting_started_video(),
			Fast_Index_Help_Content::sidebar(),
		);

		foreach ( Fast_Index_Help_Content::getting_started_steps() as $step ) {
			$texts[] = $step['text'];
		}

		foreach ( Fast_Index_Help_Content::settings() as $setting ) {
			$texts[] = $setting['what'];
			$texts[] = $setting['when'];
		}

		foreach ( Fast_Index_Help_Content::troubleshooting() as $item ) {
			$texts[] = $item['cause'];
			$texts[] = $item['fix'];
		}

		foreach ( Fast_Index_Help_Content::faq() as $item ) {
			$texts[] = $item['answer'];
		}

		foreach ( $texts as $text ) {
			// Normalize the one thing kses rewrites on purpose: a bare ampersand becomes an entity.
			$this->assertSame( wp_kses_normalize_entities( $text ), wp_kses( $text, $allowed ) );
		}
	}

	/**
	 * Every link to another site opens safely, and no frame, script or form is in the page.
	 *
	 * @return void
	 */
	public function test_screen_markup() {
		$this->login_as( 'administrator' );

		$html = self::render_help();

		$this->assertStringContainsString( '<h1>Help &amp; FAQ</h1>', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( 'href="https://wordpress.org/support/plugin/fast-index/"', $html );
		$this->assertStringContainsString( 'href="https://youtu.be/RsJA66b5884" target="_blank" rel="noopener noreferrer"', $html );

		preg_match_all( '/<a [^>]*href="(https?:\/\/[^"]+)"[^>]*>/', $html, $links );

		foreach ( $links[0] as $index => $tag ) {
			if ( 0 === strpos( $links[1][ $index ], home_url() ) ) {
				continue;
			}

			$this->assertStringContainsString( 'target="_blank"', $tag, $links[1][ $index ] );
			$this->assertStringContainsString( 'rel="noopener noreferrer"', $tag, $links[1][ $index ] );
		}

		foreach ( array_keys( Fast_Index_Help_Content::sections() ) as $key ) {
			$this->assertStringContainsString( 'href="#fi-help-' . $key . '"', $html );
			$this->assertStringContainsString( 'id="fi-help-' . $key . '"', $html );
		}

		$details = count( Fast_Index_Help_Content::faq() ) + count( Fast_Index_Help_Content::troubleshooting() );

		$this->assertSame( $details, substr_count( $html, '<details ' ) );
		$this->assertStringContainsString( 'data-fast-index-copy="fi-help-system-info"', $html );
	}

	/**
	 * The page refuses users without manage_options.
	 *
	 * @return void
	 */
	public function test_screen_requires_capability() {
		$this->login_as( 'editor' );

		try {
			self::render_help();
			$this->fail( 'Rendered for an editor.' );
		} catch ( WPDieException $e ) {
			ob_end_clean();
			$this->assertStringContainsString( 'not allowed', $e->getMessage() );
		}
	}

	/**
	 * The system information has the facts and no secret: no key, no email address, no site address.
	 *
	 * @return void
	 */
	public function test_system_info_has_no_secret() {
		$this->login_as( 'administrator' );
		$this->add_account();
		Fast_Index_Accounts::set_status( Fast_Index_Accounts::id_for( 'indexer@example-project.iam.gserviceaccount.com' ), 403 );
		Fast_Index_Log::add( 0, 'https://example.org/secret-path/', 200, 'URL_UPDATED' );

		$text = Fast_Index_Help::system_info_text();
		$html = self::render_help();

		$this->assertStringContainsString( 'Fast Index version: ' . FAST_INDEX_VERSION, $text );
		$this->assertStringContainsString( 'WordPress version: ' . get_bloginfo( 'version' ), $text );
		$this->assertStringContainsString( 'PHP version: ' . PHP_VERSION, $text );
		$this->assertStringContainsString( 'Multisite: ' . ( is_multisite() ? 'Yes' : 'No' ), $text );
		$this->assertStringContainsString( 'Site language: ' . get_locale(), $text );
		$this->assertStringContainsString( 'OpenSSL available: Yes', $text );
		$this->assertStringContainsString( 'WP-Cron disabled (DISABLE_WP_CRON): ', $text );
		$this->assertStringContainsString( 'Stored service account keys: 1', $text );
		$this->assertStringContainsString( 'Key 1: 403 Forbidden', $text );
		$this->assertStringContainsString( 'Keys available now (not resting): 0', $text );
		$this->assertStringContainsString( ', URL_UPDATED, 200 OK', $text );

		foreach ( array( $text, $html ) as $output ) {
			$this->assertStringNotContainsString( 'indexer@example-project', $output );
			$this->assertStringNotContainsString( 'PRIVATE KEY', $output );
			$this->assertStringNotContainsString( substr( preg_replace( '/-----[A-Z ]+-----|\s/', '', self::private_key() ), 10, 40 ), $output );
			$this->assertStringNotContainsString( 'secret-path', $output );
		}
	}

	/**
	 * The assets load on the Help & FAQ page only.
	 *
	 * @return void
	 */
	public function test_assets_load_on_help_page_only() {
		$this->login_as( 'administrator' );
		( new Fast_Index_Admin() )->add_menu();

		$help = new Fast_Index_Help();

		$help->enqueue_assets( 'index.php' );
		$this->assertFalse( wp_script_is( 'fast-index-help', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'fast-index-help', 'enqueued' ) );

		$help->enqueue_assets( get_plugin_page_hookname( Fast_Index_Help::SLUG, Fast_Index_Admin::SLUG ) );
		$this->assertTrue( wp_script_is( 'fast-index-help', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'fast-index-help', 'enqueued' ) );
		$this->assertStringContainsString( 'window.fastIndexHelp', implode( '', (array) wp_scripts()->get_data( 'fast-index-help', 'before' ) ) );
	}

	/**
	 * Every plugin screen gets a help tab that links to the Help & FAQ page.
	 *
	 * @return void
	 */
	public function test_help_tabs_on_every_screen() {
		$this->login_as( 'administrator' );
		( new Fast_Index_Admin() )->add_menu();

		foreach ( array( Fast_Index_Admin::SLUG, Fast_Index_Admin::SLUG_HISTORY, Fast_Index_Admin::SLUG_RUN, Fast_Index_Help::SLUG ) as $slug ) {
			$hook = get_plugin_page_hookname( $slug, Fast_Index_Admin::SLUG );
			set_current_screen( $hook );

			do_action( 'load-' . $hook ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook of an admin screen.

			$screen = get_current_screen();
			$tabs   = $screen->get_help_tabs();

			$this->assertCount( 1, $tabs, $slug );
			$this->assertStringContainsString( 'fast-index-help', $screen->get_help_sidebar(), $slug );

			if ( Fast_Index_Help::SLUG !== $slug ) {
				$tab = reset( $tabs );
				$this->assertStringContainsString( 'page=fast-index-help', $tab['content'], $slug );
			}
		}
	}

	/**
	 * The menu has the Help & FAQ sub page.
	 *
	 * @return void
	 */
	public function test_menu_has_help_page() {
		global $submenu;

		$this->login_as( 'administrator' );
		( new Fast_Index_Admin() )->add_menu();

		$this->assertContains( Fast_Index_Help::SLUG, wp_list_pluck( $submenu[ Fast_Index_Admin::SLUG ], 2 ) );
	}

	/**
	 * The settings screen has no frame, links to the help page and describes every field.
	 *
	 * @return void
	 */
	public function test_settings_screen_links_to_help_and_describes_fields() {
		$this->login_as( 'administrator' );

		ob_start();
		( new Fast_Index_Admin() )->render_settings();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringContainsString( 'page=fast-index-help', $html );
		$this->assertGreaterThanOrEqual( 7, substr_count( $html, '<p class="description">' ) );

		foreach ( array( 'status', 'post_type', 'old_post_number', 'post_status', 'exclude_category', 'delete_data' ) as $field ) {
			$this->assertNotSame( '', Fast_Index_Help_Content::field_description( $field ), $field );
			$this->assertStringContainsString( esc_html( Fast_Index_Help_Content::field_description( $field ) ), $html, $field );
		}
	}
}
