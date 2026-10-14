<?php
/**
 * Atomic named lock stored in the options table.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lock rows are inserted with INSERT IGNORE (the technique of WP_Upgrader::create_lock()),
 * so exactly one request gets the lock. A lock older than its TTL can be taken over.
 * The rows are never read through get_option().
 */
class Fast_Index_Lock {

	/**
	 * Option name prefix of lock rows.
	 */
	const PREFIX = 'fast_index_lock_';

	/**
	 * Tries to take a lock.
	 *
	 * @param string $name Lock name.
	 * @param int    $ttl  Seconds after which the lock counts as stale.
	 * @return string|false Token for release(), false when another request holds the lock.
	 */
	public static function acquire( $name, $ttl ) {
		global $wpdb;

		$option = self::PREFIX . $name;
		$token  = ( time() + (int) $ttl ) . ':' . wp_generate_password( 12, false );

		if ( self::insert( $option, $token ) ) {
			return $token;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock rows must bypass the options cache.
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );

		if ( null === $current || (int) strtok( (string) $current, ':' ) >= time() ) {
			return false;
		}

		// Remove only the stale value we saw; a parallel takeover changes the value and nothing is deleted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock rows must bypass the options cache.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $option, $current ) );

		return self::insert( $option, $token ) ? $token : false;
	}

	/**
	 * Releases a lock if this request still owns it.
	 *
	 * @param string $name  Lock name.
	 * @param string $token Token returned by acquire().
	 * @return void
	 */
	public static function release( $name, $token ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock rows must bypass the options cache.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::PREFIX . $name, $token ) );
	}

	/**
	 * Inserts the lock row.
	 *
	 * @param string $option Option name.
	 * @param string $token  Lock token.
	 * @return bool True when this call created the row.
	 */
	private static function insert( $option, $token ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic insert; get_option()/add_option() cannot lock.
		return (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", $option, $token ) );
	}
}
