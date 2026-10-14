<?php
/**
 * Premium settings: post types, edit and trash events, category exclusion and several keys.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Connects the premium options to the fast_index_* hooks of the free version.
 *
 * - Every public post type except media attachments can be chosen (free: Posts).
 * - Edit and Trash events: a published post saved again (URL_UPDATED), trashed or deleted (URL_DELETED).
 * - Posts in an excluded category are never sent.
 * - Several service account keys are kept and used.
 * - Settings rows for the daily limit of old posts and the excluded categories.
 */
class Fast_Index_Premium_Features {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'fast_index_post_type_choices', array( __CLASS__, 'post_type_choices' ) );
		add_filter( 'fast_index_post_events', array( __CLASS__, 'post_events' ) );
		add_filter( 'fast_index_default_settings', array( __CLASS__, 'default_settings' ) );
		add_filter( 'fast_index_use_several_keys', '__return_true' );
		add_filter( 'fast_index_unused_settings', '__return_empty_array' );
		add_filter( 'fast_index_should_notify', array( __CLASS__, 'skip_excluded_categories' ), 10, 4 );
		add_filter( 'fast_index_sanitize_settings', array( __CLASS__, 'sanitize_settings' ), 10, 2 );
		add_filter( 'fast_index_validate_settings', array( __CLASS__, 'validate_settings' ), 10, 2 );
		add_action( 'fast_index_settings_rows', array( __CLASS__, 'render_rows' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_after_insert_post' ), 10, 4 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_before_delete_post' ), 10, 1 );
	}

	/**
	 * Offers every public post type except media attachments.
	 *
	 * @param string[] $types Label by post type name.
	 * @return string[]
	 */
	public static function post_type_choices( $types ) {
		$choices = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $object ) {
			if ( 'attachment' !== $name ) {
				$choices[ $name ] = $object->label;
			}
		}

		return $choices + ( is_array( $types ) ? $types : array() );
	}

	/**
	 * Offers the Edit and Trash events.
	 *
	 * @param string[] $events Label by event name.
	 * @return string[]
	 */
	public static function post_events( $events ) {
		return array_merge(
			is_array( $events ) ? $events : array(),
			array(
				'edit'  => __( 'Edit', 'fast-index' ),
				'trash' => __( 'Trash', 'fast-index' ),
			)
		);
	}

	/**
	 * Keeps the 2.x default of the premium plan for sites that never saved the settings: Publish and Edit.
	 *
	 * @param array $defaults Default settings.
	 * @return array
	 */
	public static function default_settings( $defaults ) {
		$defaults['post_status'] = array(
			'publish' => '1',
			'edit'    => '1',
		);

		return $defaults;
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
	 * @param array $clean Settings about to be saved.
	 * @param array $input Unslashed form values; not sanitized.
	 * @return array
	 */
	public static function sanitize_settings( $clean, $input ) {
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
	 * Handles wp_after_insert_post: a published post saved again (edit) or moved to the trash (trash).
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post after the update.
	 * @param bool         $update      Whether an existing post was updated.
	 * @param WP_Post|null $post_before Post before the update, null for new posts.
	 * @return void
	 */
	public static function on_after_insert_post( $post_id, $post, $update, $post_before ) {
		if ( ! $post instanceof WP_Post || (int) $post_id !== (int) $post->ID || ! $update || ! $post_before instanceof WP_Post || 'publish' !== $post_before->post_status ) {
			return;
		}

		if ( 'publish' === $post->post_status ) {
			Fast_Index_Post_Watcher::notify( $post, 'edit' );
		} elseif ( 'trash' === $post->post_status ) {
			// The trashed post has a "__trashed" slug now; Google must hear about the address it had.
			Fast_Index_Post_Watcher::notify( $post_before, 'trash' );
		}
	}

	/**
	 * Handles before_delete_post: a published post deleted without the trash. Posts deleted from the trash were
	 * reported when they were trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_before_delete_post( $post_id ) {
		$post = get_post( $post_id );

		if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
			Fast_Index_Post_Watcher::notify( $post, 'trash' );
		}
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
