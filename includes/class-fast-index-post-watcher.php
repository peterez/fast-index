<?php
/**
 * Watches post changes and queues notifications.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns post changes into queued notifications.
 *
 * Nothing is sent while the post is saved: the URL goes into a single WP-Cron event,
 * so saving a post never waits for Google.
 *
 * Events: publish (a post becomes published, URL_UPDATED), edit (a published post is saved again, URL_UPDATED)
 * and trash (a published post is moved to the trash or deleted without the trash, URL_DELETED with the address
 * it had). A post deleted from the trash was reported when it was trashed.
 */
class Fast_Index_Post_Watcher {

	/**
	 * Registers the post hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_after_insert_post', array( $this, 'on_after_insert_post' ), 10, 4 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete_post' ), 10, 1 );
	}

	/**
	 * Handles wp_after_insert_post (WordPress 5.6+), which runs after terms and meta are saved.
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post after the update.
	 * @param bool         $update      Whether an existing post was updated.
	 * @param WP_Post|null $post_before Post before the update, null for new posts.
	 * @return void
	 */
	public function on_after_insert_post( $post_id, $post, $update, $post_before ) {
		if ( ! $post instanceof WP_Post || (int) $post_id !== (int) $post->ID ) {
			return;
		}

		$before = $update && $post_before instanceof WP_Post ? $post_before->post_status : '';

		if ( 'publish' === $post->post_status && 'publish' !== $before ) {
			self::notify( $post, 'publish' );
		} elseif ( 'publish' === $before && 'publish' === $post->post_status ) {
			self::notify( $post, 'edit' );
		} elseif ( 'publish' === $before && 'trash' === $post->post_status ) {
			// The trashed post has a "__trashed" slug now; Google must hear about the address it had.
			self::notify( $post_before, 'trash' );
		}
	}

	/**
	 * Handles before_delete_post: a published post deleted without the trash. Posts deleted from the trash were
	 * reported when they were trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_before_delete_post( $post_id ) {
		$post = get_post( $post_id );

		if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
			self::notify( $post, 'trash' );
		}
	}

	/**
	 * Whether the settings allow a notification for this post and event.
	 *
	 * @param array   $settings Clean settings.
	 * @param WP_Post $post     Post.
	 * @param string  $event    Event name.
	 * @return bool
	 */
	public static function should_notify( array $settings, WP_Post $post, $event ) {
		if ( ! Fast_Index_Settings::is_active( $settings ) || ! in_array( $event, Fast_Index_Settings::enabled_events( $settings ), true ) ) {
			return false;
		}

		if ( ! in_array( $post->post_type, Fast_Index_Settings::enabled_post_types( $settings ), true ) ) {
			return false;
		}

		/**
		 * Filters whether a post event is sent to Google.
		 *
		 * @param bool    $notify   Whether the notification is queued.
		 * @param WP_Post $post     Post.
		 * @param string  $event    Event name: publish, edit or trash.
		 * @param array   $settings Clean settings.
		 */
		return (bool) apply_filters( 'fast_index_should_notify', true, $post, $event, $settings );
	}

	/**
	 * Queues a notification when the settings allow it.
	 *
	 * @param WP_Post $post  Post whose permalink is sent.
	 * @param string  $event Event name: publish, edit or trash. Trash sends URL_DELETED.
	 * @return bool Whether a notification was queued.
	 */
	public static function notify( WP_Post $post, $event ) {
		if ( ! self::should_notify( Fast_Index_Settings::get(), $post, $event ) ) {
			return false;
		}

		$url = get_permalink( $post );

		if ( ! self::is_http_url( $url ) ) {
			return false;
		}

		$type = 'trash' === $event ? Fast_Index_Google_Client::TYPE_DELETED : Fast_Index_Google_Client::TYPE_UPDATED;

		Fast_Index_Cron::queue_submission( $url, $type, (int) $post->ID );

		return true;
	}

	/**
	 * Whether a value is an absolute http or https URL.
	 *
	 * @param mixed $url Value to check.
	 * @return bool
	 */
	public static function is_http_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		return in_array( $scheme, array( 'http', 'https' ), true ) && is_string( $host ) && '' !== $host;
	}
}
