<?php
/**
 * JWT, token cache and Indexing API requests (all HTTP mocked).
 *
 * @package FastIndex
 */

/**
 * Native Google client.
 */
class Fast_Index_Test_Google_Client extends Fast_Index_Test_Case {

	/**
	 * Client of the default test account.
	 *
	 * @return Fast_Index_Google_Client
	 */
	private function client() {
		return new Fast_Index_Google_Client( Fast_Index_Accounts::parse_key_json( self::key_json() ) );
	}

	/**
	 * Decodes one base64url JWT segment.
	 *
	 * @param string $segment Segment.
	 * @return string
	 */
	private static function decode_segment( $segment ) {
		return base64_decode( strtr( $segment, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $segment ) % 4 ) % 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the JWT under test.
	}

	/**
	 * The JWT is RS256-signed with the service account key and carries Google's claims.
	 *
	 * @return void
	 */
	public function test_jwt_is_signed_with_rs256() {
		$jwt = $this->client()->build_jwt( 1700000000 );

		$this->assertIsString( $jwt );

		list( $header, $claims, $signature ) = explode( '.', $jwt );

		$header_data = json_decode( self::decode_segment( $header ), true );
		$claim_data  = json_decode( self::decode_segment( $claims ), true );

		$this->assertSame( 'RS256', $header_data['alg'] );
		$this->assertSame( 'JWT', $header_data['typ'] );
		$this->assertSame( 'abc123' . md5( 'default' ), $header_data['kid'] );
		$this->assertSame( 'indexer@example-project.iam.gserviceaccount.com', $claim_data['iss'] );
		$this->assertSame( 'https://www.googleapis.com/auth/indexing', $claim_data['scope'] );
		$this->assertSame( 'https://oauth2.googleapis.com/token', $claim_data['aud'] );
		$this->assertSame( 1700000000, $claim_data['iat'] );
		$this->assertSame( 1700003600, $claim_data['exp'] );

		$public = openssl_pkey_get_details( openssl_pkey_get_private( self::private_key() ) )['key'];

		$this->assertSame( 1, openssl_verify( $header . '.' . $claims, self::decode_segment( $signature ), $public, OPENSSL_ALGO_SHA256 ) );
	}

	/**
	 * An unreadable private key gives an error, not a PHP warning.
	 *
	 * @return void
	 */
	public function test_jwt_with_bad_key_is_an_error() {
		$client = new Fast_Index_Google_Client(
			array(
				'client_email'   => 'a@example.com',
				'private_key'    => "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----",
				'private_key_id' => '',
			)
		);

		$this->assertWPError( $client->build_jwt( time() ) );
	}

	/**
	 * The token request uses the JWT bearer grant at the fixed token endpoint.
	 *
	 * @return void
	 */
	public function test_token_request_format() {
		$this->fake_google();

		$this->assertSame( 'token-1', $this->client()->get_access_token() );

		$request = $this->requests_to( Fast_Index_Google_Client::TOKEN_URL )[0];

		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'urn:ietf:params:oauth:grant-type:jwt-bearer', $request['args']['body']['grant_type'] );
		$this->assertSame( 3, substr_count( $request['args']['body']['assertion'], '.' ) + 1 );
		$this->assertSame( 0, $request['args']['redirection'] );
		$this->assertGreaterThan( 0, $request['args']['timeout'] );
		$this->assertTrue( $request['args']['sslverify'] );
	}

	/**
	 * The token is cached per key for expires_in minus 60 seconds.
	 *
	 * @return void
	 */
	public function test_token_is_cached_per_key() {
		$this->fake_google();

		$account = Fast_Index_Accounts::parse_key_json( self::key_json() );
		$client  = new Fast_Index_Google_Client( $account );

		$this->assertSame( 'token-1', $client->get_access_token() );
		$this->assertSame( 'token-1', $client->get_access_token() );
		$this->assertCount( 1, $this->requests_to( Fast_Index_Google_Client::TOKEN_URL ) );

		$key     = Fast_Index_Google_Client::token_cache_key( $account );
		$timeout = (int) get_option( '_transient_timeout_' . $key );

		$this->assertStringStartsWith( 'fast_index_token_', $key );
		$this->assertGreaterThanOrEqual( time() + 3540 - 5, $timeout );
		$this->assertLessThanOrEqual( time() + 3540, $timeout );

		$other = Fast_Index_Accounts::parse_key_json( self::key_json( 'second@example-project.iam.gserviceaccount.com', 'second' ) );

		$this->assertNotSame( $key, Fast_Index_Google_Client::token_cache_key( $other ) );
		$this->assertSame( 'token-2', ( new Fast_Index_Google_Client( $other ) )->get_access_token() );
	}

	/**
	 * Publish sends the Bearer token and a JSON body with url and type.
	 *
	 * @return void
	 */
	public function test_publish_request_format() {
		$this->fake_google();

		$result = $this->client()->publish( 'https://example.org/hello-world/', Fast_Index_Google_Client::TYPE_DELETED );

		$this->assertIsArray( $result );

		$request = $this->requests_to( Fast_Index_Google_Client::PUBLISH_URL )[0];

		$this->assertSame( 'https://indexing.googleapis.com/v3/urlNotifications:publish', $request['url'] );
		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'Bearer token-1', $request['args']['headers']['Authorization'] );
		$this->assertSame( 'application/json', $request['args']['headers']['Content-Type'] );
		$this->assertSame(
			array(
				'url'  => 'https://example.org/hello-world/',
				'type' => 'URL_DELETED',
			),
			json_decode( $request['args']['body'], true )
		);
	}

	/**
	 * A quota error keeps the HTTP status and Google's reason, not the body.
	 *
	 * @return void
	 */
	public function test_publish_quota_error() {
		$this->publish_codes = array( 429 );
		$this->fake_google();

		$result = $this->client()->publish( 'https://example.org/a/', Fast_Index_Google_Client::TYPE_UPDATED );

		$this->assertWPError( $result );
		$this->assertSame( 429, Fast_Index_Google_Client::error_status( $result ) );
		$this->assertSame( 'publish', Fast_Index_Google_Client::error_context( $result ) );
		$this->assertSame( 'RESOURCE_EXHAUSTED', $result->get_error_data()['reason'] );
	}

	/**
	 * A rejected token is forgotten, so the next call asks for a new one.
	 *
	 * @return void
	 */
	public function test_unauthorized_publish_forgets_token() {
		$this->publish_codes = array( 401, 200 );
		$this->fake_google();

		$client = $this->client();

		$this->assertWPError( $client->publish( 'https://example.org/a/', Fast_Index_Google_Client::TYPE_UPDATED ) );
		$this->assertIsArray( $client->publish( 'https://example.org/a/', Fast_Index_Google_Client::TYPE_UPDATED ) );
		$this->assertCount( 2, $this->requests_to( Fast_Index_Google_Client::TOKEN_URL ) );
	}

	/**
	 * Network failures, invalid JSON and unknown types are errors without an HTTP status.
	 *
	 * @return void
	 */
	public function test_failures_without_status() {
		$client = $this->client();

		// No mock: the test bootstrap blocks the request like a network failure.
		$this->assertSame( 0, Fast_Index_Google_Client::error_status( $client->publish( 'https://example.org/', Fast_Index_Google_Client::TYPE_UPDATED ) ) );
		$this->assertWPError( $client->publish( 'https://example.org/', 'URL_REMOVED' ) );

		add_filter(
			'pre_http_request',
			function () {
				return self::http_response( 200, 'not json' );
			}
		);

		$result = $client->get_access_token();

		$this->assertWPError( $result );
		$this->assertSame( 'fast_index_invalid_response', $result->get_error_code() );
	}
}
