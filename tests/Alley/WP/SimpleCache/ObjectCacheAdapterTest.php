<?php
/**
 * ObjectCacheAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;

/**
 * Tests for Object_Cache_Adapter.
 */
final class ObjectCacheAdapterTest extends AdapterTestCase {
	/**
	 * Create instance that is used in the tests.
	 *
	 * @return CacheInterface
	 */
	public function create_simplecache() {
		return Object_Cache_Adapter::create( rand_str() );
	}
}
