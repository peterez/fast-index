<?php
/**
 * Reads uploaded service account key files without saving them.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns an uploaded key file into account fields.
 *
 * The file is read from PHP's temporary upload location and is never moved or copied:
 * PHP removes it at the end of the request. Only the validated fields are stored.
 */
class Fast_Index_Key_Upload {

	/**
	 * Normalizes one $_FILES field ( name="fast_index_key_files[]" ) into a list of files.
	 *
	 * Empty file inputs are skipped.
	 *
	 * @param array $field The $_FILES entry.
	 * @return array[] Each with name, tmp_name, error and size.
	 */
	public static function files_from_field( array $field ) {
		if ( ! isset( $field['tmp_name'], $field['error'] ) ) {
			return array();
		}

		$names  = isset( $field['name'] ) ? (array) $field['name'] : array();
		$sizes  = isset( $field['size'] ) ? (array) $field['size'] : array();
		$errors = (array) $field['error'];
		$files  = array();

		foreach ( (array) $field['tmp_name'] as $index => $tmp_name ) {
			$error = isset( $errors[ $index ] ) && is_numeric( $errors[ $index ] ) ? (int) $errors[ $index ] : UPLOAD_ERR_NO_FILE;

			if ( UPLOAD_ERR_NO_FILE === $error || ! is_string( $tmp_name ) ) {
				continue;
			}

			$files[] = array(
				'name'     => isset( $names[ $index ] ) && is_string( $names[ $index ] ) ? sanitize_file_name( $names[ $index ] ) : '',
				'tmp_name' => $tmp_name,
				'error'    => $error,
				'size'     => isset( $sizes[ $index ] ) && is_numeric( $sizes[ $index ] ) ? (int) $sizes[ $index ] : 0,
			);
		}

		return $files;
	}

	/**
	 * Validates one uploaded file and returns the account fields.
	 *
	 * @param array $file One entry of files_from_field().
	 * @return array|WP_Error
	 */
	public static function account_from_file( array $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'fast_index_upload_failed', __( 'The file could not be uploaded.', 'fast-index' ) );
		}

		if ( 'json' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'fast_index_key_extension', __( 'Choose a .json key file.', 'fast-index' ) );
		}

		if ( $file['size'] > Fast_Index_Accounts::MAX_KEY_BYTES ) {
			return new WP_Error( 'fast_index_key_size', __( 'The file is empty or too large to be a service account key.', 'fast-index' ) );
		}

		$json = Fast_Index_Files::read( $file['tmp_name'], Fast_Index_Accounts::MAX_KEY_BYTES );

		if ( false === $json ) {
			return new WP_Error( 'fast_index_upload_failed', __( 'The file could not be uploaded.', 'fast-index' ) );
		}

		return Fast_Index_Accounts::parse_key_json( $json );
	}
}
