<?php
/**
 * ObjectCacheAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;

final class ObjectCacheAdapterTest extends AdapterTestCase {
	/**
	 * @return CacheInterface that is used in the tests
	 */
	public function create_simplecache() {
		return Object_Cache_Adapter::create( rand_str() );
	}
}
