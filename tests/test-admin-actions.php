<?php
/**
 * Capability and nonce checks of the admin handlers.
 *
 * @package FastIndex
 */

/**
 * Every write handler refuses users without manage_options and requests without a valid nonce.
 */
class Fast_Index_Test_Admin_Actions extends Fast_Index_Test_Case {

	/**
	 * Handlers under test.
	 *
	 * @var Fast_Index_Admin_Actions
	 */
	private $actions;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->actions = new Fast_Index_Admin_Actions();
		$this->catch_redirects();

		update_option( Fast_Index_Settings::OPTION, Fast_Index_Settings::defaults() );
	}

	/**
	 * Handler methods and their nonce actions.
	 *
	 * @return array[]
	 */
	public function data_handlers() {
		return array(
			'save'    => array( 'save_settings', Fast_Index_Admin_Actions::SAVE ),
			'upload'  => array( 'upload_key', Fast_Index_Admin_Actions::UPLOAD ),
			'delete'  => array( 'delete_key', Fast_Index_Admin_Actions::DELETE ),
			'dismiss' => array( 'dismiss_notice', Fast_Index_Admin_Actions::DISMISS ),
		);
	}

	/**
	 * A user without manage_options is refused before the nonce is looked at.
	 *
	 * @dataProvider data_handlers
	 *
	 * @param string $method Handler method.
	 * @param string $action Nonce action.
	 * @return void
	 */
	public function test_handlers_refuse_users_without_capability( $method, $action ) {
		$this->login_as( 'editor' );
		self::set_nonce( $action );
		self::set_nonce( $action, '_wpnonce' );

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'not allowed' );

		$this->run_handler( $method );
	}

	/**
	 * An administrator without a nonce is refused.
	 *
	 * @dataProvider data_handlers
	 *
	 * @param string $method Handler method.
	 * @return void
	 */
	public function test_handlers_refuse_requests_without_nonce( $method ) {
		$this->login_as( 'administrator' );

		$this->expectException( 'WPDieException' );

		$this->run_handler( $method );
	}

	/**
	 * A nonce of another action is refused.
	 *
	 * @return void
	 */
	public function test_save_refuses_nonce_of_another_action() {
		$this->login_as( 'administrator' );
		self::set_nonce( Fast_Index_Admin_Actions::DELETE );
		$_POST['fast_index_options'] = array( 'status' => '2' );

		try {
			$this->actions->save_settings();
			$this->fail( 'The handler accepted a wrong nonce.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( Fast_Index_Settings::STATUS_ACTIVE, Fast_Index_Settings::get()['status'] );
		}
	}

	/**
	 * A refused save leaves the stored settings untouched.
	 *
	 * @return void
	 */
	public function test_refused_save_does_not_change_settings() {
		$this->login_as( 'subscriber' );
		self::set_nonce( Fast_Index_Admin_Actions::SAVE );
		$_POST['fast_index_options'] = array( 'status' => '2' );

		try {
			$this->actions->save_settings();
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( Fast_Index_Settings::STATUS_ACTIVE, Fast_Index_Settings::get()['status'] );
	}

	/**
	 * An administrator with a valid nonce saves clean values only.
	 *
	 * @return void
	 */
	public function test_save_stores_sanitized_settings() {
		$this->login_as( 'administrator' );
		self::set_nonce( Fast_Index_Admin_Actions::SAVE );

		$_POST['fast_index_options'] = wp_slash(
			array(
				'status'      => '2',
				'post_type'   => array(
					'post'            => '1',
					"post' OR '1'='1" => '1',
					'unknown_type'    => '1',
				),
				'post_status' => array(
					'publish'  => '1',
					'evil_key' => '1',
				),
				'delete_data' => '1',
				'json_file'   => array( 'x' => array( 'file' => '/etc/passwd' ) ),
			)
		);

		try {
			$this->actions->save_settings();
			$this->fail( 'Expected a redirect.' );
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			$this->assertStringContainsString( 'page=fast-index', $redirect->getMessage() );
		}

		$saved = get_option( Fast_Index_Settings::OPTION );

		$this->assertSame( '2', $saved['status'] );
		$this->assertSame( array( 'post' => '1' ), $saved['post_type'] );
		$this->assertSame( array( 'publish' => '1' ), $saved['post_status'] );
		$this->assertTrue( $saved['delete_data'] );
		$this->assertArrayNotHasKey( 'json_file', $saved );
	}

	/**
	 * Deleting a key works with capability and nonce.
	 *
	 * @return void
	 */
	public function test_delete_key_removes_account() {
		$id = $this->add_account();

		$this->login_as( 'administrator' );
		self::set_nonce( Fast_Index_Admin_Actions::DELETE );
		$_POST['fast_index_account'] = $id;

		try {
			$this->actions->delete_key();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$this->assertSame( array(), Fast_Index_Accounts::all() );
	}

	/**
	 * An upload request without files stores nothing and writes nothing to disk.
	 *
	 * @return void
	 */
	public function test_upload_without_file_stores_nothing() {
		$this->login_as( 'administrator' );
		self::set_nonce( Fast_Index_Admin_Actions::UPLOAD );

		try {
			$this->actions->upload_key();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$this->assertSame( array(), Fast_Index_Accounts::all() );
		$messages = Fast_Index_Admin::take_flash();
		$this->assertSame( 'error', $messages[0]['type'] );
	}

	/**
	 * A file that was not uploaded through PHP is refused, even with a valid key inside.
	 *
	 * @return void
	 */
	public function test_upload_refuses_file_not_uploaded_by_php() {
		$tmp = wp_tempnam( 'key.json' );
		file_put_contents( $tmp, self::key_json() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$this->login_as( 'administrator' );
		self::set_nonce( Fast_Index_Admin_Actions::UPLOAD );
		$_FILES['fast_index_key_files'] = array(
			'name'     => array( 'key.json' ),
			'type'     => array( 'application/json' ),
			'tmp_name' => array( $tmp ),
			'error'    => array( UPLOAD_ERR_OK ),
			'size'     => array( filesize( $tmp ) ),
		);

		try {
			$this->actions->upload_key();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		wp_delete_file( $tmp );

		$this->assertSame( array(), Fast_Index_Accounts::all() );
	}

	/**
	 * Dismissing the key notice with capability and nonce removes it for good.
	 *
	 * @return void
	 */
	public function test_dismiss_notice_removes_flag() {
		Fast_Index_Notices::flag_key_rotation();

		$this->login_as( 'administrator' );
		self::set_nonce( Fast_Index_Admin_Actions::DISMISS, '_wpnonce' );
		$_GET['notice'] = Fast_Index_Notices::KEY_ROTATION;

		try {
			$this->actions->dismiss_notice();
		} catch ( Fast_Index_Test_Redirect $redirect ) {
			unset( $redirect );
		}

		$this->assertFalse( get_option( Fast_Index_Notices::KEY_ROTATION_OPTION ) );
	}

	/**
	 * Calls one handler by name with explicit calls (no dynamic method names).
	 *
	 * @param string $method Handler method.
	 * @return void
	 */
	private function run_handler( $method ) {
		switch ( $method ) {
			case 'save_settings':
				$this->actions->save_settings();
				break;
			case 'upload_key':
				$this->actions->upload_key();
				break;
			case 'delete_key':
				$this->actions->delete_key();
				break;
			default:
				$this->actions->dismiss_notice();
		}
	}
}
