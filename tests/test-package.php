<?php
/**
 * Release packages built by bin/package.sh.
 *
 * @package FastIndex
 */

/**
 * The WordPress.org package has no premium code and no development file; the premium package has the module.
 *
 * @group package
 */
class Fast_Index_Test_Package extends Fast_Index_Test_Case {

	/**
	 * Output folder of the build.
	 *
	 * @var string
	 */
	private static $out = '';

	/**
	 * Builds both packages once for the class.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass() {
		self::$out = trailingslashit( get_temp_dir() ) . 'fast-index-package-test-' . wp_generate_password( 8, false );
		wp_mkdir_p( self::$out );

		$script = dirname( __DIR__ ) . '/bin/package.sh';
		$wp     = getenv( 'WP_CLI' ) ? getenv( 'WP_CLI' ) : '/home/samet/.local/bin/wp';

		if ( ! is_executable( $script ) || ! is_executable( $wp ) || ! class_exists( 'ZipArchive' ) ) {
			return;
		}

		$output = array();
		$status = 0;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Test of the release script; arguments are escaped.
		exec( 'WP_CLI=' . escapeshellarg( $wp ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( self::$out ) . ' 2>&1', $output, $status );

		if ( 0 !== $status ) {
			self::$out = 'failed: ' . implode( "\n", $output );
		}
	}

	/**
	 * Removes the build output.
	 *
	 * @return void
	 */
	public static function wpTearDownAfterClass() {
		if ( is_dir( self::$out ) ) {
			foreach ( (array) glob( self::$out . '/*.zip' ) as $zip ) {
				wp_delete_file( $zip );
			}

			rmdir( self::$out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes the empty temporary folder of the test.
		}
	}

	/**
	 * Paths inside a zip.
	 *
	 * @param string $name File name in the output folder.
	 * @return string[]
	 */
	private function entries( $name ) {
		if ( 0 === strpos( self::$out, 'failed: ' ) ) {
			$this->fail( self::$out );
		}

		$file = self::$out . '/' . $name;

		if ( ! file_exists( $file ) ) {
			$this->markTestSkipped( 'bin/package.sh could not run here (WP-CLI, bash or ZipArchive missing).' );
		}

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $file ) );

		$paths = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$paths[] = (string) $zip->getNameIndex( $i );
		}

		$zip->close();

		return $paths;
	}

	/**
	 * Development files that neither package may contain.
	 *
	 * @param string[] $paths Zip entries.
	 * @return void
	 */
	private function assert_no_development_files( array $paths ) {
		foreach ( $paths as $path ) {
			$this->assertStringStartsWith( 'fast-index/', $path );
			$this->assertDoesNotMatchRegularExpression( '#^fast-index/(tests|bin|vendor|dist|node_modules|\.wordpress-org|\.git)(/|$)#', $path );
			$this->assertDoesNotMatchRegularExpression( '#^fast-index/(composer\.lock|phpunit\.xml\.dist|phpcs\.xml\.dist|DEVELOPMENT\.md|\.distignore|\.gitignore)$#', $path );
			$this->assertDoesNotMatchRegularExpression( '#\.(sh|zip|log)$#', $path );
		}
	}

	/**
	 * The WordPress.org package: no premium path outside the Freemius SDK, but every file of the free version.
	 *
	 * @return void
	 */
	public function test_wordpress_org_package_has_no_premium_code() {
		$paths = $this->entries( 'fast-index-' . FAST_INDEX_VERSION . '.zip' );

		$this->assertContains( 'fast-index/index.php', $paths );
		$this->assertContains( 'fast-index/readme.txt', $paths );
		$this->assertContains( 'fast-index/includes/class-fast-index-uninstaller.php', $paths );
		$this->assertNotContains( 'fast-index/uninstall.php', $paths, 'Freemius cannot record an uninstall when uninstall.php exists.' );
		$this->assertContains( 'fast-index/freemius-sdk/start.php', $paths );
		$this->assertContains( 'fast-index/assets/fast-index-icon.png', $paths );

		foreach ( $paths as $path ) {
			if ( 0 !== strpos( $path, 'fast-index/freemius-sdk/' ) ) {
				$this->assertStringNotContainsString( 'premium', strtolower( $path ), $path );
			}
		}

		$this->assert_no_development_files( $paths );
	}

	/**
	 * The premium package: the same files plus the module, under fast-index/.
	 *
	 * @return void
	 */
	public function test_premium_package_has_the_module() {
		$org     = $this->entries( 'fast-index-' . FAST_INDEX_VERSION . '.zip' );
		$premium = $this->entries( 'fast-index-premium-' . FAST_INDEX_VERSION . '.zip' );

		foreach ( glob( dirname( __DIR__ ) . '/includes/premium/*.php' ) as $file ) {
			$this->assertContains( 'fast-index/includes/premium/' . basename( $file ), $premium );
		}

		$extra = array_values( array_diff( $premium, $org ) );

		foreach ( $extra as $path ) {
			$this->assertStringStartsWith( 'fast-index/includes/premium/', $path );
		}

		$this->assertSame( array(), array_diff( $org, $premium ), 'The premium package has every file of the free one.' );

		// A free version that the Freemius deployment builds from the premium package leaves the module out too.
		$this->assertStringContainsString( '@fs_premium_only /includes/premium/', (string) file_get_contents( dirname( __DIR__ ) . '/index.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local file in a test.
		$this->assert_no_development_files( $premium );
	}
}
