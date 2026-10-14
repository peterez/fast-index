<?php
/**
 * Plugin Name: Fast Index QA Google stub
 * Description: Local QA only. Answers the Google endpoints Fast Index calls and blocks the Freemius API, so the end-to-end tests never leave the machine. Copy into wp-content/mu-plugins while testing and remove it afterwards.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fakes the answers of Google and blocks the Freemius API.
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
