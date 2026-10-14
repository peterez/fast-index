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
	 * Address returned by get_upgrade_url(); empty for the plans page of the site admin.
	 *
	 * @var string
	 */
	public $upgrade_url = '';

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
		return '' !== $this->upgrade_url ? $this->upgrade_url : admin_url( 'admin.php?page=fast-index-pricing' );
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
