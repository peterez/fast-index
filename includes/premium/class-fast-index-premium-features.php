<?php
/**
 * Premium settings: category exclusion and backup keys.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Connects the premium options to the fast_index_* hooks of the free version.
 *
 * - Posts in an excluded category are never sent.
 * - Backup service account keys are kept: the main key is always tried first, a backup key only after Google
 *   rejected the key before it (401 or 403), never after a 429 (Fast_Index_Submitter).
 * - Settings rows for the daily limit of old posts and the excluded categories.
 *
 * Post types and the Edit and Trash events are part of the free version.
 */
class Fast_Index_Premium_Features {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'fast_index_use_several_keys', '__return_true' );
		add_filter( 'fast_index_unused_settings', '__return_empty_array' );
		add_filter( 'fast_index_should_notify', array( __CLASS__, 'skip_excluded_categories' ), 10, 4 );
		add_filter( 'fast_index_sanitize_settings', array( __CLASS__, 'sanitize_settings' ), 10, 3 );
		add_filter( 'fast_index_validate_settings', array( __CLASS__, 'validate_settings' ), 10, 2 );
		add_action( 'fast_index_settings_rows', array( __CLASS__, 'render_rows' ) );
	}

	/**
	 * Leaves out posts that are in an excluded category.
	 *
	 * @param bool    $notify   Whether the notification is queued.
	 * @param WP_Post $post     Post.
	 * @param string  $event    Event name.
	 * @param array   $settings Clean settings.
	 * @return bool
	 */
	public static function skip_excluded_categories( $notify, $post, $event, $settings ) {
		$excluded = isset( $settings['exclude_category'] ) ? array_map( 'absint', array_keys( (array) $settings['exclude_category'] ) ) : array();

		if ( ! $notify || array() === $excluded || ! $post instanceof WP_Post ) {
			return (bool) $notify;
		}

		$categories = array_map( 'absint', wp_get_post_categories( $post->ID ) );

		return array() === array_intersect( $categories, $excluded );
	}

	/**
	 * Reads the premium fields of the settings form.
	 *
	 * @param array $clean  Settings about to be saved.
	 * @param array $input  Unslashed form values; not sanitized.
	 * @param array $stored Settings stored before this save.
	 * @return array
	 */
	public static function sanitize_settings( $clean, $input, $stored = array() ) {
		$input = is_array( $input ) ? $input : array();
		$ids   = array();

		if ( isset( $input['exclude_category'] ) && is_array( $input['exclude_category'] ) ) {
			foreach ( array_keys( $input['exclude_category'] ) as $id ) {
				if ( is_int( $id ) && $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		$clean['old_post_number']  = isset( $input['old_post_number'] ) && is_scalar( $input['old_post_number'] ) ? min( Fast_Index_Settings::MAX_OLD_POSTS, absint( $input['old_post_number'] ) ) : 0;
		$clean['exclude_category'] = self::existing_categories( $ids );
		$clean['sc_property']      = Fast_Index_Settings::search_console_property( isset( $input['sc_property'] ) ? $input['sc_property'] : '' );

		if ( isset( $clean['sc_property'], $stored['sc_property'] ) && $clean['sc_property'] !== $stored['sc_property'] ) {
			Fast_Index_Premium_Inspection::forget_property();
		}

		return $clean;
	}

	/**
	 * Rejects a daily limit that is not a whole number in range.
	 *
	 * @param string[] $errors Error messages.
	 * @param array    $input  Unslashed form values; not sanitized.
	 * @return string[]
	 */
	public static function validate_settings( $errors, $input ) {
		$errors = is_array( $errors ) ? $errors : array();
		$value  = is_array( $input ) && isset( $input['old_post_number'] ) ? $input['old_post_number'] : '';
		$value  = is_scalar( $value ) ? trim( (string) $value ) : 'x';

		if ( '' !== $value && ( ! ctype_digit( $value ) || (int) $value > Fast_Index_Settings::MAX_OLD_POSTS ) ) {
			/* translators: %s: highest accepted number. */
			$errors[] = sprintf( __( 'The daily limit for old posts must be a whole number from 0 to %s.', 'fast-index' ), number_format_i18n( Fast_Index_Settings::MAX_OLD_POSTS ) );
		}

		$property = is_array( $input ) && isset( $input['sc_property'] ) && is_scalar( $input['sc_property'] ) ? trim( (string) $input['sc_property'] ) : '';

		if ( '' !== $property && '' === Fast_Index_Settings::search_console_property( $property ) ) {
			$errors[] = __( 'Enter a Search Console property such as sc-domain:example.com or https://www.example.com/, or leave the field empty.', 'fast-index' );
		}

		return $errors;
	}

	/**
	 * Prints the settings rows of the daily limit for old posts and of the excluded categories.
	 *
	 * @param array $settings Clean settings.
	 * @return void
	 */
	public static function render_rows( $settings ) {
		$fast_index_settings   = $settings;
		$fast_index_categories = get_categories( array( 'hide_empty' => false ) );

		include __DIR__ . '/view-settings-rows.php';
	}

	/**
	 * Keeps only IDs of existing categories.
	 *
	 * @param int[] $ids Term IDs.
	 * @return int[] Term ID map ( id => id ).
	 */
	private static function existing_categories( array $ids ) {
		if ( array() === $ids ) {
			return array();
		}

		$found = get_terms(
			array(
				'taxonomy'   => 'category',
				'include'    => $ids,
				'fields'     => 'ids',
				'hide_empty' => false,
			)
		);
		$map   = array();

		foreach ( is_array( $found ) ? $found : array() as $id ) {
			$map[ (int) $id ] = (int) $id;
		}

		return $map;
	}
}
