<?php
/**
 * Where the free version points to the premium plan.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every place where the free version mentions the premium plan, so they stay honest and in one file:
 *
 * - the Premium box of the settings screen with a Free / Premium comparison,
 * - a "Premium" hint next to the settings sections that the premium plan extends,
 * - one line on the History screen with the real number of published posts that Google has not accepted yet,
 * - an explanation when a new key replaces the only key of the free version,
 * - "Settings" and "Upgrade" links on the Plugins screen,
 * - two notices, on the Fast Index screens only and each until the user dismisses it: a thank-you after Google
 *   accepted a notification (with a review request without any reward), and an offer one week after the plugin
 *   was first set up.
 *
 * Nothing here locks, hides or slows down a feature of the free version, and no text speaks of a discount or a
 * deadline. While the premium module is loaded none of the offers is shown.
 */
class Fast_Index_Upgrade {

	/**
	 * Per-site option: when Fast Index was first set up on the site (Unix time, autoload off).
	 */
	const FIRST_USE_OPTION = 'fast_index_first_use';

	/**
	 * Per-site option: when Google first accepted a notification sent by this version (Unix time, autoload off).
	 */
	const FIRST_SUCCESS_OPTION = 'fast_index_first_success';

	/**
	 * Per-site option: when IndexNow first accepted a notification (Unix time, autoload off).
	 */
	const FIRST_INDEXNOW_OPTION = 'fast_index_first_indexnow';

	/**
	 * User meta: notices the user dismissed, as name => Unix time.
	 */
	const USER_META = 'fast_index_dismissed_notices';

	/**
	 * Notice: thank-you after the first notification Google accepted.
	 */
	const NOTICE_SUCCESS = 'first_success';

	/**
	 * Notice: the premium plan, one week after the first setup.
	 */
	const NOTICE_OFFER = 'premium_offer';

	/**
	 * Seconds between the first setup and the offer notice.
	 */
	const OFFER_DELAY = WEEK_IN_SECONDS;

	/**
	 * Reviews page of the plugin on WordPress.org.
	 */
	const REVIEWS_URL = 'https://wordpress.org/support/plugin/fast-index/reviews/';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'plugin_action_links_' . plugin_basename( FAST_INDEX_FILE ), array( __CLASS__, 'action_links' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
	}

	/**
	 * Whether the premium plan is offered: the premium module is not loaded and Freemius can show its plans.
	 *
	 * @return bool
	 */
	public static function is_offered() {
		$url = self::upgrade_url();

		if ( Fast_Index_Plugin::premium_loaded() || '' === $url ) {
			return false;
		}

		// Network activation: Freemius keeps its plans page in the network admin, which only a super admin can open.
		return ! is_multisite() || 0 !== strpos( $url, network_admin_url() ) || current_user_can( 'manage_network_plugins' );
	}

	/**
	 * Address of the Freemius page with the plans (the Upgrade entry of the Fast Index menu).
	 *
	 * @return string Empty when Freemius is not available.
	 */
	public static function upgrade_url() {
		if ( ! function_exists( 'figi_fs' ) ) {
			return '';
		}

		$url = figi_fs()->get_upgrade_url();

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Stores when Fast Index was first set up on this site. Kept when it exists.
	 *
	 * @return void
	 */
	public static function remember_first_use() {
		add_option( self::FIRST_USE_OPTION, time(), '', false );
	}

	/**
	 * Stores when Google first accepted a notification. Kept when it exists.
	 *
	 * @return void
	 */
	public static function remember_success() {
		add_option( self::FIRST_SUCCESS_OPTION, time(), '', false );
	}

	/**
	 * Stores when IndexNow first accepted a notification (200 or 202). Kept when it exists.
	 *
	 * @return void
	 */
	public static function remember_indexnow_success() {
		add_option( self::FIRST_INDEXNOW_OPTION, time(), '', false );
	}

	/**
	 * The notice due for a user on a Fast Index screen: the thank-you first, then the offer. At most one at a time.
	 *
	 * @param int $user_id User ID.
	 * @param int $now     Unix time.
	 * @return string Notice name, empty when none is due.
	 */
	public static function due_notice( $user_id, $now ) {
		$dismissed = self::dismissed( $user_id );

		if ( ! isset( $dismissed[ self::NOTICE_SUCCESS ] ) && ( (int) get_option( self::FIRST_SUCCESS_OPTION, 0 ) > 0 || (int) get_option( self::FIRST_INDEXNOW_OPTION, 0 ) > 0 ) ) {
			return self::NOTICE_SUCCESS;
		}

		$first_use = (int) get_option( self::FIRST_USE_OPTION, 0 );

		if ( ! isset( $dismissed[ self::NOTICE_OFFER ] ) && self::is_offered() && $first_use > 0 && $now - $first_use >= self::OFFER_DELAY ) {
			return self::NOTICE_OFFER;
		}

		return '';
	}

	/**
	 * Notice names this class handles.
	 *
	 * @return string[]
	 */
	public static function notice_names() {
		return array( self::NOTICE_SUCCESS, self::NOTICE_OFFER );
	}

	/**
	 * Hides a notice for a user for good.
	 *
	 * @param int    $user_id User ID.
	 * @param string $notice  One of notice_names().
	 * @return void
	 */
	public static function dismiss( $user_id, $notice ) {
		if ( ! in_array( $notice, self::notice_names(), true ) || $user_id <= 0 ) {
			return;
		}

		$dismissed            = self::dismissed( $user_id );
		$dismissed[ $notice ] = time();

		update_user_meta( $user_id, self::USER_META, $dismissed );
	}

	/**
	 * Notices a user dismissed.
	 *
	 * @param int $user_id User ID.
	 * @return int[] Unix time by notice name.
	 */
	public static function dismissed( $user_id ) {
		$stored = get_user_meta( $user_id, self::USER_META, true );

		return is_array( $stored ) ? array_intersect_key( $stored, array_flip( self::notice_names() ) ) : array();
	}

	/**
	 * Prints the due notice on the Fast Index screens. Not on the dashboard, not on other screens.
	 *
	 * @return void
	 */
	public static function render_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof WP_Screen || ! Fast_Index_Admin::is_plugin_screen( $screen->id ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// The site may predate this version: the week starts on its first visit to a Fast Index screen.
		self::remember_first_use();

		$notice = self::due_notice( get_current_user_id(), time() );

		if ( '' === $notice ) {
			return;
		}

		$dismiss_url = Fast_Index_Notices::dismiss_url( $notice );
		$type        = self::NOTICE_SUCCESS === $notice ? 'success' : 'info';

		echo '<div class="' . esc_attr( 'notice notice-' . $type . ' is-dismissible fast-index-notice fi-upgrade-notice' ) . '" data-notice="' . esc_attr( $notice ) . '" data-dismiss-url="' . esc_url( $dismiss_url ) . '">';

		if ( self::NOTICE_SUCCESS === $notice ) {
			$accepted = (int) get_option( self::FIRST_SUCCESS_OPTION, 0 ) > 0 ? __( 'Google accepted a notification from this site.', 'fast-index' ) : __( 'IndexNow accepted a notification from this site.', 'fast-index' );

			echo '<p><strong>' . esc_html__( 'Fast Index is working.', 'fast-index' ) . '</strong> ' . esc_html( $accepted ) . '</p>';
			echo '<p>' . esc_html__( 'If Fast Index is useful to you, a review on WordPress.org helps other site owners find it.', 'fast-index' ) . ' <a href="' . esc_url( self::REVIEWS_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Rate Fast Index on WordPress.org', 'fast-index' ) . '</a></p>';

			if ( self::is_offered() ) {
				echo '<p>' . esc_html( self::summary() ) . ' <a href="' . esc_url( self::upgrade_url() ) . '">' . esc_html__( 'See the premium plan', 'fast-index' ) . '</a></p>';
			}
		} else {
			echo '<p><strong>' . esc_html_x( 'Fast Index Premium', 'Title of the notice about the premium plan', 'fast-index' ) . '</strong></p>';
			echo '<p>' . esc_html( self::summary() ) . ' ' . esc_html__( 'The free version keeps working as it is.', 'fast-index' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( self::upgrade_url() ) . '">' . esc_html__( 'See the premium plan', 'fast-index' ) . '</a></p>';
		}

		echo '<p><a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Do not show this again', 'fast-index' ) . '</a></p></div>';
	}

	/**
	 * One sentence on what the premium plan adds (notices and Help & FAQ).
	 *
	 * @return string
	 */
	public static function summary() {
		return __( 'The premium plan adds hourly batches of old posts within a daily limit you set, excluded categories and backup service account keys.', 'fast-index' );
	}

	/**
	 * The line after a bulk action that selected more items than are sent at a time.
	 *
	 * @return string
	 */
	public static function limit_line() {
		return __( 'The premium plan also sends published posts that Google has not accepted yet in hourly batches, within a daily limit you set.', 'fast-index' );
	}

	/**
	 * Rows of the Free / Premium comparison in the Premium box.
	 *
	 * @return array[] Each: label, free, premium. A value is a string, or true / false for included / not included.
	 */
	public static function comparison() {
		return array(
			array(
				'label'   => __( 'Notifications to Google when content is published, updated, trashed or deleted', 'fast-index' ),
				'free'    => true,
				'premium' => true,
			),
			array(
				'label'   => __( 'Posts, pages and custom post types', 'fast-index' ),
				'free'    => true,
				'premium' => true,
			),
			array(
				'label'   => __( 'History of Google\'s answers, Help & FAQ', 'fast-index' ),
				'free'    => true,
				'premium' => true,
			),
			array(
				'label'   => __( 'Sending by hand from the post lists (row action and bulk action)', 'fast-index' ),
				'free'    => __( 'Up to 100 at a time', 'fast-index' ),
				'premium' => __( 'Up to 100 at a time, plus hourly batches of old posts', 'fast-index' ),
			),
			array(
				'label'   => __( 'Old posts in hourly batches, within a daily limit you set', 'fast-index' ),
				'free'    => false,
				'premium' => true,
			),
			array(
				'label'   => __( 'Categories whose posts are never sent', 'fast-index' ),
				'free'    => false,
				'premium' => true,
			),
			array(
				'label'   => __( 'Service account keys', 'fast-index' ),
				'free'    => __( 'One', 'fast-index' ),
				'premium' => __( 'A main key and backup keys; a backup key is used only when Google rejects the main key', 'fast-index' ),
			),
		);
	}

	/**
	 * Text of the "Premium" hint next to a settings section.
	 *
	 * @param string $section keys.
	 * @return string Plain text; empty for an unknown section.
	 */
	public static function hint( $section ) {
		switch ( $section ) {
			case 'keys':
				return __( 'The free version uses one key. The premium plan can also keep backup keys: a backup key is used only when Google rejects the main key (401 or 403), never to get past the daily quota.', 'fast-index' );
			default:
				return '';
		}
	}

	/**
	 * Prints a "Premium" hint: a small badge that opens a short explanation with a link. A plain disclosure
	 * element, not a control of a setting.
	 *
	 * @param string $text Explanation (plain text).
	 * @param string $url  Upgrade page.
	 * @return void
	 */
	public static function render_hint( $text, $url ) {
		if ( '' === (string) $text ) {
			return;
		}

		echo '<details class="fi-premium-hint"><summary><span class="fi-premium-badge">' . esc_html_x( 'Premium', 'Badge next to a setting that the premium plan extends', 'fast-index' ) . '</span></summary>';
		echo '<p>' . esc_html( $text );

		if ( '' !== (string) $url ) {
			echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'See the premium plan', 'fast-index' ) . '</a>';
		}

		echo '</p></details>';
	}

	/**
	 * The History line about published posts that Google has not accepted yet.
	 *
	 * @param int $waiting Real number from the History summary.
	 * @return string Plain text; empty when nothing waits or the offer is not shown.
	 */
	public static function waiting_line( $waiting ) {
		$waiting = (int) $waiting;

		if ( $waiting <= 0 || ! self::is_offered() ) {
			return '';
		}

		return sprintf(
			/* translators: %s: number of published posts. */
			_n(
				'%s published post has not been accepted by Google yet. The premium plan can send old posts in hourly batches, within a daily limit you set.',
				'%s published posts have not been accepted by Google yet. The premium plan can send old posts in hourly batches, within a daily limit you set.',
				$waiting,
				'fast-index'
			),
			number_format_i18n( $waiting )
		);
	}

	/**
	 * Explanation after a new key replaced the only key of the free version.
	 *
	 * @param string $new_email Email of the uploaded key.
	 * @param string $old_email Email of the key it replaced.
	 * @return string
	 */
	public static function replaced_key_message( $new_email, $old_email ) {
		/* translators: 1: email of the new service account key, 2: email of the key it replaced. */
		$message = sprintf( __( 'The key of %1$s was saved and replaced the key of %2$s.', 'fast-index' ), $new_email, $old_email );

		if ( self::is_offered() ) {
			$message .= ' ' . self::hint( 'keys' );
		}

		return $message;
	}

	/**
	 * Adds "Settings" and, while the premium plan is offered, "Upgrade" to the plugin's row on the Plugins screen.
	 * Freemius adds an Upgrade link under the same key; this one replaces it, so there is only one.
	 *
	 * @param string[] $links Action links by key.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$links = is_array( $links ) ? $links : array();
		$added = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=' . Fast_Index_Admin::SLUG ) ) . '">' . esc_html_x( 'Settings', 'Link on the Plugins screen', 'fast-index' ) . '</a>',
		);

		if ( self::is_offered() ) {
			$added['upgrade'] = '<a href="' . esc_url( self::upgrade_url() ) . '" class="fi-upgrade-link">' . esc_html__( 'Upgrade', 'fast-index' ) . '</a>';
		}

		unset( $links['upgrade'] );

		return array_merge( $added, $links );
	}
}
