<?php
/**
 * Premium: index status from the Search Console URL Inspection API.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asks Search Console whether an address that Google accepted is indexed.
 *
 * - The same service account key as the Indexing API is used (the main key), with the read-only Search Console
 *   scope and the thin JWT client of the free version.
 * - The property is the one set on the settings screen, or the one found with sites.list: a Domain property
 *   that covers the site's host, otherwise the longest URL-prefix property the site address starts with.
 * - Google allows 2,000 inspections per day for each property (and 600 per minute). Fast Index counts them per
 *   Pacific day and stops at DAILY_LIMIT; the automatic checks use at most AUTO_LIMIT of them.
 * - History gets an "Index Status" column with a Check button on every accepted Google entry. An accepted
 *   address is checked again automatically 24 hours after it was sent, and once more after 72 hours if it was not
 *   indexed yet: at most two automatic checks per address (hourly event of the free version).
 * - A card above History compares the addresses Google accepted in the last 30 days with those checked and those
 *   indexed; the numbers come from the History entries.
 *
 * @link https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect
 * @link https://developers.google.com/webmaster-tools/limits
 */
class Fast_Index_Premium_Inspection {

	/**
	 * URL Inspection endpoint.
	 */
	const INSPECT_URL = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

	/**
	 * Search Console sites.list endpoint: the properties the service account can read.
	 */
	const SITES_URL = 'https://www.googleapis.com/webmasters/v3/sites';

	/**
	 * Read-only Search Console scope.
	 */
	const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Inspections per day and property that Google allows.
	 */
	const DAILY_LIMIT = 2000;

	/**
	 * Inspections per day and property the automatic checks may use: half of the quota.
	 */
	const AUTO_LIMIT = 1000;

	/**
	 * Automatic checks per hourly run.
	 */
	const AUTO_PER_RUN = 50;

	/**
	 * Seconds one hourly run may spend.
	 */
	const TIME_BUDGET = 20;

	/**
	 * First automatic check, after the notification was accepted.
	 */
	const FIRST_CHECK = DAY_IN_SECONDS;

	/**
	 * Second automatic check, for addresses that were not indexed at the first one.
	 */
	const SECOND_CHECK = 3 * DAY_IN_SECONDS;

	/**
	 * Meta on a Google History entry: the index state (indexed, crawled, discovered, unknown or not_indexed).
	 */
	const META_STATE = '_fast_index_index_state';

	/**
	 * Meta on a Google History entry: the last inspection (array).
	 */
	const META_RESULT = '_fast_index_inspection';

	/**
	 * Meta on a Google History entry: when the next automatic check is due (Unix time).
	 */
	const META_DUE = '_fast_index_inspect_due';

	/**
	 * Meta on a Google History entry: automatic checks done.
	 */
	const META_RUNS = '_fast_index_inspect_runs';

	/**
	 * Per-site option: inspections of the current Pacific day, per property (autoload off).
	 */
	const USAGE_OPTION = 'fast_index_inspection_usage';

	/**
	 * Transient: the property found with sites.list (one day).
	 */
	const PROPERTY_TRANSIENT = 'fast_index_sc_property';

	/**
	 * Action of the Check button (admin-post.php).
	 */
	const CHECK_ACTION = 'fast_index_inspect';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'fast_index_token_scopes', array( __CLASS__, 'token_scopes' ) );
		add_filter( 'fast_index_history_columns', array( __CLASS__, 'columns' ) );
		add_action( 'fast_index_history_cell', array( __CLASS__, 'cell' ), 10, 2 );
		add_action( 'fast_index_history_before_table', array( __CLASS__, 'render_card' ) );
		add_action( 'admin_post_' . self::CHECK_ACTION, array( __CLASS__, 'handle_check' ) );
		add_action( 'fast_index_logged', array( __CLASS__, 'schedule_checks' ), 10, 4 );
		add_action( 'fast_index_hourly', array( __CLASS__, 'run_due' ) );
		// Another key may be the user of other properties: find the property again.
		add_action( 'add_option_' . Fast_Index_Accounts::OPTION, array( __CLASS__, 'forget_property' ) );
		add_action( 'update_option_' . Fast_Index_Accounts::OPTION, array( __CLASS__, 'forget_property' ) );
	}

	/**
	 * Forgets the property found with sites.list.
	 *
	 * @return void
	 */
	public static function forget_property() {
		delete_transient( self::PROPERTY_TRANSIENT );
	}

	/**
	 * Adds the Search Console scope, so a replaced or deleted key also loses its Search Console token.
	 *
	 * @param string[] $scopes Scopes.
	 * @return string[]
	 */
	public static function token_scopes( $scopes ) {
		$scopes   = is_array( $scopes ) ? $scopes : array();
		$scopes[] = self::SCOPE;

		return $scopes;
	}

	/**
	 * Inspects the address of an accepted Google History entry and stores the result on it.
	 *
	 * @param int  $log_id    History entry.
	 * @param bool $automatic Whether this is an automatic check (it may use only AUTO_LIMIT a day).
	 * @return array|WP_Error Result (see parse()), or an error with a message that says what to do.
	 */
	public static function inspect( $log_id, $automatic = false ) {
		$entry = get_post( (int) $log_id );

		if ( ! $entry instanceof WP_Post || Fast_Index_Log::POST_TYPE !== $entry->post_type || Fast_Index_Log::STATUS_SENT !== $entry->post_status ) {
			return new WP_Error( 'fast_index_inspect_entry', __( 'Only addresses that Google accepted can be checked.', 'fast-index' ) );
		}

		$accounts = Fast_Index_Accounts::in_use();

		if ( array() === $accounts ) {
			return new WP_Error( 'fast_index_inspect_key', __( 'Upload a service account key on the settings screen first.', 'fast-index' ) );
		}

		$client   = new Fast_Index_Google_Client( reset( $accounts ), self::SCOPE );
		$property = self::property( $client );

		if ( is_wp_error( $property ) ) {
			return $property;
		}

		if ( ! self::has_quota( $property, $automatic ) ) {
			return new WP_Error(
				'fast_index_inspect_quota',
				sprintf(
					/* translators: %s: number of checks per day. */
					__( 'Search Console allows %s index status checks per day for each property, and they are used up for today. Try again tomorrow.', 'fast-index' ),
					number_format_i18n( self::DAILY_LIMIT )
				),
				array( 'status' => 429 )
			);
		}

		self::count_use( $property, $automatic );

		$response = $client->request(
			'POST',
			self::INSPECT_URL,
			array(
				'inspectionUrl' => (string) $entry->post_content,
				'siteUrl'       => $property,
				'languageCode'  => 'en-US',
			),
			'inspect'
		);

		if ( is_wp_error( $response ) ) {
			return self::explain( $response, $property );
		}

		$result = self::parse( $response );

		update_post_meta( $entry->ID, self::META_RESULT, $result );
		update_post_meta( $entry->ID, self::META_STATE, $result['state'] );

		return $result;
	}

	/**
	 * The Search Console property to ask: the one on the settings screen, or the one found with sites.list.
	 *
	 * @param Fast_Index_Google_Client $client Client with the Search Console scope.
	 * @return string|WP_Error
	 */
	public static function property( Fast_Index_Google_Client $client ) {
		$settings = Fast_Index_Settings::get();

		if ( '' !== $settings['sc_property'] ) {
			return $settings['sc_property'];
		}

		$cached = get_transient( self::PROPERTY_TRANSIENT );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$sites = $client->request( 'GET', self::SITES_URL, null, 'sites' );

		if ( is_wp_error( $sites ) ) {
			return self::explain( $sites, '' );
		}

		$property = self::best_property( isset( $sites['siteEntry'] ) && is_array( $sites['siteEntry'] ) ? $sites['siteEntry'] : array(), home_url( '/' ) );

		if ( '' === $property ) {
			return new WP_Error( 'fast_index_inspect_property', self::not_a_user_message(), array( 'status' => 403 ) );
		}

		set_transient( self::PROPERTY_TRANSIENT, $property, DAY_IN_SECONDS );

		return $property;
	}

	/**
	 * Picks the property that covers a site address: a Domain property of its host (or of a parent domain), else the
	 * longest URL-prefix property the address starts with. Unverified entries are left out.
	 *
	 * @param array[] $entries siteEntry list of sites.list (siteUrl, permissionLevel).
	 * @param string  $home    Home address of the site.
	 * @return string Property, empty when none covers the site.
	 */
	public static function best_property( array $entries, $home ) {
		$host   = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$home   = strtolower( trailingslashit( $home ) );
		$domain = '';
		$prefix = '';

		foreach ( $entries as $entry ) {
			$url   = is_array( $entry ) && isset( $entry['siteUrl'] ) ? (string) $entry['siteUrl'] : '';
			$level = is_array( $entry ) && isset( $entry['permissionLevel'] ) ? (string) $entry['permissionLevel'] : '';

			if ( '' === $url || 'siteUnverifiedUser' === $level ) {
				continue;
			}

			if ( 0 === strpos( $url, 'sc-domain:' ) ) {
				$name = strtolower( substr( $url, 10 ) );

				if ( '' !== $name && ( $host === $name || substr( $host, -strlen( '.' . $name ) ) === '.' . $name ) && strlen( $name ) > strlen( substr( $domain, 10 ) ) ) {
					$domain = 'sc-domain:' . $name;
				}
			} elseif ( 0 === strpos( $home, strtolower( trailingslashit( $url ) ) ) && strlen( $url ) > strlen( $prefix ) ) {
				$prefix = $url;
			}
		}

		return '' !== $domain ? $domain : $prefix;
	}

	/**
	 * Reads the index state from an inspection answer.
	 *
	 * @param array $response Decoded answer of index:inspect.
	 * @return array Keys: state, verdict, coverage, last_crawl (Unix time, 0 when never crawled), link, checked.
	 */
	public static function parse( array $response ) {
		$result   = isset( $response['inspectionResult'] ) && is_array( $response['inspectionResult'] ) ? $response['inspectionResult'] : array();
		$index    = isset( $result['indexStatusResult'] ) && is_array( $result['indexStatusResult'] ) ? $result['indexStatusResult'] : array();
		$verdict  = isset( $index['verdict'] ) ? (string) $index['verdict'] : '';
		$coverage = isset( $index['coverageState'] ) ? sanitize_text_field( (string) $index['coverageState'] ) : '';
		$crawl    = isset( $index['lastCrawlTime'] ) ? strtotime( (string) $index['lastCrawlTime'] ) : false;
		$link     = isset( $result['inspectionResultLink'] ) ? esc_url_raw( (string) $result['inspectionResultLink'] ) : '';

		if ( 'PASS' === $verdict ) {
			$state = 'indexed';
		} elseif ( false !== stripos( $coverage, 'crawled' ) ) {
			$state = 'crawled';
		} elseif ( false !== stripos( $coverage, 'discovered' ) ) {
			$state = 'discovered';
		} elseif ( false !== stripos( $coverage, 'unknown' ) ) {
			$state = 'unknown';
		} else {
			$state = 'not_indexed';
		}

		return array(
			'state'      => $state,
			'verdict'    => preg_replace( '/[^A-Z_]/', '', $verdict ),
			'coverage'   => substr( $coverage, 0, 200 ),
			'last_crawl' => false === $crawl ? 0 : (int) $crawl,
			'link'       => 0 === strpos( $link, 'https://search.google.com/' ) ? $link : '',
			'checked'    => time(),
		);
	}

	/**
	 * Name of an index state.
	 *
	 * @param string $state State from parse().
	 * @return string
	 */
	public static function state_label( $state ) {
		switch ( $state ) {
			case 'indexed':
				return __( 'Indexed', 'fast-index' );
			case 'crawled':
				return __( 'Crawled, not indexed', 'fast-index' );
			case 'discovered':
				return __( 'Discovered, not indexed', 'fast-index' );
			case 'unknown':
				return __( 'Unknown to Google', 'fast-index' );
			default:
				return __( 'Not indexed', 'fast-index' );
		}
	}

	/**
	 * Turns an error of Google into a message that says what to do.
	 *
	 * @param WP_Error $error    Error of the client.
	 * @param string   $property Property that was asked, empty for sites.list.
	 * @return WP_Error
	 */
	public static function explain( WP_Error $error, $property ) {
		$status  = Fast_Index_Google_Client::error_status( $error );
		$data    = $error->get_error_data();
		$detail  = is_array( $data ) && isset( $data['detail'] ) ? (string) $data['detail'] : '';
		$context = Fast_Index_Google_Client::error_context( $error );

		if ( 'token' === $context && $status >= 400 && $status < 500 ) {
			$message = __( 'Google did not accept the service account key. Upload a new key on the settings screen.', 'fast-index' );
		} elseif ( 403 === $status && in_array( $detail, array( 'SERVICE_DISABLED', 'accessNotConfigured' ), true ) ) {
			$message = __( 'The Google Search Console API is not enabled in the Google Cloud project of the key. Enable it in the Google Cloud console, wait a few minutes and try again.', 'fast-index' );
		} elseif ( 403 === $status ) {
			$message = '' !== $property
				/* translators: %s: Search Console property, such as sc-domain:example.com. */
				? sprintf( __( 'Search Console refused the check for %s: the service account is not a user of this property, or the address is not part of it. Add the service account to the property in Search Console (Settings, Users and permissions), or set the right property on the settings screen.', 'fast-index' ), $property )
				: self::not_a_user_message();
		} elseif ( 429 === $status ) {
			$message = sprintf(
				/* translators: %s: number of checks per day. */
				__( 'Search Console allows %s index status checks per day for each property, and they are used up for today. Try again tomorrow.', 'fast-index' ),
				number_format_i18n( self::DAILY_LIMIT )
			);
		} elseif ( 0 === $status ) {
			$message = __( 'Could not connect to Google.', 'fast-index' );
		} else {
			/* translators: %d: HTTP status code returned by Google. */
			$message = sprintf( __( 'Google answered with HTTP status %d.', 'fast-index' ), $status );
		}

		return new WP_Error( 'fast_index_inspect_failed', $message, array( 'status' => $status ) );
	}

	/**
	 * Message when the service account can read no property that covers the site.
	 *
	 * @return string
	 */
	public static function not_a_user_message() {
		return __( 'The service account is not a user of a Search Console property that covers this site. Add the service account to the property in Search Console (Settings, Users and permissions), or set the property on the settings screen.', 'fast-index' );
	}

	/**
	 * Inspections of today (Pacific Time), for a property.
	 *
	 * @param string $property Property.
	 * @return int[] Keys: all, auto.
	 */
	public static function usage( $property ) {
		$stored = get_option( self::USAGE_OPTION, array() );
		$day    = self::day();

		if ( ! is_array( $stored ) || ! isset( $stored['day'] ) || $day !== $stored['day'] || ! isset( $stored['counts'][ $property ] ) ) {
			return array(
				'all'  => 0,
				'auto' => 0,
			);
		}

		return array(
			'all'  => isset( $stored['counts'][ $property ]['all'] ) ? (int) $stored['counts'][ $property ]['all'] : 0,
			'auto' => isset( $stored['counts'][ $property ]['auto'] ) ? (int) $stored['counts'][ $property ]['auto'] : 0,
		);
	}

	/**
	 * Whether another inspection fits in today's quota.
	 *
	 * @param string $property  Property.
	 * @param bool   $automatic Whether the check is automatic.
	 * @return bool
	 */
	public static function has_quota( $property, $automatic ) {
		$usage = self::usage( $property );

		return $usage['all'] < self::DAILY_LIMIT && ( ! $automatic || $usage['auto'] < self::AUTO_LIMIT );
	}

	/**
	 * Counts one inspection.
	 *
	 * @param string $property  Property.
	 * @param bool   $automatic Whether the check is automatic.
	 * @return void
	 */
	private static function count_use( $property, $automatic ) {
		$stored = get_option( self::USAGE_OPTION, array() );
		$day    = self::day();

		if ( ! is_array( $stored ) || ! isset( $stored['day'] ) || $day !== $stored['day'] ) {
			$stored = array(
				'day'    => $day,
				'counts' => array(),
			);
		}

		$usage                         = self::usage( $property );
		$stored['counts'][ $property ] = array(
			'all'  => $usage['all'] + 1,
			'auto' => $usage['auto'] + ( $automatic ? 1 : 0 ),
		);

		update_option( self::USAGE_OPTION, $stored, false );
	}

	/**
	 * Today's date in Pacific Time, the day of Google's API quotas.
	 *
	 * @return string Y-m-d.
	 */
	private static function day() {
		$date = new DateTime( 'now', new DateTimeZone( Fast_Index_Accounts::QUOTA_TIME_ZONE ) );

		return $date->format( 'Y-m-d' );
	}

	/**
	 * Plans the automatic checks of an accepted address: the first one a day later. Earlier entries of the same
	 * post stop waiting for theirs, so an address is never checked twice for two notifications.
	 *
	 * @param int    $log_id  History entry.
	 * @param int    $code    Answer of Google.
	 * @param string $url     Address.
	 * @param int    $post_id Post, 0 when unknown.
	 * @return void
	 */
	public static function schedule_checks( $log_id, $code, $url, $post_id ) {
		if ( 200 !== (int) $code ) {
			return;
		}

		if ( (int) $post_id > 0 ) {
			$older = get_posts(
				array(
					'post_type'   => Fast_Index_Log::POST_TYPE,
					'post_status' => Fast_Index_Log::STATUS_SENT,
					'post_parent' => (int) $post_id,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only entries waiting for a check carry the key.
					'meta_key'    => self::META_DUE,
					'fields'      => 'ids',
					'numberposts' => 20,
				)
			);

			foreach ( $older as $id ) {
				if ( (int) $id !== (int) $log_id ) {
					delete_post_meta( (int) $id, self::META_DUE );
				}
			}
		}

		update_post_meta( (int) $log_id, self::META_DUE, time() + self::FIRST_CHECK );
		update_post_meta( (int) $log_id, self::META_RUNS, 0 );
		unset( $url );
	}

	/**
	 * Hourly run: the automatic checks that are due, within their share of the quota and the time budget.
	 *
	 * @return int Number of checks made.
	 */
	public static function run_due() {
		if ( ! Fast_Index_Settings::is_active( Fast_Index_Settings::get() ) ) {
			return 0;
		}

		$due = get_posts(
			array(
				'post_type'    => Fast_Index_Log::POST_TYPE,
				'post_status'  => Fast_Index_Log::STATUS_SENT,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Only entries waiting for a check carry the key; hourly cron.
				'meta_key'     => self::META_DUE,
				'meta_value'   => time(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
				'meta_type'    => 'NUMERIC',
				'meta_compare' => '<=',
				'orderby'      => 'meta_value_num',
				'order'        => 'ASC',
				'fields'       => 'ids',
				'numberposts'  => self::AUTO_PER_RUN,
			)
		);

		$deadline = time() + self::TIME_BUDGET;
		$checked  = 0;

		foreach ( $due as $log_id ) {
			if ( time() >= $deadline ) {
				break;
			}

			$result = self::inspect( (int) $log_id, true );

			if ( is_wp_error( $result ) ) {
				// Quota, key, property or connection: every other check would fail the same way this hour.
				break;
			}

			++$checked;
			$runs = (int) get_post_meta( (int) $log_id, self::META_RUNS, true ) + 1;
			update_post_meta( (int) $log_id, self::META_RUNS, $runs );

			if ( $runs < 2 && 'indexed' !== $result['state'] ) {
				$sent = (int) strtotime( (string) get_post_field( 'post_date_gmt', (int) $log_id ) . ' UTC' );
				update_post_meta( (int) $log_id, self::META_DUE, max( time() + MINUTE_IN_SECONDS, $sent + self::SECOND_CHECK ) );
			} else {
				delete_post_meta( (int) $log_id, self::META_DUE );
			}
		}

		return $checked;
	}

	/**
	 * Adds the Index Status column to History.
	 *
	 * @param string[] $columns Extra columns.
	 * @return string[]
	 */
	public static function columns( $columns ) {
		$columns          = is_array( $columns ) ? $columns : array();
		$columns['index'] = __( 'Index Status', 'fast-index' );

		return $columns;
	}

	/**
	 * Prints the Index Status cell of a History entry: the last result and the Check button.
	 *
	 * @param string $column Column key.
	 * @param array  $entry  History entry.
	 * @return void
	 */
	public static function cell( $column, $entry ) {
		if ( 'index' !== $column || ! is_array( $entry ) || Fast_Index_Log::CHANNEL_GOOGLE !== $entry['channel'] || 200 !== (int) $entry['code'] ) {
			return;
		}

		$result = get_post_meta( (int) $entry['id'], self::META_RESULT, true );
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		if ( is_array( $result ) && isset( $result['state'], $result['checked'] ) ) {
			echo '<span class="' . esc_attr( 'fi-index-state fi-index-state--' . $result['state'] ) . '">' . esc_html( self::state_label( $result['state'] ) ) . '</span>';

			if ( '' !== $result['coverage'] ) {
				// Google's own words, in English.
				echo '<br><span class="description"><bdi dir="ltr">' . esc_html( $result['coverage'] ) . '</bdi></span>';
			}

			if ( $result['last_crawl'] > 0 ) {
				/* translators: %s: date and time. */
				echo '<br><span class="description">' . esc_html( sprintf( __( 'Last crawl: %s', 'fast-index' ), wp_date( $format, $result['last_crawl'] ) ) ) . '</span>';
			}

			/* translators: %s: date and time. */
			echo '<br><span class="description">' . esc_html( sprintf( __( 'Checked: %s', 'fast-index' ), wp_date( $format, $result['checked'] ) ) ) . '</span>';

			if ( '' !== $result['link'] ) {
				echo '<br><a href="' . esc_url( $result['link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open in Search Console', 'fast-index' ) . '<span class="screen-reader-text"> ' . esc_html__( '(external link, opens in a new tab)', 'fast-index' ) . '</span></a>';
			}
		}

		echo '<form method="post" class="fi-index-check" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::CHECK_ACTION ) . '" />';
		echo '<input type="hidden" name="fast_index_log" value="' . esc_attr( (string) (int) $entry['id'] ) . '" />';
		wp_nonce_field( self::CHECK_ACTION, Fast_Index_Admin_Actions::NONCE_FIELD );
		echo '<button type="submit" class="button button-small">' . esc_html( is_array( $result ) ? __( 'Check again', 'fast-index' ) : __( 'Check', 'fast-index' ) ) . '</button>';
		echo '</form>';
	}

	/**
	 * Check button handler: capability, nonce, then one inspection; the result is shown on History.
	 *
	 * @return void
	 */
	public static function handle_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			Fast_Index_Admin_Actions::forbidden();
		}

		check_admin_referer( self::CHECK_ACTION, Fast_Index_Admin_Actions::NONCE_FIELD );

		$log_id = isset( $_POST['fast_index_log'] ) ? absint( wp_unslash( $_POST['fast_index_log'] ) ) : 0;
		$result = self::inspect( $log_id );

		if ( is_wp_error( $result ) ) {
			Fast_Index_Admin::flash( 'error', $result->get_error_message() );
		} else {
			Fast_Index_Admin::flash(
				'success',
				sprintf(
					/* translators: 1: address, 2: index state such as "Indexed". */
					__( 'Index status of %1$s: %2$s.', 'fast-index' ),
					(string) get_post_field( 'post_content', $log_id ),
					self::state_label( $result['state'] )
				)
			);
		}

		$referer = wp_get_referer();

		wp_safe_redirect( $referer ? $referer : admin_url( 'admin.php?page=' . Fast_Index_Admin::SLUG_HISTORY ) );
		exit;
	}

	/**
	 * Numbers of the card: different addresses Google accepted since a moment, of them checked, of them indexed.
	 *
	 * @param int $since Unix time.
	 * @return int[] Keys: accepted, checked, indexed.
	 */
	public static function counts( $since ) {
		global $wpdb;

		$after = gmdate( 'Y-m-d H:i:s', (int) $since );
		$sql   = "SELECT COUNT(DISTINCT p.post_content) FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS m ON m.post_id = p.ID AND m.meta_key = %s
			WHERE p.post_type = %s AND p.post_status = %s AND p.post_date_gmt >= %s AND ( %s = '' OR m.meta_value = %s )";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- COUNT(DISTINCT) over the log and its meta has no WP_Query equivalent; History only; $sql is a fixed string with placeholders.
		$checked = (int) $wpdb->get_var( $wpdb->prepare( $sql, self::META_STATE, Fast_Index_Log::POST_TYPE, Fast_Index_Log::STATUS_SENT, $after, '', '' ) );
		$indexed = (int) $wpdb->get_var( $wpdb->prepare( $sql, self::META_STATE, Fast_Index_Log::POST_TYPE, Fast_Index_Log::STATUS_SENT, $after, 'indexed', 'indexed' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'accepted' => Fast_Index_Log::count_accepted_addresses( (int) $since ),
			'checked'  => $checked,
			'indexed'  => $indexed,
		);
	}

	/**
	 * Prints the card above History: accepted, checked and indexed addresses of the last 30 days, and today's checks.
	 *
	 * @return void
	 */
	public static function render_card() {
		$counts   = self::counts( time() - 30 * DAY_IN_SECONDS );
		$settings = Fast_Index_Settings::get();
		$property = '' !== $settings['sc_property'] ? $settings['sc_property'] : (string) get_transient( self::PROPERTY_TRANSIENT );
		$cells    = array(
			__( 'Accepted by Google, last 30 days', 'fast-index' ) => $counts['accepted'],
			__( 'Checked in Search Console', 'fast-index' ) => $counts['checked'],
			__( 'Indexed', 'fast-index' ) => $counts['indexed'],
		);

		echo '<div class="fi-index-card">';
		echo '<h2>' . esc_html__( 'Index Status', 'fast-index' ) . '</h2><dl>';

		foreach ( $cells as $label => $number ) {
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( number_format_i18n( $number ) ) . '</dd></div>';
		}

		echo '</dl><p class="description">';
		echo esc_html__( 'Each address is checked automatically 24 hours after Google accepted it, and again after 72 hours if it was not indexed yet. The Check button in the list checks one address now.', 'fast-index' );

		if ( '' !== $property ) {
			$usage = self::usage( $property );
			echo ' ' . esc_html(
				sprintf(
					/* translators: 1: Search Console property, 2: checks made today, 3: checks allowed per day. */
					__( 'Property %1$s: %2$s of %3$s checks used today.', 'fast-index' ),
					$property,
					number_format_i18n( $usage['all'] ),
					number_format_i18n( self::DAILY_LIMIT )
				)
			);
		}

		echo '</p></div>';
	}
}
