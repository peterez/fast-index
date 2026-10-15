<?php
/**
 * Plugin Name: Fast Index QA Google stub
 * Description: Local QA only. Answers the Google, Search Console and IndexNow endpoints Fast Index calls, blocks the Freemius API and counts every request by host (option fast_index_qa_requests) and by address (option fast_index_qa_urls), so the end-to-end tests never leave the machine. Copy into wp-content/mu-plugins while testing and remove it afterwards.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

// Page views of the Fast Index specs (header X-Fast-Index-QA) do not start WP-Cron: the tests run the events with
// WP-CLI, and a WP-Cron run in parallel could write back an older copy of the cron list.
if ( isset( $_SERVER['HTTP_X_FAST_INDEX_QA'] ) && ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The core constant, set for QA page views only.
}

/**
 * Fakes the answers of Google, Search Console and IndexNow, blocks the Freemius API and counts the requests.
 *
 * @param false|array|WP_Error $response Short-circuit value.
 * @param array                $args     Request arguments.
 * @param string               $url      Request URL.
 * @return false|array|WP_Error
 */
function fast_index_qa_stub( $response, $args, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$ok   = array(
		'headers'  => array(),
		'cookies'  => array(),
		'filename' => null,
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
	);

	$count          = (array) get_option( 'fast_index_qa_requests', array() );
	$count[ $host ] = ( isset( $count[ $host ] ) ? (int) $count[ $host ] : 0 ) + 1;
	update_option( 'fast_index_qa_requests', $count, false );

	// The addresses of each notification, so a test counts only its own posts (another lane may publish posts on the
	// same site at the same time).
	$body = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
	$urls = array();
	if ( is_array( $body ) ) {
		foreach ( array( 'url', 'inspectionUrl' ) as $field ) {
			if ( isset( $body[ $field ] ) ) {
				$urls[] = (string) $body[ $field ];
			}
		}
		if ( isset( $body['urlList'] ) && is_array( $body['urlList'] ) ) {
			$urls = array_merge( $urls, array_map( 'strval', $body['urlList'] ) );
		}
	}
	if ( array() !== $urls ) {
		$log   = (array) get_option( 'fast_index_qa_urls', array() );
		$log[] = array(
			'host' => $host,
			'urls' => $urls,
		);
		update_option( 'fast_index_qa_urls', array_slice( $log, -2000 ), false );
	}

	if ( 'api.indexnow.org' === $host ) {
		return array( 'body' => '' ) + array_replace(
			$ok,
			array(
				'response' => array(
					'code'    => 202,
					'message' => 'Accepted',
				),
			)
		);
	}

	if ( 'www.googleapis.com' === $host && false !== strpos( $url, '/webmasters/v3/sites' ) ) {
		return $ok + array(
			'body' => wp_json_encode(
				array(
					'siteEntry' => array(
						array(
							'siteUrl'         => home_url( '/' ),
							'permissionLevel' => 'siteOwner',
						),
					),
				)
			),
		);
	}

	if ( 'searchconsole.googleapis.com' === $host ) {
		return $ok + array(
			'body' => wp_json_encode(
				array(
					'inspectionResult' => array(
						'inspectionResultLink' => 'https://search.google.com/search-console/inspect',
						'indexStatusResult'    => array(
							'verdict'       => 'PASS',
							'coverageState' => 'Submitted and indexed',
							'lastCrawlTime' => gmdate( 'Y-m-d\\TH:i:s\\Z', time() - DAY_IN_SECONDS ),
						),
					),
				)
			),
		);
	}

	if ( 'oauth2.googleapis.com' === $host ) {
		return $ok + array(
			'body' => wp_json_encode(
				array(
					'access_token' => 'qa-token',
					'expires_in'   => 3600,
					'token_type'   => 'Bearer',
				)
			),
		);
	}

	if ( 'indexing.googleapis.com' === $host ) {
		return $ok + array( 'body' => wp_json_encode( array( 'urlNotificationMetadata' => array( 'url' => 'qa' ) ) ) );
	}

	if ( false !== strpos( $host, 'freemius.com' ) ) {
		return new WP_Error( 'fast_index_qa_blocked', 'Blocked in QA: ' . $url );
	}

	return $response;
}
add_filter( 'pre_http_request', 'fast_index_qa_stub', 10, 3 );

/**
 * Keeps WP-Cron (wp-cron.php, started by any page view) away from the Fast Index events: the tests run them with
 * WP-CLI, and a parallel run would send a notification twice. Other events run as usual.
 *
 * @param null|array $pre Ready events, or null to let WordPress decide.
 * @return null|array
 */
function fast_index_qa_ready_cron_jobs( $pre ) {
	if ( null !== $pre || ! wp_doing_cron() ) {
		return $pre;
	}

	$ready = array();
	$now   = microtime( true );

	foreach ( (array) _get_cron_array() as $time => $hooks ) {
		if ( $time > $now ) {
			break;
		}
		foreach ( (array) $hooks as $hook => $events ) {
			if ( 0 !== strpos( (string) $hook, 'fast_index_' ) ) {
				$ready[ $time ][ $hook ] = $events;
			}
		}
	}

	return $ready;
}
add_filter( 'pre_get_ready_cron_jobs', 'fast_index_qa_ready_cron_jobs' );
