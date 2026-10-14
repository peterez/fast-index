<?php
/**
 * Form handlers served by admin-post.php.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every handler checks, in this order: capability, nonce, input. Then it does the work
 * and redirects (post/redirect/get).
 */
class Fast_Index_Admin_Actions {

	/**
	 * Action: save the settings form.
	 */
	const SAVE = 'fast_index_save_settings';

	/**
	 * Action: upload service account keys.
	 */
	const UPLOAD = 'fast_index_upload_key';

	/**
	 * Action: delete one service account key.
	 */
	const DELETE = 'fast_index_delete_key';

	/**
	 * Action: send a batch of old posts now.
	 */
	const RUN = 'fast_index_run_backlog';

	/**
	 * Action: dismiss an admin notice.
	 */
	const DISMISS = 'fast_index_dismiss_notice';

	/**
	 * Name of the nonce field in the forms.
	 */
	const NONCE_FIELD = 'fast_index_nonce';

	/**
	 * Registers the admin-post handlers.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_' . self::SAVE, array( $this, 'save_settings' ) );
		add_action( 'admin_post_' . self::UPLOAD, array( $this, 'upload_key' ) );
		add_action( 'admin_post_' . self::DELETE, array( $this, 'delete_key' ) );
		add_action( 'admin_post_' . self::RUN, array( $this, 'run_backlog' ) );
		add_action( 'admin_post_' . self::DISMISS, array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Saves the settings form.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		check_admin_referer( self::SAVE, self::NONCE_FIELD );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every field is allowlisted and sanitized by Fast_Index_Settings::sanitize_input().
		$input  = isset( $_POST['fast_index_options'] ) ? wp_unslash( $_POST['fast_index_options'] ) : array();
		$errors = Fast_Index_Settings::validate_input( $input );

		if ( array() !== $errors ) {
			foreach ( $errors as $error ) {
				Fast_Index_Admin::flash( 'error', $error );
			}

			// Nothing is saved: the stored settings stay as they were.
			self::redirect( Fast_Index_Admin::SLUG );
		}

		Fast_Index_Settings::save( Fast_Index_Settings::sanitize_input( $input, Fast_Index_Plugin::can_use_premium() ) );
		Fast_Index_Log::flush_stats();
		Fast_Index_Admin::flash( 'success', __( 'Settings saved.', 'fast-index' ) );

		self::redirect( Fast_Index_Admin::SLUG );
	}

	/**
	 * Stores uploaded service account keys. The files are read, never saved.
	 *
	 * The free plan keeps one key: a new key replaces the stored one (2.x behaviour).
	 *
	 * @return void
	 */
	public function upload_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		check_admin_referer( self::UPLOAD, self::NONCE_FIELD );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Fast_Index_Key_Upload sanitizes the name, casts size and error, and checks tmp_name with is_uploaded_file().
		$field   = isset( $_FILES['fast_index_key_files'] ) && is_array( $_FILES['fast_index_key_files'] ) ? $_FILES['fast_index_key_files'] : array();
		$files   = Fast_Index_Key_Upload::files_from_field( $field );
		$premium = Fast_Index_Plugin::can_use_premium();

		if ( array() === $files ) {
			Fast_Index_Admin::flash( 'error', __( 'Choose a .json key file.', 'fast-index' ) );
			self::redirect( Fast_Index_Admin::SLUG );
		}

		foreach ( $premium ? $files : array_slice( $files, 0, 1 ) as $file ) {
			self::store_key( $file, $premium );
		}

		self::redirect( Fast_Index_Admin::SLUG );
	}

	/**
	 * Deletes one service account key.
	 *
	 * @return void
	 */
	public function delete_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		check_admin_referer( self::DELETE, self::NONCE_FIELD );

		$id = isset( $_POST['fast_index_account'] ) ? sanitize_key( wp_unslash( $_POST['fast_index_account'] ) ) : '';

		if ( Fast_Index_Accounts::delete( $id ) ) {
			Fast_Index_Admin::flash( 'success', __( 'The service account key was deleted.', 'fast-index' ) );
		} else {
			Fast_Index_Admin::flash( 'error', __( 'The service account key was not found.', 'fast-index' ) );
		}

		self::redirect( Fast_Index_Admin::SLUG );
	}

	/**
	 * Sends one batch of old posts now and shows the results.
	 *
	 * @return void
	 */
	public function run_backlog() {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		check_admin_referer( self::RUN, self::NONCE_FIELD );

		Fast_Index_Admin::store_run_results( Fast_Index_Backlog::run() );

		self::redirect( Fast_Index_Admin::SLUG_RUN );
	}

	/**
	 * Dismisses a notice for good. Reached by a link or by the notice's close button.
	 *
	 * @return void
	 */
	public function dismiss_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::forbidden();
		}

		check_admin_referer( self::DISMISS, '_wpnonce' );

		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

		if ( in_array( $notice, Fast_Index_Notices::names(), true ) ) {
			Fast_Index_Notices::dismiss( $notice );
		}

		$referer = wp_get_referer();

		wp_safe_redirect( $referer ? $referer : admin_url() );
		exit;
	}

	/**
	 * Validates and stores one uploaded key, and queues the message about it.
	 *
	 * @param array $file    One entry of Fast_Index_Key_Upload::files_from_field().
	 * @param bool  $premium Whether several keys may be kept.
	 * @return void
	 */
	private static function store_key( array $file, $premium ) {
		$account = Fast_Index_Key_Upload::account_from_file( $file );

		if ( is_wp_error( $account ) ) {
			/* translators: 1: uploaded file name, 2: reason the file was rejected. */
			Fast_Index_Admin::flash( 'error', sprintf( __( '%1$s: %2$s', 'fast-index' ), $file['name'], $account->get_error_message() ) );
			return;
		}

		if ( $premium ) {
			Fast_Index_Accounts::save( $account );
		} else {
			Fast_Index_Accounts::replace_all( $account );
		}

		// Notifications that waited for a key go out with the next scheduled-task run.
		Fast_Index_Retry::resume();

		/* translators: %s: service account email. */
		Fast_Index_Admin::flash( 'success', sprintf( __( 'The key of %s was saved.', 'fast-index' ), $account['client_email'] ) );
	}

	/**
	 * Stops a request from a user without the capability.
	 *
	 * @return void
	 */
	private static function forbidden() {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fast-index' ), '', array( 'response' => 403 ) );
	}

	/**
	 * Redirects to a plugin page and stops.
	 *
	 * @param string $page Page slug.
	 * @return void
	 */
	private static function redirect( $page ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}
}
