<?php
/**
 * TransientAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;

final class TransientAdapterTest extends AdapterTestCase {
	public function test_clear() {
		$this->markTestSkipped( 'TransientAdapter does not support clear()' );
	}

	/**
	 * @return CacheInterface that is used in the tests
	 */
	public function create_simplecache() {
		return Transient_Adapter::create();
	}
}
