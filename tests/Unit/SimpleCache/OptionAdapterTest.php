<?php
/**
 * OptionAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\Tests\Unit\SimpleCache;

use Alley\WP\SimpleCache\Option_Adapter;
use Psr\SimpleCache\CacheInterface;

/**
 * Tests for Option_Adapter.
 */
final class OptionAdapterTest extends AdapterTestCase {
	/**
	 * Create instance that is used in the tests.
	 *
	 * @return CacheInterface
	 */
	public function create_simplecache() {
		return Option_Adapter::create();
	}

	/**
	 * Test clear().
	 */
	public function test_clear() {
		$this->markTestSkipped( 'Option_Adapter does not support clear()' );
	}

	/**
	 * Test binary data preservation.
	 */
	public function test_binary_data() {
		$this->markTestSkipped( 'wpdb does not support saving binary data' );
	}
}
