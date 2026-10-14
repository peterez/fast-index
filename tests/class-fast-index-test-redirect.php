<?php
/**
 * Exception used instead of a redirect in tests.
 *
 * @package FastIndex
 */

/**
 * Thrown instead of redirecting, so a handler's exit is never reached in tests.
 */
class Fast_Index_Test_Redirect extends Exception {
}
