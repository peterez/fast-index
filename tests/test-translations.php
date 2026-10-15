<?php
/**
 * Translations: the bundled files fill what an older translate.wordpress.org language pack lacks.
 *
 * @package FastIndex
 */

/**
 * Bundled translation next to a language pack.
 */
class Fast_Index_Test_Translations extends Fast_Index_Test_Case {

	/**
	 * Language pack written by the test.
	 *
	 * @var string
	 */
	private $pack = '';

	/**
	 * Text domain registry before the test; null before WordPress 6.1, which has no registry.
	 *
	 * @var WP_Textdomain_Registry|null
	 */
	private $registry;

	/**
	 * Keeps the text domain registry, which remembers where translation files were found.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->registry = isset( $GLOBALS['wp_textdomain_registry'] ) ? clone $GLOBALS['wp_textdomain_registry'] : null;
	}

	/**
	 * Removes the language pack and returns to the test locale.
	 *
	 * @return void
	 */
	public function tear_down() {
		restore_current_locale();
		if ( null !== $this->registry ) {
			$GLOBALS['wp_textdomain_registry'] = $this->registry; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restores the test state.
		}
		if ( '' !== $this->pack && file_exists( $this->pack ) ) {
			unlink( $this->pack ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file.
		}
		unload_textdomain( 'fast-index' );
		parent::tear_down();
	}

	/**
	 * Writes a language pack with one string, as an old pack from translate.wordpress.org would have.
	 *
	 * @param string $locale Locale.
	 * @return void
	 */
	private function write_pack( $locale ) {
		require_once ABSPATH . WPINC . '/pomo/mo.php';

		$this->pack = WP_LANG_DIR . '/plugins/fast-index-' . $locale . '.mo';
		$mo         = new MO();
		$mo->set_header( 'Plural-Forms', 'nplurals=2; plural=n != 1;' );
		$mo->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'History',
					'translations' => array( 'Verlauf aus dem Sprachpaket' ),
				)
			)
		);
		$this->assertTrue( $mo->export_to_file( $this->pack ) );

		// As after a language pack update: WordPress looks in the languages folder again.
		wp_cache_delete( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' );
		if ( isset( $GLOBALS['wp_textdomain_registry'] ) ) {
			$GLOBALS['wp_textdomain_registry']->set( 'fast-index', $locale, WP_LANG_DIR . '/plugins' );
		}
	}

	/**
	 * Translation of a string in the bundled file.
	 *
	 * @param string $locale Locale.
	 * @param string $text   Source string.
	 * @return string
	 */
	private static function bundled( $locale, $text ) {
		require_once ABSPATH . WPINC . '/pomo/po.php';

		$po = new PO();
		$po->import_from_file( dirname( FAST_INDEX_FILE ) . '/languages/fast-index-' . $locale . '.po' );

		return $po->translate( $text );
	}

	/**
	 * With an older pack installed, its strings win and the bundled file fills the rest.
	 *
	 * @return void
	 */
	public function test_bundled_file_fills_an_older_language_pack() {
		$this->write_pack( 'de_DE' );
		$expected = self::bundled( 'de_DE', 'Help & FAQ' );
		$this->assertNotSame( 'Help & FAQ', $expected );

		// Switching the locale is where WordPress loads the pack again; the plugin adds its file after it.
		$this->assertTrue( switch_to_locale( 'de_DE' ) );
		$this->assertSame( 'Verlauf aus dem Sprachpaket', __( 'History', 'fast-index' ) );
		$this->assertSame( $expected, __( 'Help & FAQ', 'fast-index' ) );

		// On init the domain may not be loaded yet: the pack is loaded first, then the bundled file.
		unload_textdomain( 'fast-index', true );
		$this->assertTrue( Fast_Index_Plugin::add_bundled_translation() );
		$this->assertSame( 'Verlauf aus dem Sprachpaket', __( 'History', 'fast-index' ) );
		$this->assertSame( $expected, __( 'Help & FAQ', 'fast-index' ) );
	}

	/**
	 * Without a pack nothing extra is loaded: WordPress finds the bundled file itself.
	 *
	 * @return void
	 */
	public function test_nothing_extra_without_a_language_pack() {
		$this->assertTrue( switch_to_locale( 'de_DE' ) );
		unload_textdomain( 'fast-index', true );
		$this->assertFalse( Fast_Index_Plugin::add_bundled_translation() );
		$this->assertFalse( is_textdomain_loaded( 'fast-index' ) );
	}
}
