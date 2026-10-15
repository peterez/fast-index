<?php
/**
 * Activation, deactivation, version upgrades and multisite set-up.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps every site of an install on the current data version.
 *
 * The installed version lives in the per-site option fast_index_db_version. It is compared
 * on plugins_loaded, so updates done through the admin, WP-CLI, auto-updates or FTP all
 * upgrade on the next request. Version 2.x had no version option: "0" means either a new
 * site or a 2.x site, and the 3.0.0 step handles both.
 */
class Fast_Index_Installer {

	/**
	 * Per-site option holding the installed data version (autoloaded: read on every request).
	 */
	const DB_VERSION_OPTION = 'fast_index_db_version';

	/**
	 * Network option tracking the upgrade sweep over all sites.
	 */
	const NETWORK_OPTION = 'fast_index_network_upgrade';

	/**
	 * Single event that upgrades the sites of a network in batches.
	 */
	const NETWORK_HOOK = 'fast_index_upgrade_network';

	/**
	 * Sites per batch on network activation and deactivation.
	 */
	const BATCH_SIZE = 100;

	/**
	 * Sites per run of the network upgrade sweep.
	 */
	const SWEEP_SIZE = 25;

	/**
	 * Seconds one sweep run may spend.
	 */
	const SWEEP_BUDGET = 20;

	/**
	 * Activation callback.
	 *
	 * @param bool $network_wide True when activated for the whole network.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		// Lets WordPress run the clean-up when the plugin is deleted. Freemius replaces this callback with its own
		// when the plugin is deactivated in the admin; both end in Fast_Index_Uninstaller::run_once().
		register_uninstall_hook( FAST_INDEX_FILE, array( 'Fast_Index_Uninstaller', 'run_once' ) );

		if ( ! is_multisite() || ! $network_wide ) {
			self::install_site();
			return;
		}

		if ( wp_is_large_network() ) {
			// Too many sites for one request: every site upgrades itself on its first request,
			// and the sweep handles the quiet ones.
			self::schedule_network_sweep();
			return;
		}

		$offset = 0;

		do {
			$site_ids = self::site_ids( $offset, self::BATCH_SIZE );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				self::install_site();
				restore_current_blog();
			}

			$offset += self::BATCH_SIZE;
			$found   = count( $site_ids );
		} while ( self::BATCH_SIZE === $found );
	}

	/**
	 * Deactivation callback. Removes scheduled events only; no data is deleted.
	 *
	 * @param bool $network_wide True when deactivated for the whole network.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( ! is_multisite() || ! $network_wide ) {
			Fast_Index_Cron::unschedule();
			return;
		}

		wp_unschedule_hook( self::NETWORK_HOOK );

		if ( wp_is_large_network() ) {
			// Too many sites for one request, as on activation. The events left on the sites find no handler while
			// the plugin is inactive; activating again keeps them (no duplicates), and deleting the plugin removes
			// them on every site.
			return;
		}

		$offset = 0;

		do {
			$site_ids = self::site_ids( $offset, self::BATCH_SIZE );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				Fast_Index_Cron::unschedule();
				restore_current_blog();
			}

			$offset += self::BATCH_SIZE;
			$found   = count( $site_ids );
		} while ( self::BATCH_SIZE === $found );
	}

	/**
	 * Sets up the current site: data upgrade and scheduled events.
	 *
	 * Used on activation and for new sites. It does not look at wp_installing():
	 * wpmu_create_blog() leaves that flag on after the new site's tables exist.
	 *
	 * @return void
	 */
	public static function install_site() {
		self::upgrade_site();
		Fast_Index_Cron::schedule();
		Fast_Index_Upgrade::remember_first_use();
	}

	/**
	 * Runs pending upgrade steps for the current site on a normal request.
	 * Cheap when the site is up to date. Skipped while WordPress itself is being installed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( wp_installing() ) {
			return;
		}

		self::upgrade_site();
	}

	/**
	 * Applies pending upgrade steps under a lock.
	 *
	 * @return void
	 */
	private static function upgrade_site() {
		if ( ! self::needs_upgrade() ) {
			return;
		}

		$token = Fast_Index_Lock::acquire( 'upgrade', 5 * MINUTE_IN_SECONDS );

		if ( false === $token ) {
			return; // Another request is upgrading this site.
		}

		try {
			self::run_steps( (string) get_option( self::DB_VERSION_OPTION, '0' ) );
		} finally {
			Fast_Index_Lock::release( 'upgrade', $token );
		}

		if ( is_multisite() && is_main_site() ) {
			self::schedule_network_sweep();
		}
	}

	/**
	 * Whether the current site is behind the code's data version.
	 *
	 * @return bool
	 */
	public static function needs_upgrade() {
		return version_compare( (string) get_option( self::DB_VERSION_OPTION, '0' ), FAST_INDEX_DB_VERSION, '<' );
	}

	/**
	 * Installs a site created after the plugin was network activated.
	 *
	 * Hooked to wp_initialize_site (priority 900, after core created the site's tables).
	 *
	 * @param WP_Site $new_site New site.
	 * @return void
	 */
	public static function on_initialize_site( $new_site ) {
		if ( ! $new_site instanceof WP_Site || ! self::is_network_active() ) {
			return; // Activated per site: the activation hook sets the site up.
		}

		switch_to_blog( (int) $new_site->blog_id );
		self::install_site();
		restore_current_blog();
	}

	/**
	 * Upgrades one batch of sites, then queues the next batch. Cron callback.
	 *
	 * Without this sweep a site that never gets a request would keep its old key files.
	 *
	 * @return void
	 */
	public static function upgrade_network_batch() {
		if ( ! is_multisite() ) {
			return;
		}

		$state     = self::sweep_state();
		$deadline  = time() + self::SWEEP_BUDGET;
		$site_ids  = self::site_ids( $state['offset'], self::SWEEP_SIZE );
		$processed = 0;

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			self::maybe_upgrade();
			restore_current_blog();

			++$processed;

			if ( time() >= $deadline ) {
				break;
			}
		}

		$state['offset'] += $processed;
		$state['done']    = count( $site_ids ) < self::SWEEP_SIZE && count( $site_ids ) === $processed;

		update_site_option( self::NETWORK_OPTION, $state );

		if ( ! $state['done'] ) {
			wp_schedule_single_event( time() + 30, self::NETWORK_HOOK );
		}
	}

	/**
	 * Queues the network sweep once per data version.
	 *
	 * @return void
	 */
	public static function schedule_network_sweep() {
		if ( ! is_multisite() || ! self::is_network_active() ) {
			return;
		}

		$state = self::sweep_state();

		if ( ! $state['done'] && ! wp_next_scheduled( self::NETWORK_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::NETWORK_HOOK );
		}
	}

	/**
	 * Whether the plugin is active for the whole network.
	 *
	 * @return bool
	 */
	public static function is_network_active() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( plugin_basename( FAST_INDEX_FILE ) );
	}

	/**
	 * Applies the steps newer than the installed version.
	 *
	 * @param string $installed Installed data version.
	 * @return void
	 */
	private static function run_steps( $installed ) {
		if ( version_compare( $installed, '3.0.0', '<' ) && true !== Fast_Index_Migration::to_3_0_0() ) {
			return; // Keep the old version: the step runs again on a later request.
		}

		Fast_Index_Cron::schedule();
		Fast_Index_Upgrade::remember_first_use();
		update_option( self::DB_VERSION_OPTION, FAST_INDEX_DB_VERSION, true );
	}

	/**
	 * Sweep progress for the current data version.
	 *
	 * @return array Keys: version, offset, done.
	 */
	private static function sweep_state() {
		$state = get_site_option( self::NETWORK_OPTION, array() );

		if ( ! is_array( $state ) || ! isset( $state['version'] ) || FAST_INDEX_DB_VERSION !== $state['version'] ) {
			return array(
				'version' => FAST_INDEX_DB_VERSION,
				'offset'  => 0,
				'done'    => false,
			);
		}

		return array(
			'version' => FAST_INDEX_DB_VERSION,
			'offset'  => isset( $state['offset'] ) ? absint( $state['offset'] ) : 0,
			'done'    => ! empty( $state['done'] ),
		);
	}

	/**
	 * One page of site IDs.
	 *
	 * @param int $offset First row.
	 * @param int $number Page size.
	 * @return int[]
	 */
	private static function site_ids( $offset, $number ) {
		return array_map(
			'intval',
			get_sites(
				array(
					'fields' => 'ids',
					'number' => $number,
					'offset' => $offset,
				)
			)
		);
	}
}
