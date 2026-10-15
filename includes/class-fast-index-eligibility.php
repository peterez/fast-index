<?php
/**
 * Whether a page is of a kind that Google supports in the Indexing API.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google: "The Indexing API can only be used to crawl pages with either JobPosting or BroadcastEvent embedded in a
 * VideoObject." This class looks for those types in the saved data of a post: JSON-LD blocks in the content
 * (<script type="application/ld+json">, @graph and @type lists included), microdata (itemtype), and the job
 * listings of WP Job Manager, whose JobPosting data it builds from the listing's own fields. It makes no request
 * and does not render the post, so data that a theme or another plugin prints only on the page is not seen; the
 * fast_index_is_eligible filter can add it.
 *
 * @link https://developers.google.com/search/apis/indexing-api/v3/quickstart
 */
class Fast_Index_Eligibility {

	/**
	 * Structured data types Google supports in the Indexing API.
	 */
	const TYPES = array( 'JobPosting', 'BroadcastEvent' );

	/**
	 * Meta key on a Google History entry: the post had a supported type when it was sent ("1").
	 */
	const META = '_fast_index_eligible';

	/**
	 * Whether a post has JobPosting or BroadcastEvent data in its saved content.
	 *
	 * @param int|WP_Post $post Post or post ID.
	 * @return bool
	 */
	public static function is_eligible( $post ) {
		$post     = get_post( $post );
		$eligible = $post instanceof WP_Post && ( array() !== self::types_in( (string) $post->post_content ) || self::is_job_manager_listing( $post ) );

		/**
		 * Filters whether a post counts as supported by the Google Indexing API (JobPosting or BroadcastEvent).
		 *
		 * @param bool         $eligible Whether the saved content has one of the types.
		 * @param WP_Post|null $post     Post.
		 */
		return (bool) apply_filters( 'fast_index_is_eligible', $eligible, $post instanceof WP_Post ? $post : null );
	}

	/**
	 * Whether a post is a WP Job Manager listing that has JobPosting data. Only when that plugin is active.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	private static function is_job_manager_listing( WP_Post $post ) {
		if ( 'job_listing' !== $post->post_type || ! function_exists( 'wpjm_get_job_listing_structured_data' ) ) {
			return false;
		}

		$data = wpjm_get_job_listing_structured_data( $post );

		return is_array( $data ) && isset( $data['@type'] ) && 'JobPosting' === $data['@type'];
	}

	/**
	 * Supported types found in a piece of HTML.
	 *
	 * @param string $html Saved post content.
	 * @return string[] Found types, each once.
	 */
	public static function types_in( $html ) {
		$html  = (string) $html;
		$found = array();

		if ( false === stripos( $html, 'JobPosting' ) && false === stripos( $html, 'BroadcastEvent' ) ) {
			return $found;
		}

		if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $blocks ) ) {
			foreach ( $blocks[1] as $json ) {
				$data  = json_decode( trim( html_entity_decode( $json, ENT_QUOTES, 'UTF-8' ) ), true );
				$found = array_merge( $found, is_array( $data ) ? self::types_in_data( $data ) : array() );
			}
		}

		if ( preg_match_all( '#itemtype=["\']https?://schema\.org/(JobPosting|BroadcastEvent)["\']#i', $html, $items ) ) {
			foreach ( $items[1] as $type ) {
				$found[] = 'jobposting' === strtolower( $type ) ? 'JobPosting' : 'BroadcastEvent';
			}
		}

		return array_values( array_intersect( self::TYPES, array_unique( $found ) ) );
	}

	/**
	 * Supported types in decoded JSON-LD, at any depth.
	 *
	 * @param array $data Decoded JSON-LD.
	 * @param int   $depth Current depth.
	 * @return string[]
	 */
	private static function types_in_data( array $data, $depth = 0 ) {
		$found = array();

		if ( $depth > 10 ) {
			return $found;
		}

		if ( isset( $data['@type'] ) ) {
			foreach ( (array) $data['@type'] as $type ) {
				if ( is_string( $type ) && in_array( $type, self::TYPES, true ) ) {
					$found[] = $type;
				}
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$found = array_merge( $found, self::types_in_data( $value, $depth + 1 ) );
			}
		}

		return $found;
	}

	/**
	 * Values for the SQL condition "AND ( %d = 0 OR p.post_content LIKE %s OR p.post_content LIKE %s )" that the
	 * counters and the old-post batches add: while "Send to Google only pages it supports" is on, only posts whose
	 * content may hold a supported type are kept. The PHP check above stays the final word.
	 *
	 * @param array $settings Clean settings.
	 * @return array Three values: the switch (0 off, 1 on) and the two LIKE patterns.
	 */
	public static function sql_values( array $settings ) {
		global $wpdb;

		return array( empty( $settings['eligible_only'] ) ? 0 : 1, '%' . $wpdb->esc_like( 'JobPosting' ) . '%', '%' . $wpdb->esc_like( 'BroadcastEvent' ) . '%' );
	}
}
