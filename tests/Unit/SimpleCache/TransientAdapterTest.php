<?php
/**
 * TransientAdapterTest class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\Tests\Unit\SimpleCache;

use Alley\WP\SimpleCache\Transient_Adapter;
use Psr\SimpleCache\CacheInterface;

/**
 * Tests for Transient_Adapter.
 */
final class TransientAdapterTest extends AdapterTestCase {
	/**
	 * Create instance that is used in the tests.
	 *
	 * @return CacheInterface
	 */
	public function create_simplecache() {
		return Transient_Adapter::create();
	}

	/**
	 * Test clear().
	 */
	public function test_clear() {
		$this->markTestSkipped( 'Transient_Adapter does not support clear()' );
	}

	/**
	 * Test binary data preservation.
	 */
	public function test_binary_data() {
		$this->markTestSkipped( 'wpdb does not support saving binary data' );
	}
}
