<?php
/**
 * The shared CultDevOps UI library (assets/css/cdo-ui.css, assets/js/cdo-ui.js and their translations) is the copy
 * that tools/cdo-ui/sync.sh made: same version in both files, the ten JSON translations named after the script path,
 * and, where the CultDevOps workspace is present, byte for byte the source.
 *
 * @package FastIndex
 */

/**
 * CultDevOps UI library copy.
 */
class Fast_Index_Test_Cdo_Ui extends Fast_Index_Test_Case {

	/**
	 * Plugin folder with a trailing slash.
	 *
	 * @return string
	 */
	private function dir() {
		return dirname( __DIR__ ) . '/';
	}

	/**
	 * The CSS and the script carry the same library version in their header.
	 */
	public function test_css_and_script_have_the_same_version() {
		$css = file_get_contents( $this->dir() . 'assets/css/cdo-ui.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$js  = file_get_contents( $this->dir() . 'assets/js/cdo-ui.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertSame( 1, preg_match( '/\* CultDevOps UI (\d+\.\d+\.\d+)/', $css, $css_version ) );
		$this->assertSame( 1, preg_match( "/var VERSION = '(\\d+\\.\\d+\\.\\d+)';/", $js, $js_version ) );
		$this->assertSame( $css_version[1], $js_version[1] );
		$this->assertTrue( version_compare( $css_version[1], '2.0.0', '>=' ) );
	}

	/**
	 * The script's sentences are translated in the ten languages; the file name carries md5( 'assets/js/cdo-ui.js' ),
	 * which is how wp_set_script_translations() finds it.
	 */
	public function test_script_translations_exist_for_every_language() {
		$md5 = md5( 'assets/js/cdo-ui.js' );
		foreach ( array( 'tr_TR', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_BR', 'nl_NL', 'ru_RU', 'ar', 'ja' ) as $locale ) {
			$file = $this->dir() . 'languages/fast-index-' . $locale . '-' . $md5 . '.json';
			$this->assertFileExists( $file );
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->assertArrayHasKey( 'Show options', $data['locale_data']['messages'], $locale );
			$this->assertNotSame( '', $data['locale_data']['messages']['Show options'][0], $locale );
		}
	}

	/**
	 * In the CultDevOps workspace the copies are identical to tools/cdo-ui (the identity test of KARARLAR (25)).
	 */
	public function test_copies_match_the_library_source() {
		$source = dirname( __DIR__, 4 ) . '/tools/cdo-ui/';
		if ( ! is_dir( $source ) ) {
			$this->markTestSkipped( 'The CultDevOps workspace (tools/cdo-ui) is not here.' );
		}
		$this->assertSame( hash_file( 'sha256', $source . 'cdo-ui.css' ), hash_file( 'sha256', $this->dir() . 'assets/css/cdo-ui.css' ), 'Run tools/cdo-ui/sync.sh.' );
		$this->assertSame( hash_file( 'sha256', $source . 'cdo-ui.js' ), hash_file( 'sha256', $this->dir() . 'assets/js/cdo-ui.js' ), 'Run tools/cdo-ui/sync.sh.' );
	}
}
