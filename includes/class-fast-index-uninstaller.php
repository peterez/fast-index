<?php
/**
 * Clean-up when the plugin is deleted.
 *
 * The plugin has no uninstall.php: with that file WordPress would never call the uninstall hook of the Freemius
 * SDK, so Freemius could not record the uninstall (Freemius documentation, "uninstall.php File"). WordPress loads
 * index.php instead and fires uninstall_fast-index/index.php; run_once() is attached to the Freemius
 * after_uninstall action and, as a fallback, to that core action. This class names every option, event and post
 * type itself.
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
	 * Plugin files of the free version and of the premium package (Freemius installs the premium package in its
	 * own folder). Data is shared, so nothing is removed while the other copy is active.
	 */
	const PLUGIN_FILES = array( 'fast-index/index.php', 'fast-index-premium/index.php' );

	/**
	 * User meta holding the notices a user dismissed (see Fast_Index_Upgrade).
	 */
	const USER_META = 'fast_index_dismissed_notices';

	/**
	 * Whether the clean-up already ran in this request.
	 *
	 * @var bool
	 */
	private static $done = false;

	/**
	 * Scheduled events of every version.
	 *
	 * @return string[]
	 */
	public static function cron_hooks() {
		return array( 'fast_index_submit_url', 'fast_index_process_backlog', 'fast_index_retry', 'fast_index_upgrade_network', 'fiDailyCronHook' );
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
			'fast_index_first_use',
			'fast_index_first_success',
			'fast_index_first_indexnow',
			'fast_index_indexnow_key',
			'fast_index_inspection_usage',
		);
	}

	/**
	 * Uninstall callback. Runs at most once per request: from the Freemius after_uninstall action, from the core
	 * uninstall_fast-index/index.php action (when Freemius did not run it, for example without a logged-in user
	 * in WP-CLI) or from the uninstall hook registered on activation. Does nothing while the other copy of the
	 * plugin (free or premium) is still active, because both use the same data.
	 *
	 * @return void
	 */
	public static function run_once() {
		if ( self::$done || self::other_copy_is_active() ) {
			return;
		}

		self::$done = true;
		self::run();
	}

	/**
	 * Whether the free version or the premium package is still active on this site or on the network.
	 *
	 * @return bool
	 */
	public static function other_copy_is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::PLUGIN_FILES as $file ) {
			if ( is_plugin_active( $file ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cleans every site.
	 *
	 * @return void
	 */
	public static function run() {
		if ( ! is_multisite() ) {
			if ( self::uninstall_site() ) {
				self::remove_user_meta();
			}
			return;
		}

		$offset = 0;
		$kept   = false;

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
				$kept = ! self::uninstall_site() || $kept;
				restore_current_blog();
			}

			$offset += self::BATCH_SIZE;
			$found   = count( $site_ids );
		} while ( self::BATCH_SIZE === $found );

		// Network-wide upgrade progress is temporary data.
		delete_site_option( 'fast_index_network_upgrade' );

		// Users belong to the network: their dismissed notices go once no site keeps Fast Index data.
		if ( ! $kept ) {
			self::remove_user_meta();
		}
	}

	/**
	 * Cleans the current site.
	 *
	 * @return bool Whether all plugin data of the site was removed ("delete data" on).
	 */
	public static function uninstall_site() {
		self::remove_temporary_data();

		// Read the preference before the option is deleted.
		$settings = get_option( 'fast_index_options', array() );

		if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
			return false;
		}

		self::remove_log();

		foreach ( self::options() as $option ) {
			delete_option( $option );
		}

		return true;
	}

	/**
	 * Removes the dismissed-notice record of every user.
	 *
	 * @return void
	 */
	private static function remove_user_meta() {
		delete_metadata( 'user', 0, self::USER_META, '', true );
	}

	/**
	 * Removes scheduled events, transients, lock rows and the retry queue. History entries that
	 * waited for a key stay in History as not sent.
	 *
	 * @return void
	 */
	private static function remove_temporary_data() {
		global $wpdb;

		foreach ( self::cron_hooks() as $hook ) {
			wp_unschedule_hook( $hook );
		}

		delete_post_meta_by_key( '_fast_index_retry' );
		delete_post_meta_by_key( '_fast_index_inspect_due' );

		foreach ( array( '_transient_fast_index_', '_transient_timeout_fast_index_', 'fast_index_lock_' ) as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: removes the plugin's own rows by prefix.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		}
	}

	/**
	 * Removes the log entries of every channel (Google: fi_log, IndexNow: fast_index_now_log) and their meta.
	 *
	 * @return void
	 */
	private static function remove_log() {
		global $wpdb;

		foreach ( array( 'fi_log', 'fast_index_now_log' ) as $post_type ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: one statement each instead of a wp_delete_post() call per entry.
			$wpdb->query( $wpdb->prepare( "DELETE m FROM {$wpdb->postmeta} AS m INNER JOIN {$wpdb->posts} AS p ON p.ID = m.post_id WHERE p.post_type = %s", $post_type ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}
