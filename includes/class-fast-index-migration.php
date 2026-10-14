<?php
/**
 * Data migration from version 2.x.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Moves the service account keys that 2.x saved as public files in the uploads folder.
 *
 * Version 2.x kept, in fast_index_options['json_file'], one entry per key:
 * md5( client_email ) => array( 'file' => absolute path, 'status' => int, 'mail' => email ).
 * Each file is read, validated and stored in the key option, then deleted from disk.
 * Every step is safe to repeat: a key that is already stored and whose file is gone
 * counts as migrated.
 */
class Fast_Index_Migration {

	/**
	 * Issue: no file at the stored path.
	 */
	const ISSUE_MISSING = 'missing';

	/**
	 * Issue: the file exists but could not be read.
	 */
	const ISSUE_UNREADABLE = 'unreadable';

	/**
	 * Issue: the file is not a valid service account key.
	 */
	const ISSUE_INVALID = 'invalid';

	/**
	 * Issue: the stored path is not a .json file inside the uploads folder; it was left alone.
	 */
	const ISSUE_OUTSIDE_UPLOADS = 'outside_uploads';

	/**
	 * Issue: the key was stored, but the old file could not be deleted.
	 */
	const ISSUE_DELETE_FAILED = 'delete_failed';

	/**
	 * Migration to 3.0.0 for the current site.
	 *
	 * @return bool True when finished (issues are reported to the admin, not retried).
	 */
	public static function to_3_0_0() {
		wp_unschedule_hook( Fast_Index_Cron::LEGACY_HOOK );

		$raw = get_option( Fast_Index_Settings::OPTION, null );

		if ( ! is_array( $raw ) ) {
			return true;
		}

		$legacy = isset( $raw['json_file'] ) && is_array( $raw['json_file'] ) ? $raw['json_file'] : array();
		$issues = array();

		foreach ( $legacy as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['file'] ) || ! is_string( $entry['file'] ) ) {
				continue;
			}

			$issue = self::migrate_entry( $entry );

			if ( null !== $issue ) {
				$issues[] = $issue;
			}
		}

		// Saving the normalized settings drops json_file: the paths are never needed again.
		Fast_Index_Settings::save( Fast_Index_Settings::normalize( $raw ) );

		if ( array() !== $legacy ) {
			Fast_Index_Notices::flag_key_rotation();
		}

		Fast_Index_Notices::add_issues( $issues );

		return true;
	}

	/**
	 * Migrates one legacy key file.
	 *
	 * @param array $entry Legacy entry ( file, status, mail ).
	 * @return array|null Issue ( code, email, file ) or null on success.
	 */
	public static function migrate_entry( array $entry ) {
		$path  = wp_normalize_path( (string) $entry['file'] );
		$email = isset( $entry['mail'] ) && is_string( $entry['mail'] ) ? sanitize_email( $entry['mail'] ) : '';

		$relative = self::relative_upload_path( $path );

		if ( false === $relative ) {
			return self::issue( self::ISSUE_OUTSIDE_UPLOADS, $email, sanitize_file_name( wp_basename( $path ) ) );
		}

		if ( ! file_exists( $path ) ) {
			// Already migrated by an earlier, interrupted run, or deleted by the user.
			return Fast_Index_Accounts::has_email( $email ) ? null : self::issue( self::ISSUE_MISSING, $email, $relative );
		}

		$json = Fast_Index_Files::read( $path, Fast_Index_Accounts::MAX_KEY_BYTES );

		if ( false === $json ) {
			return self::issue( self::ISSUE_UNREADABLE, $email, $relative );
		}

		$account = Fast_Index_Accounts::parse_key_json( $json );

		if ( is_wp_error( $account ) ) {
			return self::issue( self::ISSUE_INVALID, $email, $relative );
		}

		$id = Fast_Index_Accounts::save( $account );
		self::carry_over_status( $id, $account['client_email'], $entry );

		return Fast_Index_Files::delete( $path ) ? null : self::issue( self::ISSUE_DELETE_FAILED, $account['client_email'], $relative );
	}

	/**
	 * Path relative to the uploads folder of the current site, if the file may be touched.
	 *
	 * Accepted: a .json path inside the uploads folder, without "..", that stays inside the
	 * folder after symbolic links are resolved.
	 *
	 * @param string $path Absolute, normalized path.
	 * @return string|false
	 */
	public static function relative_upload_path( $path ) {
		$uploads = wp_upload_dir( null, false );

		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		$base = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );

		if ( 0 !== strpos( $path, $base ) ) {
			return false;
		}

		$relative = substr( $path, strlen( $base ) );

		if ( '' === $relative || 0 !== validate_file( $relative ) || 'json' !== strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) ) ) {
			return false;
		}

		return self::resolves_inside( $path, $uploads['basedir'] ) ? $relative : false;
	}

	/**
	 * Whether an existing file stays inside a folder after symbolic links are resolved.
	 * A missing file passes: there is nothing to read or delete.
	 *
	 * @param string $path   File path.
	 * @param string $folder Folder path.
	 * @return bool
	 */
	private static function resolves_inside( $path, $folder ) {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		$real_path   = realpath( $path );
		$real_folder = realpath( $folder );

		if ( false === $real_path || false === $real_folder ) {
			return false;
		}

		return 0 === strpos( wp_normalize_path( $real_path ), trailingslashit( wp_normalize_path( $real_folder ) ) );
	}

	/**
	 * Keeps the last status of a migrated account.
	 *
	 * Version 2.x rested failing accounts with a network-wide transient "fi_" . md5( email ).
	 * A resting account keeps resting; otherwise only the displayed status is kept.
	 *
	 * @param string $id    Account ID.
	 * @param string $email Client email.
	 * @param array  $entry Legacy entry.
	 * @return void
	 */
	private static function carry_over_status( $id, $email, array $entry ) {
		$legacy_transient = 'fi_' . md5( $email );
		$resting          = get_site_transient( $legacy_transient );

		if ( false !== $resting && 200 !== (int) $resting ) {
			Fast_Index_Accounts::set_status( $id, (int) $resting );
		} elseif ( isset( $entry['status'] ) && is_numeric( $entry['status'] ) ) {
			Fast_Index_Accounts::remember_status( $id, (int) $entry['status'] );
		}

		delete_site_transient( $legacy_transient );
	}

	/**
	 * Builds an issue record. Values are stored raw and escaped when shown.
	 *
	 * @param string $code  Issue code.
	 * @param string $email Client email, may be empty.
	 * @param string $file  Path relative to the uploads folder, or a file name.
	 * @return array
	 */
	private static function issue( $code, $email, $file ) {
		return array(
			'code'  => $code,
			'email' => (string) $email,
			'file'  => (string) $file,
		);
	}
}
