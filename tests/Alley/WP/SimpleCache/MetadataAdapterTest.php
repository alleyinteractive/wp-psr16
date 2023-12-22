<?php
/**
 * MetadataAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;

/**
 * Tests for Metadata_Adapter.
 */
final class MetadataAdapterTest extends AdapterTestCase {
	/**
	 * Test clear().
	 */
	public function test_clear() {
		$this->markTestSkipped( 'Metadata_Adapter does not support clear()' );
	}

	/**
	 * Test binary data preservation.
	 */
	public function test_binary_data() {
		$this->markTestSkipped( 'wpdb does not support saving binary data' );
	}

	/**
	 * Create instance that is used in the tests.
	 *
	 * @return CacheInterface
	 */
	public function create_simplecache() {
		return Metadata_Adapter::create( 'post', self::factory()->post->create() );
	}
}
