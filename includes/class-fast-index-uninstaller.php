<?php
/**
 * Clean-up when the plugin is deleted.
 *
 * Used only by uninstall.php. The rest of the plugin is not loaded then, so this class
 * names every option, event and post type itself.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Removes temporary data always, and all plugin data only when the user asked for it.
 *
 * Kept by default (restored if the plugin is installed again): settings, keys, log.
 * Freemius keeps its own fs_* options; they are shared with other Freemius products.
 */
class Fast_Index_Uninstaller {

	/**
	 * Sites handled per batch on multisite.
	 */
	const BATCH_SIZE = 100;

	/**
	 * Scheduled events of every version.
	 *
	 * @return string[]
	 */
	public static function cron_hooks() {
		return array( 'fast_index_submit_url', 'fast_index_process_backlog', 'fast_index_upgrade_network', 'fiDailyCronHook' );
	}

	/**
	 * Options removed when "delete data" is on.
	 *
	 * @return string[]
	 */
	public static function options() {
		return array(
			'fast_index_options',
			'fast_index_service_accounts',
			'fast_index_account_statuses',
			'fast_index_db_version',
			'fast_index_notice_key_rotation',
			'fast_index_migration_issues',
		);
	}

	/**
	 * Cleans every site.
	 *
	 * @return void
	 */
	public static function run() {
		if ( ! is_multisite() ) {
			self::uninstall_site();
			return;
		}

		$offset = 0;

		do {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => self::BATCH_SIZE,
					'offset' => $offset,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::uninstall_site();
				restore_current_blog();
			}

			$offset += self::BATCH_SIZE;
			$found   = count( $site_ids );
		} while ( self::BATCH_SIZE === $found );

		// Network-wide upgrade progress is temporary data.
		delete_site_option( 'fast_index_network_upgrade' );
	}

	/**
	 * Cleans the current site.
	 *
	 * @return void
	 */
	public static function uninstall_site() {
		self::remove_temporary_data();

		// Read the preference before the option is deleted.
		$settings = get_option( 'fast_index_options', array() );

		if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
			return;
		}

		self::remove_log();

		foreach ( self::options() as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Removes scheduled events, transients and lock rows.
	 *
	 * @return void
	 */
	private static function remove_temporary_data() {
		global $wpdb;

		foreach ( self::cron_hooks() as $hook ) {
			wp_unschedule_hook( $hook );
		}

		foreach ( array( '_transient_fast_index_', '_transient_timeout_fast_index_', 'fast_index_lock_' ) as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: removes the plugin's own rows by prefix.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		}
	}

	/**
	 * Removes the log entries and their meta.
	 *
	 * @return void
	 */
	private static function remove_log() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: one statement each instead of a wp_delete_post() call per entry.
		$wpdb->query( $wpdb->prepare( "DELETE m FROM {$wpdb->postmeta} AS m INNER JOIN {$wpdb->posts} AS p ON p.ID = m.post_id WHERE p.post_type = %s", 'fi_log' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type = %s", 'fi_log' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
