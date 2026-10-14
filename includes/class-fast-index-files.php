<?php
/**
 * Small local file helpers.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and deletes local files.
 *
 * Reading uses the direct filesystem class on purpose: the files are PHP's own upload
 * temporary files and key files written by version 2.x into the uploads folder. FTP or
 * SSH transports cannot reach PHP's temporary folder and would ask for credentials.
 */
class Fast_Index_Files {

	/**
	 * Reads a local file.
	 *
	 * @param string $path      Absolute path.
	 * @param int    $max_bytes Largest accepted size.
	 * @return string|false Contents, false when missing, unreadable or too large.
	 */
	public static function read( $path, $max_bytes ) {
		if ( ! is_string( $path ) || '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		$size = filesize( $path );

		if ( false === $size || $size > $max_bytes ) {
			return false;
		}

		$contents = self::filesystem()->get_contents( $path );

		return is_string( $contents ) ? $contents : false;
	}

	/**
	 * Deletes a local file.
	 *
	 * @param string $path Absolute path.
	 * @return bool True when the file no longer exists.
	 */
	public static function delete( $path ) {
		wp_delete_file( $path );
		clearstatcache( true, $path );

		return ! file_exists( $path );
	}

	/**
	 * Direct filesystem access.
	 *
	 * @return WP_Filesystem_Direct
	 */
	private static function filesystem() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		return new WP_Filesystem_Direct( null );
	}
}
