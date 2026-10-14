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
 *
 * The free version offers Posts and the Publish event. The premium module adds more choices and its own
 * settings through the fast_index_* filters below. Stored values that the running code does not offer are
 * kept as they are: they are not used, and they are not lost when the settings are saved.
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
	 * Every post event the stored settings can hold (the shape of the option, not what is offered).
	 *
	 * @return string[]
	 */
	public static function post_events() {
		return array( 'publish', 'edit', 'trash' );
	}

	/**
	 * Post events offered on the settings screen, with their labels.
	 *
	 * @return string[] Label by event name.
	 */
	public static function event_choices() {
		/**
		 * Filters the post events offered on the settings screen.
		 *
		 * @param string[] $events Label by event name: publish, edit or trash.
		 */
		$events = apply_filters( 'fast_index_post_events', array( 'publish' => __( 'Publish', 'fast-index' ) ) );

		return array_intersect_key( is_array( $events ) ? $events : array(), array_flip( self::post_events() ) );
	}

	/**
	 * Post types offered on the settings screen, with their labels.
	 *
	 * @return string[] Label by post type name.
	 */
	public static function post_type_choices() {
		$post = get_post_type_object( 'post' );

		/**
		 * Filters the post types offered on the settings screen.
		 *
		 * @param string[] $types Label by post type name.
		 */
		$types   = apply_filters( 'fast_index_post_type_choices', array( 'post' => $post ? $post->label : 'Posts' ) );
		$choices = array();

		foreach ( is_array( $types ) ? $types : array() as $name => $label ) {
			$name = (string) $name;

			if ( self::is_clean_key( $name ) && 'attachment' !== $name && Fast_Index_Log::POST_TYPE !== $name && post_type_exists( $name ) ) {
				$choices[ $name ] = (string) $label;
			}
		}

		return $choices;
	}

	/**
	 * Default values, defined in one place. The free version notifies Google when a post is published.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array(
			'status'           => self::STATUS_ACTIVE,
			'post_type'        => array( 'post' => '1' ),
			'post_status'      => array( 'publish' => '1' ),
			'old_post_number'  => 0,
			'exclude_category' => array(),
			'delete_data'      => false,
		);

		/**
		 * Filters the default settings, used while the settings were never saved.
		 *
		 * @param array $defaults Default settings.
		 */
		$filtered = apply_filters( 'fast_index_default_settings', $defaults );

		return is_array( $filtered ) ? array_merge( $defaults, array_intersect_key( $filtered, $defaults ) ) : $defaults;
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
	 * Cleans the settings form. The form changes only what it shows: offered post types and events are set from
	 * the checkboxes, and every stored value the form does not show (a premium setting while the premium module
	 * is off, for example) is kept.
	 *
	 * @param mixed $input Unslashed $_POST['fast_index_options'].
	 * @return array
	 */
	public static function sanitize_input( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$stored = self::get();
		$clean  = $stored;

		$clean['status']      = isset( $input['status'] ) && is_scalar( $input['status'] ) ? (string) $input['status'] : self::STATUS_ACTIVE;
		$clean['delete_data'] = ! empty( $input['delete_data'] );
		$clean['post_type']   = self::merge_choices( $stored['post_type'], $input, 'post_type', array_keys( self::post_type_choices() ) );
		$clean['post_status'] = self::merge_choices( $stored['post_status'], $input, 'post_status', array_keys( self::event_choices() ) );

		/**
		 * Filters the settings about to be saved from the settings form.
		 *
		 * @param array $clean  Settings: the stored ones with the fields of the free version applied.
		 * @param array $input  Unslashed form values; not sanitized.
		 * @param array $stored Settings stored before this save.
		 */
		$clean = apply_filters( 'fast_index_sanitize_settings', $clean, $input, $stored );

		return self::normalize( is_array( $clean ) ? $clean : $stored );
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

		$types   = isset( $input['post_type'] ) && is_array( $input['post_type'] ) ? $input['post_type'] : array();
		$checked = array_intersect_key( self::flag_map( array( 'post_type' => $types ), 'post_type', array(), array() ), self::post_type_choices() );

		if ( array() === $checked ) {
			$errors[] = __( 'Select at least one post type.', 'fast-index' );
		}

		/**
		 * Filters the errors that reject the settings form.
		 *
		 * @param string[] $errors Error messages.
		 * @param array    $input  Unslashed form values; not sanitized.
		 */
		$errors = apply_filters( 'fast_index_validate_settings', $errors, $input );

		return array_values( array_filter( array_map( 'strval', is_array( $errors ) ? $errors : array() ) ) );
	}

	/**
	 * Applies the checkboxes of offered choices to a stored flag map. Stored keys that are not offered stay.
	 *
	 * @param array    $stored  Stored flag map.
	 * @param array    $input   Form values.
	 * @param string   $key     Map name.
	 * @param string[] $offered Keys shown on the form.
	 * @return array
	 */
	private static function merge_choices( array $stored, array $input, $key, array $offered ) {
		$checked = self::flag_map( $input, $key, array(), array() );

		foreach ( $offered as $name ) {
			if ( isset( $checked[ $name ] ) ) {
				$stored[ $name ] = '1';
			} else {
				unset( $stored[ $name ] );
			}
		}

		return $stored;
	}

	/**
	 * Stored values that the running code does not use, for a short note on the settings screen. They are kept,
	 * and the premium module uses them again.
	 *
	 * @param array $settings Clean settings.
	 * @return string[] Plain text, one line each; empty when everything stored is used.
	 */
	public static function unused( array $settings ) {
		$lines  = array();
		$types  = array();
		$events = array_diff( array_keys( $settings['post_status'] ), array_keys( self::event_choices() ) );
		$keys   = count( Fast_Index_Accounts::all() ) - count( Fast_Index_Accounts::in_use() );

		foreach ( array_diff( array_keys( $settings['post_type'] ), array_keys( self::post_type_choices() ) ) as $type ) {
			$object = get_post_type_object( (string) $type );

			if ( $object ) {
				$types[] = $object->label;
			}
		}

		if ( array() !== $types ) {
			/* translators: %s: list of post type names. */
			$lines[] = sprintf( __( 'Post types: %s', 'fast-index' ), wp_sprintf( '%l', $types ) );
		}

		if ( array() !== $events ) {
			$labels = array(
				'edit'  => __( 'Edit', 'fast-index' ),
				'trash' => __( 'Trash', 'fast-index' ),
			);
			$names  = array_intersect_key( $labels, array_flip( $events ) );
			/* translators: %s: list of post event names, such as Edit and Trash. */
			$lines[] = sprintf( __( 'Post events: %s', 'fast-index' ), wp_sprintf( '%l', array_values( $names ) ) );
		}

		if ( $settings['old_post_number'] > 0 ) {
			/* translators: %s: number of old posts per day. */
			$lines[] = sprintf( __( 'Daily limit for old posts: %s', 'fast-index' ), number_format_i18n( $settings['old_post_number'] ) );
		}

		if ( array() !== $settings['exclude_category'] ) {
			/* translators: %s: number of categories. */
			$lines[] = sprintf( __( 'Excluded categories: %s', 'fast-index' ), number_format_i18n( count( $settings['exclude_category'] ) ) );
		}

		if ( $keys > 0 ) {
			/* translators: %s: number of keys. */
			$lines[] = sprintf( __( 'Service account keys after the first one: %s', 'fast-index' ), number_format_i18n( $keys ) );
		}

		/**
		 * Filters the list of stored values that are kept but not used. The premium module uses them all.
		 *
		 * @param string[] $lines    Plain text lines.
		 * @param array    $settings Clean settings.
		 */
		$lines = apply_filters( 'fast_index_unused_settings', $lines, $settings );

		return is_array( $lines ) ? array_values( array_map( 'strval', $lines ) ) : array();
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
	 * Checked post types that are offered, exist and are viewable.
	 *
	 * @param array $settings Clean settings.
	 * @return string[]
	 */
	public static function enabled_post_types( array $settings ) {
		$types = array();

		foreach ( array_keys( array_intersect_key( $settings['post_type'], self::post_type_choices() ) ) as $type ) {
			$type = (string) $type;

			if ( is_post_type_viewable( $type ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	/**
	 * Checked post events that are offered.
	 *
	 * @param array $settings Clean settings.
	 * @return string[]
	 */
	public static function enabled_events( array $settings ) {
		return array_map( 'strval', array_keys( array_intersect_key( $settings['post_status'], self::event_choices() ) ) );
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
}
