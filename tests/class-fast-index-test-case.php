<?php
/**
 * Shared helpers for the Fast Index tests.
 *
 * @package FastIndex
 */

/**
 * Base test case: RSA test keys, a fake Google endpoint and redirect capture.
 */
abstract class Fast_Index_Test_Case extends WP_UnitTestCase {

	/**
	 * Generated private keys, by label (key generation is slow).
	 *
	 * @var string[]
	 */
	private static $keys = array();

	/**
	 * Requests seen by the fake Google endpoint.
	 *
	 * @var array[]
	 */
	protected $requests = array();

	/**
	 * Status codes the fake publish endpoint answers with, in order. The last one repeats.
	 *
	 * @var int[]
	 */
	protected $publish_codes = array( 200 );

	/**
	 * The Freemius instance replaced by a test double, restored after the test.
	 *
	 * @var object|null
	 */
	private $real_freemius = null;

	/**
	 * Resets globals touched by the tests. Hooks added by the premium module are removed by the parent, which
	 * restores the hooks of before the test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		$_FILES   = array();

		if ( null !== $this->real_freemius ) {
			$GLOBALS['figi_fs']  = $this->real_freemius;
			$this->real_freemius = null;
		}

		parent::tear_down();
	}

	/**
	 * Replaces the Freemius instance with a test double.
	 *
	 * @param bool $licensed Whether a license allows the premium code.
	 * @return void
	 */
	protected function fake_freemius( $licensed ) {
		if ( null === $this->real_freemius ) {
			$this->real_freemius = $GLOBALS['figi_fs'];
		}

		$GLOBALS['figi_fs'] = new Fast_Index_Test_Freemius( $licensed );
	}

	/**
	 * Loads the premium module as a valid license would. Its hooks are removed again after the test.
	 *
	 * @return void
	 */
	protected function with_premium() {
		$this->fake_freemius( true );
		$this->assertTrue( Fast_Index_Premium::maybe_boot(), 'The premium module loads with a license.' );
	}

	/**
	 * Returns a PEM private key generated with OpenSSL for the tests.
	 *
	 * @param string $label Key label; the same label returns the same key.
	 * @return string
	 */
	protected static function private_key( $label = 'default' ) {
		if ( ! isset( self::$keys[ $label ] ) ) {
			$resource = openssl_pkey_new(
				array(
					'private_key_bits' => 2048,
					'private_key_type' => OPENSSL_KEYTYPE_RSA,
				)
			);
			openssl_pkey_export( $resource, $pem );
			self::$keys[ $label ] = $pem;
		}

		return self::$keys[ $label ];
	}

	/**
	 * Builds a service account key file body like the one Google Cloud downloads.
	 *
	 * @param string $email Client email.
	 * @param string $label Key label.
	 * @return string
	 */
	protected static function key_json( $email = 'indexer@example-project.iam.gserviceaccount.com', $label = 'default' ) {
		return wp_json_encode(
			array(
				'type'           => 'service_account',
				'project_id'     => 'example-project',
				'private_key_id' => 'abc123' . md5( $label ),
				'private_key'    => self::private_key( $label ),
				'client_email'   => $email,
				'client_id'      => '1234567890',
				'token_uri'      => 'https://evil.example/token',
			)
		);
	}

	/**
	 * Stores an account and returns its ID.
	 *
	 * @param string $email Client email.
	 * @param string $label Key label.
	 * @return string
	 */
	protected function add_account( $email = 'indexer@example-project.iam.gserviceaccount.com', $label = 'default' ) {
		return Fast_Index_Accounts::save( Fast_Index_Accounts::parse_key_json( self::key_json( $email, $label ) ) );
	}

	/**
	 * Answers Google requests locally and records them.
	 *
	 * @return void
	 */
	protected function fake_google() {
		add_filter( 'pre_http_request', array( $this, 'answer_google' ), 10, 3 );
	}

	/**
	 * Fake Google endpoint.
	 *
	 * @param false|array $response Short-circuit value.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return array|false
	 */
	public function answer_google( $response, $args, $url ) {
		$this->requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( Fast_Index_Google_Client::TOKEN_URL === $url ) {
			return self::http_response(
				200,
				array(
					'access_token' => 'token-' . count( $this->requests ),
					'expires_in'   => 3600,
					'token_type'   => 'Bearer',
				)
			);
		}

		if ( Fast_Index_Google_Client::PUBLISH_URL === $url ) {
			$code = count( $this->publish_codes ) > 1 ? array_shift( $this->publish_codes ) : $this->publish_codes[0];
			$body = 200 === $code
				? array( 'urlNotificationMetadata' => array( 'url' => 'x' ) )
				: array(
					'error' => array(
						'code'    => $code,
						'message' => 'Fake error',
						'status'  => 429 === $code ? 'RESOURCE_EXHAUSTED' : 'ERROR',
					),
				);

			return self::http_response( $code, $body );
		}

		return $response;
	}

	/**
	 * Requests sent to one URL.
	 *
	 * @param string $url URL.
	 * @return array[]
	 */
	protected function requests_to( $url ) {
		return array_values(
			array_filter(
				$this->requests,
				function ( $request ) use ( $url ) {
					return $url === $request['url'];
				}
			)
		);
	}

	/**
	 * Builds a WordPress HTTP API response array.
	 *
	 * @param int   $code HTTP status.
	 * @param mixed $body Body; arrays are JSON encoded.
	 * @return array
	 */
	protected static function http_response( $code, $body ) {
		return array(
			'headers'  => array(),
			'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Makes wp_safe_redirect() throw, so handlers stop before exit.
	 *
	 * @return void
	 */
	protected function catch_redirects() {
		add_filter( 'wp_redirect', array( $this, 'throw_redirect' ) );
	}

	/**
	 * Redirect filter that throws.
	 *
	 * @param string $location Redirect target.
	 * @return void
	 * @throws Fast_Index_Test_Redirect Always.
	 */
	public function throw_redirect( $location ) {
		throw new Fast_Index_Test_Redirect( esc_url_raw( $location ) );
	}

	/**
	 * Logs in a user with a role.
	 *
	 * @param string $role Role name.
	 * @return int User ID.
	 */
	protected function login_as( $role ) {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );

		if ( is_multisite() && 'administrator' === $role ) {
			grant_super_admin( $user_id );
		}

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Puts a nonce into the request.
	 *
	 * @param string $action Nonce action.
	 * @param string $field  Field name.
	 * @return void
	 */
	protected static function set_nonce( $action, $field = Fast_Index_Admin_Actions::NONCE_FIELD ) {
		$nonce              = wp_create_nonce( $action );
		$_POST[ $field ]    = $nonce;
		$_REQUEST[ $field ] = $nonce;
	}
}
