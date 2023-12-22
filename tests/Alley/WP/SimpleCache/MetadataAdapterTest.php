<?php
/**
 * MetadataAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;

final class MetadataAdapterTest extends AdapterTestCase {
	public function test_clear() {
		$this->markTestSkipped( 'Metadata_Adapter does not support clear()' );
	}

	/**
	 * @return CacheInterface that is used in the tests
	 */
	public function create_simplecache() {
		return Metadata_Adapter::create( 'post', self::factory()->post->create() );
	}
}
