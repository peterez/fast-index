<?php
/**
 * Premium: weekly email report.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends a short weekly summary to the site's administration email address with wp_mail(): no outside service.
 *
 * - The numbers come from the History entries of the last seven days, so they match History: accepted by Google,
 *   not accepted or not sent, accepted by IndexNow, checked in Search Console and indexed, and the published posts
 *   that Google has not accepted yet.
 * - The email is written in the language of the recipient: the user whose email address is the administration
 *   address, else the site language (switch_to_user_locale(), or switch_to_locale() before WordPress 6.2). The
 *   plugin's translation is loaded for that language and restored afterwards.
 * - It can be turned off on the settings screen (setting weekly_report, on by default); the email links there.
 * - A week without any History entry sends nothing.
 * - Multisite: each site has its own event and sends to its own administration address.
 */
class Fast_Index_Premium_Report {

	/**
	 * Weekly WP-Cron event.
	 */
	const HOOK = 'fast_index_weekly_report';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_action( 'fast_index_hourly', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Plans the weekly event once. Runs from the hourly event, so a site that just got the module starts within the
	 * hour; deactivation removes the event (Fast_Index_Cron::unschedule()).
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::HOOK );
		}
	}

	/**
	 * Numbers of a period, from the History entries.
	 *
	 * @param int $since Start (Unix time).
	 * @return int[] Keys: google_accepted, google_failed, indexnow_accepted, checked, indexed, waiting.
	 */
	public static function numbers( $since ) {
		$inspection = Fast_Index_Premium_Inspection::counts( $since );

		return array(
			'google_accepted'   => Fast_Index_Log::count_sent_since( $since ),
			'google_failed'     => self::count_failed_since( $since ),
			'indexnow_accepted' => Fast_Index_Log::count_sent_since( $since, Fast_Index_Log::INDEXNOW_POST_TYPE ),
			'checked'           => $inspection['checked'],
			'indexed'           => $inspection['indexed'],
			'waiting'           => Fast_Index_Log::count_waiting( Fast_Index_Settings::get() ),
		);
	}

	/**
	 * Google History entries since a moment that are not an acceptance (refused, failed, not sent).
	 *
	 * @param int $since Unix time.
	 * @return int
	 */
	private static function count_failed_since( $since ) {
		$query = new WP_Query(
			array(
				'post_type'              => Fast_Index_Log::POST_TYPE,
				'post_status'            => Fast_Index_Log::STATUS_FAILED,
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'column'    => 'post_date_gmt',
						'after'     => gmdate( 'Y-m-d H:i:s', (int) $since ),
						'inclusive' => true,
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Whether anything was written to History since a moment, in either channel.
	 *
	 * @param int $since Unix time.
	 * @return bool
	 */
	private static function has_activity( $since ) {
		$query = new WP_Query(
			array(
				'post_type'              => array_values( Fast_Index_Log::post_types() ),
				'post_status'            => array( Fast_Index_Log::STATUS_SENT, Fast_Index_Log::STATUS_FAILED ),
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'column'    => 'post_date_gmt',
						'after'     => gmdate( 'Y-m-d H:i:s', (int) $since ),
						'inclusive' => true,
					),
				),
			)
		);

		return array() !== $query->posts;
	}

	/**
	 * Sends the report of the last seven days. Cron handler.
	 *
	 * @return bool Whether an email was sent.
	 */
	public static function send() {
		$settings = Fast_Index_Settings::get();
		$to       = (string) get_option( 'admin_email' );
		$since    = time() - WEEK_IN_SECONDS;

		if ( empty( $settings['weekly_report'] ) || ! is_email( $to ) || ! self::has_activity( $since ) ) {
			return false;
		}

		$user     = get_user_by( 'email', $to );
		$locale   = $user instanceof WP_User ? get_user_locale( $user ) : get_locale();
		$switched = $user instanceof WP_User && function_exists( 'switch_to_user_locale' ) ? switch_to_user_locale( $user->ID ) : switch_to_locale( $locale );

		self::load_translation( $locale );

		$message = self::message( self::numbers( $since ), $since );
		$sent    = wp_mail( $to, $message['subject'], $message['body'] );

		if ( $switched ) {
			restore_previous_locale();
		}

		self::load_translation( determine_locale() );

		return (bool) $sent;
	}

	/**
	 * Subject and plain text body of the report, in the current language.
	 *
	 * @param int[] $numbers Numbers from numbers().
	 * @param int   $since   Start of the period.
	 * @return string[] Keys: subject, body.
	 */
	public static function message( array $numbers, $since ) {
		$site   = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$format = get_option( 'date_format' );
		$lines  = array(
			sprintf(
				/* translators: 1: site title, 2: site address, 3: first day, 4: last day. */
				__( 'Fast Index report for %1$s (%2$s), %3$s to %4$s.', 'fast-index' ),
				$site,
				home_url( '/' ),
				wp_date( $format, (int) $since ),
				wp_date( $format )
			),
			'',
			/* translators: %s: number of addresses. */
			sprintf( __( 'Accepted by Google: %s', 'fast-index' ), number_format_i18n( $numbers['google_accepted'] ) ),
			/* translators: %s: number of notifications. */
			sprintf( __( 'Not accepted by Google or not sent: %s', 'fast-index' ), number_format_i18n( $numbers['google_failed'] ) ),
			/* translators: %s: number of addresses. */
			sprintf( __( 'Accepted by IndexNow: %s', 'fast-index' ), number_format_i18n( $numbers['indexnow_accepted'] ) ),
			/* translators: 1: number of addresses checked, 2: number of them that are indexed. */
			sprintf( __( 'Checked in Search Console: %1$s, of them indexed: %2$s', 'fast-index' ), number_format_i18n( $numbers['checked'] ), number_format_i18n( $numbers['indexed'] ) ),
			/* translators: %s: number of published posts. */
			sprintf( __( 'Published posts that Google has not accepted yet: %s', 'fast-index' ), number_format_i18n( $numbers['waiting'] ) ),
			'',
			/* translators: %s: address of the History screen. */
			sprintf( __( 'History: %s', 'fast-index' ), admin_url( 'admin.php?page=' . Fast_Index_Admin::SLUG_HISTORY ) ),
			/* translators: %s: address of the settings screen. */
			sprintf( __( 'To stop this email, uncheck Weekly Email Report on the settings screen: %s', 'fast-index' ), admin_url( 'admin.php?page=' . Fast_Index_Admin::SLUG ) ),
		);

		return array(
			/* translators: %s: site title. */
			'subject' => sprintf( __( '[%s] Fast Index weekly report', 'fast-index' ), $site ),
			'body'    => implode( "\n", $lines ) . "\n",
		);
	}

	/**
	 * Loads the plugin's translation for a language: the language pack first, then the bundled file for the strings
	 * the pack lacks (as Fast_Index_Plugin::add_bundled_translation() does).
	 *
	 * @param string $locale Locale.
	 * @return void
	 */
	private static function load_translation( $locale ) {
		unload_textdomain( 'fast-index' );

		if ( 'en_US' === $locale ) {
			return;
		}

		$pack    = WP_LANG_DIR . '/plugins/fast-index-' . $locale . '.mo';
		$bundled = FAST_INDEX_PATH . 'languages/fast-index-' . $locale . '.mo';

		if ( is_readable( $pack ) ) {
			load_textdomain( 'fast-index', $pack, $locale );
		}

		if ( is_readable( $bundled ) ) {
			load_textdomain( 'fast-index', $bundled, $locale );
		}
	}
}
