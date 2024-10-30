<?php
/**
 * Invalid_Argument_Exception class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

/**
 * Exception interface for invalid cache arguments.
 */
final class Invalid_Argument_Exception extends \Exception implements \Psr\SimpleCache\InvalidArgumentException {}
