<?php
/**
 * Test double of the Freemius SDK instance.
 *
 * @package FastIndex
 */

/**
 * Answers the Freemius calls of Fast Index without the SDK: a license is taken as valid or not, and the upgrade
 * page has a fixed address. Any other call returns null.
 */
class Fast_Index_Test_Freemius {

	/**
	 * Whether the premium code may run (a valid license or trial).
	 *
	 * @var bool
	 */
	private $licensed;

	/**
	 * Builds the double.
	 *
	 * @param bool $licensed Whether a license allows the premium code.
	 */
	public function __construct( $licensed ) {
		$this->licensed = (bool) $licensed;
	}

	/**
	 * Freemius::can_use_premium_code().
	 *
	 * @return bool
	 */
	public function can_use_premium_code() {
		return $this->licensed;
	}

	/**
	 * Freemius::get_upgrade_url().
	 *
	 * @return string
	 */
	public function get_upgrade_url() {
		return admin_url( 'admin.php?page=fast-index-pricing' );
	}

	/**
	 * Any other Freemius method.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Arguments.
	 * @return null
	 */
	public function __call( $name, $arguments ) {
		return null;
	}
}
