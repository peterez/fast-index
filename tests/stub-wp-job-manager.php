<?php
/**
 * Stand-in for the one WP Job Manager function Fast Index calls (tests only).
 *
 * @package FastIndex
 */

if ( ! function_exists( 'wpjm_get_job_listing_structured_data' ) ) {
	/**
	 * JobPosting data for listings with a title, as WP Job Manager builds it from a listing's fields.
	 *
	 * @param WP_Post $post Listing.
	 * @return array|false
	 */
	function wpjm_get_job_listing_structured_data( $post ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Name of the WP Job Manager function this file stands in for.
		return '' !== $post->post_title ? array( '@type' => 'JobPosting' ) : false;
	}
}
