<?php
/**
 * Plugin settings: defaults, normalization and form sanitization.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and cleans the fast_index_options option.
 *
 * The stored shape stays compatible with version 2.x: flag maps keyed by post
 * type or post event, and a term ID map for the excluded categories.
 */
class Fast_Index_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'fast_index_options';

	/**
	 * Settings group passed to register_setting().
	 */
	const GROUP = 'fast_index';

	/**
	 * Status value: the plugin sends notifications.
	 */
	const STATUS_ACTIVE = '1';

	/**
	 * Status value: the plugin sends nothing.
	 */
	const STATUS_PASSIVE = '2';

	/**
	 * Highest accepted daily number of old posts.
	 */
	const MAX_OLD_POSTS = 10000;

	/**
	 * Longest post type name WordPress accepts.
	 */
	const MAX_POST_TYPE_LENGTH = 20;

	/**
	 * Post events that can trigger a notification.
	 *
	 * @return string[]
	 */
	public static function post_events() {
		return array( 'publish', 'edit', 'trash' );
	}

	/**
	 * Default values, defined in one place.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'status'           => self::STATUS_ACTIVE,
			'post_type'        => array( 'post' => '1' ),
			'post_status'      => array(
				'publish' => '1',
				'edit'    => '1',
			),
			'old_post_number'  => 0,
			'exclude_category' => array(),
			'delete_data'      => false,
		);
	}

	/**
	 * Registers the option so every update_option() call runs the sanitizer.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_option' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize callback of the registered setting.
	 *
	 * @param mixed $value Value about to be saved.
	 * @return array
	 */
	public static function sanitize_option( $value ) {
		return self::normalize( is_array( $value ) ? $value : array() );
	}

	/**
	 * Returns the current settings with every key present and clean.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );

		return self::normalize( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Saves clean settings.
	 *
	 * @param array $settings Settings returned by sanitize_input() or normalize().
	 * @return void
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION, self::normalize( $settings ) );
	}

	/**
	 * Brings stored or submitted values into the known shape. Unknown keys are dropped.
	 *
	 * Keys of the flag maps must already be in sanitize_key() form; a key that
	 * sanitize_key() would change is rejected, not rewritten.
	 *
	 * @param array $raw Raw values.
	 * @return array
	 */
	public static function normalize( array $raw ) {
		$defaults = self::defaults();
		$status   = isset( $raw['status'] ) && is_scalar( $raw['status'] ) ? (string) $raw['status'] : self::STATUS_ACTIVE;

		return array(
			'status'           => in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_PASSIVE ), true ) ? $status : self::STATUS_ACTIVE,
			'post_type'        => self::flag_map( $raw, 'post_type', $defaults['post_type'], array() ),
			'post_status'      => self::flag_map( $raw, 'post_status', $defaults['post_status'], self::post_events() ),
			'old_post_number'  => isset( $raw['old_post_number'] ) && is_scalar( $raw['old_post_number'] ) ? min( self::MAX_OLD_POSTS, absint( $raw['old_post_number'] ) ) : 0,
			'exclude_category' => isset( $raw['exclude_category'] ) && is_array( $raw['exclude_category'] ) ? self::id_map( $raw['exclude_category'] ) : array(),
			'delete_data'      => ! empty( $raw['delete_data'] ),
		);
	}

	/**
	 * Cleans the settings form. Post types and categories are checked against what exists.
	 *
	 * @param mixed $input           Unslashed $_POST['fast_index_options'].
	 * @param bool  $can_use_premium Whether the premium plan is active.
	 * @return array
	 */
	public static function sanitize_input( $input, $can_use_premium ) {
		$input = is_array( $input ) ? $input : array();

		// Unchecked checkbox groups are missing from the request: they mean "nothing selected".
		$clean = self::normalize(
			array(
				'status'           => isset( $input['status'] ) ? $input['status'] : self::STATUS_ACTIVE,
				'post_type'        => isset( $input['post_type'] ) && is_array( $input['post_type'] ) ? $input['post_type'] : array(),
				'post_status'      => isset( $input['post_status'] ) && is_array( $input['post_status'] ) ? $input['post_status'] : array(),
				'old_post_number'  => isset( $input['old_post_number'] ) ? $input['old_post_number'] : 0,
				'exclude_category' => isset( $input['exclude_category'] ) && is_array( $input['exclude_category'] ) ? $input['exclude_category'] : array(),
				'delete_data'      => ! empty( $input['delete_data'] ),
			)
		);

		$clean['post_type']        = array_intersect_key( $clean['post_type'], self::public_post_types() );
		$clean['exclude_category'] = self::existing_categories( $clean['exclude_category'] );

		return self::apply_plan_limits( $clean, $can_use_premium );
	}

	/**
	 * Finds form values that must be rejected instead of corrected.
	 *
	 * @param mixed $input Unslashed $_POST['fast_index_options'].
	 * @return string[] Error messages; empty when the input is valid.
	 */
	public static function validate_input( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$errors = array();
		$status = isset( $input['status'] ) && is_scalar( $input['status'] ) ? (string) $input['status'] : '';

		if ( ! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_PASSIVE ), true ) ) {
			$errors[] = __( 'Choose a valid status.', 'fast-index' );
		}

		if ( empty( $input['post_type'] ) || ! is_array( $input['post_type'] ) ) {
			$errors[] = __( 'Select at least one post type.', 'fast-index' );
		}

		if ( isset( $input['old_post_number'] ) && ! self::is_count( $input['old_post_number'] ) ) {
			/* translators: %s: highest accepted number. */
			$errors[] = sprintf( __( 'The daily number of old posts must be a whole number from 0 to %s.', 'fast-index' ), number_format_i18n( self::MAX_OLD_POSTS ) );
		}

		return $errors;
	}

	/**
	 * Whether a form value is a whole number within the accepted range.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_count( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return '' === $value || ( ctype_digit( $value ) && (int) $value <= self::MAX_OLD_POSTS );
	}

	/**
	 * Applies the limits of the free plan (kept from 2.x; see audit finding FI-005).
	 *
	 * @param array $settings        Clean settings.
	 * @param bool  $can_use_premium Whether the premium plan is active.
	 * @return array
	 */
	public static function apply_plan_limits( array $settings, $can_use_premium ) {
		if ( $can_use_premium ) {
			return $settings;
		}

		$settings['old_post_number']  = 0;
		$settings['exclude_category'] = array();
		$settings['post_type']        = array_intersect_key( $settings['post_type'], array( 'post' => true ) );
		$settings['post_status']      = array_intersect_key( $settings['post_status'], array( 'publish' => true ) );

		return $settings;
	}

	/**
	 * Whether notifications are switched on.
	 *
	 * @param array $settings Clean settings.
	 * @return bool
	 */
	public static function is_active( array $settings ) {
		return self::STATUS_ACTIVE === $settings['status'];
	}

	/**
	 * Enabled post types that exist, are viewable and are not the plugin's log type.
	 *
	 * @param array $settings Clean settings.
	 * @return string[]
	 */
	public static function enabled_post_types( array $settings ) {
		$types = array();

		foreach ( array_keys( $settings['post_type'] ) as $type ) {
			$type = (string) $type;

			if ( Fast_Index_Log::POST_TYPE !== $type && post_type_exists( $type ) && is_post_type_viewable( $type ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	/**
	 * Excluded category term IDs.
	 *
	 * @param array $settings Clean settings.
	 * @return int[]
	 */
	public static function excluded_category_ids( array $settings ) {
		return array_map( 'absint', array_keys( $settings['exclude_category'] ) );
	}

	/**
	 * Public post types the user can choose from (attachments excluded).
	 *
	 * @return string[] Labels keyed by post type name.
	 */
	public static function public_post_types() {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $object ) {
			if ( 'attachment' !== $name ) {
				$types[ $name ] = $object->label;
			}
		}

		return $types;
	}

	/**
	 * Reads one flag map ( key => '1' ) from raw values.
	 *
	 * @param array    $raw      Raw values.
	 * @param string   $key      Map name.
	 * @param array    $fallback Value used when the map is missing or not an array.
	 * @param string[] $allowed  Allowed keys; empty means any well-formed key.
	 * @return array
	 */
	private static function flag_map( array $raw, $key, array $fallback, array $allowed ) {
		if ( ! isset( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
			return $fallback;
		}

		$map = array();

		foreach ( $raw[ $key ] as $name => $flag ) {
			$name = (string) $name;

			if ( ! self::is_clean_key( $name ) || ( array() !== $allowed && ! in_array( $name, $allowed, true ) ) ) {
				continue;
			}

			if ( is_scalar( $flag ) && '1' === (string) $flag ) {
				$map[ $name ] = '1';
			}
		}

		return $map;
	}

	/**
	 * Whether a map key is a well-formed identifier.
	 *
	 * @param string $name Key.
	 * @return bool
	 */
	private static function is_clean_key( $name ) {
		return '' !== $name && strlen( $name ) <= self::MAX_POST_TYPE_LENGTH && sanitize_key( $name ) === $name;
	}

	/**
	 * Reads a term ID map ( id => id ). Keys that are not positive integers are dropped.
	 *
	 * @param array $raw Raw map.
	 * @return int[]
	 */
	private static function id_map( array $raw ) {
		$map = array();

		foreach ( array_keys( $raw ) as $id ) {
			if ( is_int( $id ) && $id > 0 ) {
				$map[ $id ] = $id;
			}
		}

		return $map;
	}

	/**
	 * Keeps only IDs of existing categories.
	 *
	 * @param int[] $ids Term ID map.
	 * @return int[]
	 */
	private static function existing_categories( array $ids ) {
		if ( array() === $ids ) {
			return array();
		}

		$found = get_terms(
			array(
				'taxonomy'   => 'category',
				'include'    => array_values( $ids ),
				'fields'     => 'ids',
				'hide_empty' => false,
			)
		);

		if ( ! is_array( $found ) ) {
			return array();
		}

		$map = array();

		foreach ( $found as $id ) {
			$map[ (int) $id ] = (int) $id;
		}

		return $map;
	}
}
