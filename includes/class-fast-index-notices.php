<?php
/**
 * Admin notices about the key migration.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows the notices raised by the 3.0.0 key migration on the dashboard and the plugin pages.
 *
 * Both notices are shown to users who can manage options, can be dismissed, and never
 * come back once dismissed. The issue list also shrinks by itself when a file that could
 * not be deleted is removed by hand.
 */
class Fast_Index_Notices {

	/**
	 * Notice: recommend rotating keys that were publicly stored by 2.x.
	 */
	const KEY_ROTATION = 'key_rotation';

	/**
	 * Notice: some keys could not be migrated.
	 */
	const ISSUES = 'migration_issues';

	/**
	 * Option flagging the key rotation notice (autoload off).
	 */
	const KEY_ROTATION_OPTION = 'fast_index_notice_key_rotation';

	/**
	 * Option holding the migration issues (autoload off).
	 */
	const ISSUES_OPTION = 'fast_index_migration_issues';

	/**
	 * Most issues kept.
	 */
	const MAX_ISSUES = 50;

	/**
	 * Registers the notice output.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Notice names accepted by dismiss().
	 *
	 * @return string[]
	 */
	public static function names() {
		return array( self::KEY_ROTATION, self::ISSUES );
	}

	/**
	 * Turns the key rotation notice on.
	 *
	 * @return void
	 */
	public static function flag_key_rotation() {
		update_option( self::KEY_ROTATION_OPTION, '1', false );
	}

	/**
	 * Stores migration issues, without duplicates.
	 *
	 * @param array[] $issues Issues from Fast_Index_Migration.
	 * @return void
	 */
	public static function add_issues( array $issues ) {
		if ( array() === $issues ) {
			return;
		}

		$stored = array();

		foreach ( array_merge( self::issues(), $issues ) as $issue ) {
			$stored[ md5( $issue['code'] . '|' . $issue['email'] . '|' . $issue['file'] ) ] = $issue;
		}

		update_option( self::ISSUES_OPTION, array_slice( array_values( $stored ), 0, self::MAX_ISSUES ), false );
	}

	/**
	 * Stored issues with the expected fields.
	 *
	 * @return array[]
	 */
	public static function issues() {
		$stored = get_option( self::ISSUES_OPTION, array() );
		$issues = array();

		if ( ! is_array( $stored ) ) {
			return $issues;
		}

		foreach ( $stored as $issue ) {
			if ( is_array( $issue ) && isset( $issue['code'], $issue['email'], $issue['file'] ) ) {
				$issues[] = array(
					'code'  => (string) $issue['code'],
					'email' => (string) $issue['email'],
					'file'  => (string) $issue['file'],
				);
			}
		}

		return $issues;
	}

	/**
	 * Issues still worth showing: an undeletable file that is gone now is solved.
	 *
	 * @return array[]
	 */
	public static function open_issues() {
		$open = array();

		foreach ( self::issues() as $issue ) {
			if ( Fast_Index_Migration::ISSUE_DELETE_FAILED === $issue['code'] && ! self::upload_file_exists( $issue['file'] ) ) {
				continue;
			}

			$open[] = $issue;
		}

		return $open;
	}

	/**
	 * Whether any notice is waiting.
	 *
	 * @return bool
	 */
	public static function has_pending() {
		return self::is_key_rotation_pending() || array() !== self::open_issues();
	}

	/**
	 * Hides a notice for good.
	 *
	 * @param string $notice One of names().
	 * @return void
	 */
	public static function dismiss( $notice ) {
		if ( self::KEY_ROTATION === $notice ) {
			delete_option( self::KEY_ROTATION_OPTION );
		} elseif ( self::ISSUES === $notice ) {
			delete_option( self::ISSUES_OPTION );
		}
	}

	/**
	 * Nonce-protected URL that dismisses a notice.
	 *
	 * @param string $notice One of names().
	 * @return string
	 */
	public static function dismiss_url( $notice ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => Fast_Index_Admin_Actions::DISMISS,
					'notice' => $notice,
				),
				admin_url( 'admin-post.php' )
			),
			Fast_Index_Admin_Actions::DISMISS
		);
	}

	/**
	 * Prints the notices on the dashboard and the plugin pages.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_notice_screen() ) {
			return;
		}

		$this->render_issues( self::open_issues() );
		$this->render_key_rotation();
	}

	/**
	 * Whether the current screen shows the notices.
	 *
	 * @return bool
	 */
	private static function is_notice_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen instanceof WP_Screen && ( 'dashboard' === $screen->id || Fast_Index_Admin::is_plugin_screen( $screen->id ) );
	}

	/**
	 * Whether the key rotation notice is on.
	 *
	 * @return bool
	 */
	private static function is_key_rotation_pending() {
		return '1' === (string) get_option( self::KEY_ROTATION_OPTION, '' );
	}

	/**
	 * Prints the key rotation notice.
	 *
	 * @return void
	 */
	private function render_key_rotation() {
		if ( ! self::is_key_rotation_pending() ) {
			return;
		}

		$this->open_notice( 'warning', self::KEY_ROTATION );
		echo '<p><strong>' . esc_html__( 'Fast Index:', 'fast-index' ) . '</strong> ';
		echo esc_html__( 'In earlier versions, service account key files were stored in a publicly accessible folder; for security, we recommend creating a new key in Google Cloud and revoking the old one.', 'fast-index' ) . '</p>';
		$this->close_notice( self::KEY_ROTATION );
	}

	/**
	 * Prints the migration issue notice.
	 *
	 * @param array[] $issues Open issues.
	 * @return void
	 */
	private function render_issues( array $issues ) {
		if ( array() === $issues ) {
			return;
		}

		$this->open_notice( 'error', self::ISSUES );
		echo '<p><strong>' . esc_html__( 'Fast Index could not finish moving your service account keys to the database.', 'fast-index' ) . '</strong></p><ul>';

		foreach ( $issues as $issue ) {
			echo '<li>' . esc_html( self::issue_message( $issue ) ) . '</li>';
		}

		echo '</ul>';
		$this->close_notice( self::ISSUES );
	}

	/**
	 * Prints the opening tag of a dismissible notice.
	 *
	 * @param string $type   warning or error.
	 * @param string $notice Notice name.
	 * @return void
	 */
	private function open_notice( $type, $notice ) {
		echo '<div class="' . esc_attr( 'notice notice-' . $type . ' is-dismissible fast-index-notice' ) . '" data-dismiss-url="' . esc_url( self::dismiss_url( $notice ) ) . '">';
	}

	/**
	 * Prints the no-JavaScript dismiss link and the closing tag.
	 *
	 * @param string $notice Notice name.
	 * @return void
	 */
	private function close_notice( $notice ) {
		echo '<p><a href="' . esc_url( self::dismiss_url( $notice ) ) . '">' . esc_html__( 'Do not show this again', 'fast-index' ) . '</a></p></div>';
	}

	/**
	 * Text of one issue.
	 *
	 * @param array $issue Issue ( code, email, file ).
	 * @return string
	 */
	private static function issue_message( array $issue ) {
		$email = '' !== $issue['email'] ? $issue['email'] : __( 'an unknown account', 'fast-index' );

		switch ( $issue['code'] ) {
			case Fast_Index_Migration::ISSUE_MISSING:
				/* translators: %s: service account email. */
				return sprintf( __( 'The key file of %s was not found. Upload the key again.', 'fast-index' ), $email );
			case Fast_Index_Migration::ISSUE_UNREADABLE:
				/* translators: 1: service account email, 2: file path inside the uploads folder. */
				return sprintf( __( 'The key file of %1$s (uploads/%2$s) could not be read. Upload the key again, then delete the old file.', 'fast-index' ), $email, $issue['file'] );
			case Fast_Index_Migration::ISSUE_INVALID:
				/* translators: 1: service account email, 2: file path inside the uploads folder. */
				return sprintf( __( 'The key file of %1$s (uploads/%2$s) is not a valid service account key. Upload the key again, then delete the old file.', 'fast-index' ), $email, $issue['file'] );
			case Fast_Index_Migration::ISSUE_DELETE_FAILED:
				/* translators: 1: service account email, 2: file path inside the uploads folder. */
				return sprintf( __( 'The key of %1$s was moved, but the old file uploads/%2$s could not be deleted. Delete this file now: it can be downloaded by anyone.', 'fast-index' ), $email, $issue['file'] );
			default:
				/* translators: 1: service account email, 2: file name. */
				return sprintf( __( 'The key file of %1$s (%2$s) is not inside the uploads folder and was left untouched. Upload the key again, then delete the old file.', 'fast-index' ), $email, $issue['file'] );
		}
	}

	/**
	 * Whether a file inside the uploads folder exists.
	 *
	 * @param string $relative Path relative to the uploads folder.
	 * @return bool
	 */
	private static function upload_file_exists( $relative ) {
		$uploads = wp_upload_dir( null, false );

		return ! empty( $uploads['basedir'] ) && 0 === validate_file( $relative ) && file_exists( trailingslashit( $uploads['basedir'] ) . $relative );
	}
}
