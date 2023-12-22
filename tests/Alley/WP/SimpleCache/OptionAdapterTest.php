<?php
/**
 * OptionAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;

final class OptionAdapterTest extends AdapterTestCase {
	public function test_clear() {
		$this->markTestSkipped( 'Option_Adapter does not support clear()' );
	}

	/**
	 * @return CacheInterface that is used in the tests
	 */
	public function create_simplecache() {
		return Option_Adapter::create();
	}
}
