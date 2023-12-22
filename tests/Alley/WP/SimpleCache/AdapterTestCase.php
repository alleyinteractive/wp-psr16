<?php
/**
 * AdapterTestCase class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Alley\WP\SimpleCache\PSR16_Compliant;
use Mantle\Testkit\Test_Case;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\Clock\MockClock;

/**
 * Test a SimpleCache adapter. Adapted from https://github.com/php-cache/integration-tests.
 */
abstract class AdapterTestCase extends Test_Case {
	/**
	 * Instance under test.
	 *
	 * @var CacheInterface
	 */
	private CacheInterface $cache;

	/**
	 * Clock instance.
	 *
	 * @var \Symfony\Component\Clock\ClockInterface
	 */
	private \Symfony\Component\Clock\ClockInterface $clock;

	/**
	 * Create instance that is used in the tests.
	 *
	 * @return CacheInterface
	 */
	abstract public function create_simplecache();

	/**
	 * Runs the routine before each test is executed.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->clock = new MockClock();
		$this->cache = new PSR16_Compliant(
			$this->clock,
			$this->create_simplecache(),
		);
	}

	/**
	 * After a test method runs, reset any state in WordPress the test method might have changed.
	 */
	protected function tearDown(): void {
		$this->cache->clear();

		parent::tearDown();
	}

	/**
	 * Advance time perceived by the cache for the purposes of testing TTL.
	 *
	 * @param int $seconds Number of seconds to advance.
	 */
	public function advance_time( $seconds ) {
		$this->clock->sleep( $seconds );
	}

	/**
	 * Data provider for invalid cache keys.
	 *
	 * @return array
	 */
	public static function invalid_keys() {
		return array_merge(
			self::invalid_array_keys(),
			[
				[ 2 ],
			],
		);
	}

	/**
	 * Data provider for invalid array keys.
	 *
	 * @return array
	 */
	public static function invalid_array_keys() {
		return [
			[ '' ],
			[ true ],
			[ false ],
			[ null ],
			[ 2.5 ],
			[ '{str' ],
			[ 'rand{' ],
			[ 'rand{str' ],
			[ 'rand}str' ],
			[ 'rand(str' ],
			[ 'rand)str' ],
			[ 'rand/str' ],
			[ 'rand\\str' ],
			[ 'rand@str' ],
			[ 'rand:str' ],
			[ new \stdClass() ],
			[ [ 'array' ] ],
		];
	}

	/**
	 * Data provider for invalid TTL values.
	 *
	 * @return array
	 */
	public static function invalid_ttl() {
		return [
			[ '' ],
			[ true ],
			[ false ],
			[ 'abc' ],
			[ 2.5 ],
			[ ' 1' ], // Can be cast to int.
			[ '12foo' ], // Can be cast to int.
			[ '025' ], // Can be interpreted as hex.
			[ new \stdClass() ],
			[ [ 'array' ] ],
		];
	}

	/**
	 * Data provider for valid keys.
	 *
	 * @return array
	 */
	public static function valid_keys() {
		return [
			[ 'AbC19_.' ],
			[ '1234567890123456789012345678901234567890123456789012345678901234' ],
		];
	}

	/**
	 * Data provider for valid data to store.
	 *
	 * @return array
	 */
	public static function valid_data() {
		return [
			[ 'AbC19_.' ],
			[ 4711 ],
			[ 47.11 ],
			[ true ],
			[ null ],
			[ [ 'key' => 'value' ] ],
			[ new \stdClass() ],
		];
	}

	/**
	 * Test set().
	 */
	public function test_set() {
		$result = $this->cache->set( 'key', 'value' );
		$this->assertTrue( $result, 'set() must return true if success' );
		$this->assertEquals( 'value', $this->cache->get( 'key' ) );
	}

	/**
	 * Test set() with TTLs.
	 */
	public function test_set_ttl() {
		$result = $this->cache->set( 'key1', 'value', 2 );
		$this->assertTrue( $result, 'set() must return true if success' );
		$this->assertEquals( 'value', $this->cache->get( 'key1' ) );

		$this->cache->set( 'key2', 'value', new \DateInterval( 'PT2S' ) );
		$this->assertEquals( 'value', $this->cache->get( 'key2' ) );

		$this->advance_time( 3 );

		$this->assertNull( $this->cache->get( 'key1' ), 'Value must expire after ttl.' );
		$this->assertNull( $this->cache->get( 'key2' ), 'Value must expire after ttl.' );
	}

	/**
	 * Test set() with expired TTLs.
	 */
	public function test_set_expired_ttl() {
		$this->cache->set( 'key0', 'value' );
		$this->cache->set( 'key0', 'value', 0 );
		$this->assertNull( $this->cache->get( 'key0' ) );
		$this->assertFalse( $this->cache->has( 'key0' ) );

		$this->cache->set( 'key1', 'value', - 1 );
		$this->assertNull( $this->cache->get( 'key1' ) );
		$this->assertFalse( $this->cache->has( 'key1' ) );
	}

	/**
	 * Test get().
	 */
	public function test_get() {
		$this->assertNull( $this->cache->get( 'key' ) );
		$this->assertEquals( 'foo', $this->cache->get( 'key', 'foo' ) );

		$this->cache->set( 'key', 'value' );
		$this->assertEquals( 'value', $this->cache->get( 'key', 'foo' ) );
	}

	/**
	 * Test delete().
	 */
	public function test_delete() {
		$this->assertTrue( $this->cache->delete( 'key' ), 'Deleting a value that does not exist should return true' );
		$this->cache->set( 'key', 'value' );
		$this->assertTrue( $this->cache->delete( 'key' ), 'Delete must return true on success' );
		$this->assertNull( $this->cache->get( 'key' ), 'Values must be deleted on delete()' );
	}

	/**
	 * Test clear().
	 */
	public function test_clear() {
		$this->assertTrue( $this->cache->clear(), 'Clearing an empty cache should return true' );
		$this->cache->set( 'key', 'value' );
		$this->assertTrue( $this->cache->clear(), 'Delete must return true on success' );
		$this->assertNull( $this->cache->get( 'key' ), 'Values must be deleted on clear()' );
	}

	/**
	 * Test setMultiple().
	 */
	public function test_setMultiple() {
		$result = $this->cache->setMultiple(
			[
				'key0' => 'value0',
				'key1' => 'value1',
			]
		);
		$this->assertTrue( $result, 'setMultiple() must return true if success' );
		$this->assertEquals( 'value0', $this->cache->get( 'key0' ) );
		$this->assertEquals( 'value1', $this->cache->get( 'key1' ) );
	}

	/**
	 * See https://github.com/php-cache/integration-tests/issues/92.
	 */
	public function test_setMultiple_with_integer_array_key() {
		$this->markTestSkipped( 'Integer array keys are not supported.' );
	}

	/**
	 * Test setMultiple() with TTLs.
	 */
	public function test_setMultiple_ttl() {
		$this->cache->setMultiple(
			[
				'key2' => 'value2',
				'key3' => 'value3',
			],
			2
		);
		$this->assertEquals( 'value2', $this->cache->get( 'key2' ) );
		$this->assertEquals( 'value3', $this->cache->get( 'key3' ) );

		$this->cache->setMultiple( [ 'key4' => 'value4' ], new \DateInterval( 'PT2S' ) );
		$this->assertEquals( 'value4', $this->cache->get( 'key4' ) );

		$this->advance_time( 3 );
		$this->assertNull( $this->cache->get( 'key2' ), 'Value must expire after ttl.' );
		$this->assertNull( $this->cache->get( 'key3' ), 'Value must expire after ttl.' );
		$this->assertNull( $this->cache->get( 'key4' ), 'Value must expire after ttl.' );
	}

	/**
	 * Test setMultiple() with expired TTLs.
	 */
	public function test_setMultiple_expired_ttl() {
		$this->cache->setMultiple(
			[
				'key0' => 'value0',
				'key1' => 'value1',
			],
			0
		);
		$this->assertNull( $this->cache->get( 'key0' ) );
		$this->assertNull( $this->cache->get( 'key1' ) );
	}

	/**
	 * Test setMultiple() with generator function.
	 */
	public function test_setMultiple_with_generator() {
		$gen = function () {
			yield 'key0' => 'value0';
			yield 'key1' => 'value1';
		};

		$this->cache->setMultiple( $gen() );
		$this->assertEquals( 'value0', $this->cache->get( 'key0' ) );
		$this->assertEquals( 'value1', $this->cache->get( 'key1' ) );
	}

	/**
	 * Test getMultiple().
	 */
	public function test_getMultiple() {
		$result = $this->cache->getMultiple( [ 'key0', 'key1' ] );
		$keys   = [];
		foreach ( $result as $i => $r ) {
			$keys[] = $i;
			$this->assertNull( $r );
		}
		sort( $keys );
		$this->assertSame( [ 'key0', 'key1' ], $keys );

		$this->cache->set( 'key3', 'value' );
		$result = $this->cache->getMultiple( [ 'key2', 'key3', 'key4' ], 'foo' );
		$keys   = [];
		foreach ( $result as $key => $r ) {
			$keys[] = $key;
			if ( 'key3' === $key ) {
				$this->assertEquals( 'value', $r );
			} else {
				$this->assertEquals( 'foo', $r );
			}
		}
		sort( $keys );
		$this->assertSame( [ 'key2', 'key3', 'key4' ], $keys );
	}

	/**
	 * Test getMultiple() with a generator function.
	 */
	public function test_getMultiple_with_generator() {
		$gen = function () {
			yield 1 => 'key0';
			yield 1 => 'key1';
		};

		$this->cache->set( 'key0', 'value0' );
		$result = $this->cache->getMultiple( $gen() );
		$keys   = [];
		foreach ( $result as $key => $r ) {
			$keys[] = $key;
			if ( 'key0' === $key ) {
				$this->assertEquals( 'value0', $r );
			} elseif ( 'key1' === $key ) {
				$this->assertNull( $r );
			} else {
				$this->assertFalse( true, 'This should not happen' );
			}
		}
		sort( $keys );
		$this->assertSame( [ 'key0', 'key1' ], $keys );
		$this->assertEquals( 'value0', $this->cache->get( 'key0' ) );
		$this->assertNull( $this->cache->get( 'key1' ) );
	}

	/**
	 * Test deleteMultiple().
	 */
	public function test_deleteMultiple() {
		$this->assertTrue( $this->cache->deleteMultiple( [] ), 'Deleting a empty array should return true' );
		$this->assertTrue(
			$this->cache->deleteMultiple( [ 'key' ] ),
			'Deleting a value that does not exist should return true'
		);

		$this->cache->set( 'key0', 'value0' );
		$this->cache->set( 'key1', 'value1' );
		$this->assertTrue( $this->cache->deleteMultiple( [ 'key0', 'key1' ] ), 'Delete must return true on success' );
		$this->assertNull( $this->cache->get( 'key0' ), 'Values must be deleted on deleteMultiple()' );
		$this->assertNull( $this->cache->get( 'key1' ), 'Values must be deleted on deleteMultiple()' );
	}

	/**
	 * Test deleteMultiple() with a generator function.
	 */
	public function test_deleteMultiple_generator() {
		$gen = function () {
			yield 1 => 'key0';
			yield 1 => 'key1';
		};
		$this->cache->set( 'key0', 'value0' );
		$this->assertTrue( $this->cache->deleteMultiple( $gen() ), 'Deleting a generator should return true' );

		$this->assertNull( $this->cache->get( 'key0' ), 'Values must be deleted on deleteMultiple()' );
		$this->assertNull( $this->cache->get( 'key1' ), 'Values must be deleted on deleteMultiple()' );
	}

	/**
	 * Test has().
	 */
	public function test_has() {
		$this->assertFalse( $this->cache->has( 'key0' ) );
		$this->cache->set( 'key0', 'value0' );
		$this->assertTrue( $this->cache->has( 'key0' ) );
	}

	/**
	 * Test long keys.
	 */
	public function test_basic_usage_with_long_key() {
		$key = str_repeat( 'a', 300 );

		$this->assertFalse( $this->cache->has( $key ) );
		$this->assertTrue( $this->cache->set( $key, 'value' ) );

		$this->assertTrue( $this->cache->has( $key ) );
		$this->assertSame( 'value', $this->cache->get( $key ) );

		$this->assertTrue( $this->cache->delete( $key ) );

		$this->assertFalse( $this->cache->has( $key ) );
	}

	/**
	 * Test get() with invalid keys.
	 *
	 * @dataProvider invalid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_get_invalid_keys( $key ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->get( $key );
	}

	/**
	 * Test getMultiple() with invalid keys.
	 *
	 * @dataProvider invalid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_getMultiple_invalid_keys( $key ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->getMultiple( [ 'key1', $key, 'key2' ] );
	}

	/**
	 * Test getMultiple() with an invalid iterable.
	 */
	public function test_getMultiple_no_iterable() {
		// Now a TypeError, not an InvalidArgumentException.
		$this->expectException( 'TypeError' );
		$this->cache->getMultiple( 'key' );
	}

	/**
	 * Test set() with invalid keys.
	 *
	 * @dataProvider invalid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_set_invalid_keys( $key ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->set( $key, 'foobar' );
	}

	/**
	 * Test setMultiple() with invalid keys.
	 *
	 * @dataProvider invalid_array_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_setMultiple_invalid_keys( $key ) {
		$values = function () use ( $key ) {
			yield 'key1' => 'foo';
			yield $key => 'bar';
			yield 'key2' => 'baz';
		};
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->setMultiple( $values() );
	}

	/**
	 * Test setMultiple() with an invalid iterable.
	 */
	public function test_setMultiple_no_iterable() {
		// Now a TypeError, not an InvalidArgumentException.
		$this->expectException( 'TypeError' );
		$this->cache->setMultiple( 'key' );
	}

	/**
	 * Test has() with invalid keys.
	 *
	 * @dataProvider invalid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_has_invalid_keys( $key ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->has( $key );
	}

	/**
	 * Test delete() with invalid keys.
	 *
	 * @dataProvider invalid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_delete_invalid_keys( $key ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->delete( $key );
	}

	/**
	 * Test deleteMultiple() with invalid keys.
	 *
	 * @dataProvider invalid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_deleteMultiple_invalid_keys( $key ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->deleteMultiple( [ 'key1', $key, 'key2' ] );
	}

	/**
	 * Test deleteMultiple() with an invalid iterable.
	 */
	public function test_deleteMultiple_no_iterable() {
		$this->expectException( 'TypeError' );
		$this->cache->deleteMultiple( 'key' );
	}

	/**
	 * Test set() with invalid TTLs.
	 *
	 * @dataProvider invalid_ttl
	 *
	 * @param mixed $ttl TTL to test.
	 */
	public function test_set_invalid_ttl( $ttl ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->set( 'key', 'value', $ttl );
	}

	/**
	 * Test setMultiple() with invalid TTLs.
	 *
	 * @dataProvider invalid_ttl
	 *
	 * @param mixed $ttl TTL to test.
	 */
	public function test_setMultiple_invalid_ttl( $ttl ) {
		$this->expectException( 'Psr\SimpleCache\InvalidArgumentException' );
		$this->cache->setMultiple( [ 'key' => 'value' ], $ttl );
	}

	/**
	 * Test overwriting values with null.
	 */
	public function test_null_overwrite() {
		$this->cache->set( 'key', 5 );
		$this->cache->set( 'key', null );

		$this->assertNull( $this->cache->get( 'key' ), 'Setting null to a key must overwrite previous value' );
	}

	/**
	 * Test string data type preservation.
	 */
	public function test_data_type_string() {
		$this->cache->set( 'key', '5' );
		$result = $this->cache->get( 'key' );
		$this->assertTrue( '5' === $result, 'Wrong data type. If we store a string we must get an string back.' );
		$this->assertTrue( \is_string( $result ), 'Wrong data type. If we store a string we must get an string back.' );
	}

	/**
	 * Test integer data type preservation.
	 */
	public function test_data_type_integer() {
		$this->cache->set( 'key', 5 );
		$result = $this->cache->get( 'key' );
		$this->assertTrue( 5 === $result, 'Wrong data type. If we store an int we must get an int back.' );
		$this->assertTrue( \is_int( $result ), 'Wrong data type. If we store an int we must get an int back.' );
	}

	/**
	 * Test float data type preservation.
	 */
	public function test_data_type_float() {
		$float = 1.23456789;
		$this->cache->set( 'key', $float );
		$result = $this->cache->get( 'key' );
		$this->assertTrue( \is_float( $result ), 'Wrong data type. If we store float we must get an float back.' );
		$this->assertEquals( $float, $result );
	}

	/**
	 * Test boolean data type preservation.
	 */
	public function test_data_type_boolean() {
		$this->cache->set( 'key', false );
		$result = $this->cache->get( 'key' );
		$this->assertTrue( \is_bool( $result ), 'Wrong data type. If we store boolean we must get an boolean back.' );
		$this->assertFalse( $result );
		$this->assertTrue( $this->cache->has( 'key' ), 'has() should return true when true are stored. ' );
	}

	/**
	 * Test array data type preservation.
	 */
	public function test_data_type_array() {
		$array = [
			'a' => 'foo',
			2   => 'bar',
		];
		$this->cache->set( 'key', $array );
		$result = $this->cache->get( 'key' );
		$this->assertTrue( \is_array( $result ), 'Wrong data type. If we store array we must get an array back.' );
		$this->assertEquals( $array, $result );
	}

	/**
	 * Test object data type preservation.
	 */
	public function test_data_type_object() {
		$object    = new \stdClass();
		$object->a = 'foo';
		$this->cache->set( 'key', $object );
		$result = $this->cache->get( 'key' );
		$this->assertTrue( \is_object( $result ), 'Wrong data type. If we store object we must get an object back.' );
		$this->assertEquals( $object, $result );
	}

	/**
	 * Test binary data preservation.
	 */
	public function test_binary_data() {
		$data = '';
		for ( $i = 0; $i < 256; $i++ ) {
			$data .= \chr( $i );
		}

		$this->cache->set( 'key', $data );
		$result = $this->cache->get( 'key' );
		$this->assertTrue( $data === $result, 'Binary data must survive a round trip.' );
	}

	/**
	 * Test set() with valid keys.
	 *
	 * @dataProvider valid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_set_valid_keys( $key ) {
		$this->cache->set( $key, 'foobar' );
		$this->assertEquals( 'foobar', $this->cache->get( $key ) );
	}

	/**
	 * Test setMultiple() with valid keys.
	 *
	 * @dataProvider valid_keys
	 *
	 * @param mixed $key Key to test.
	 */
	public function test_setMultiple_valid_keys( $key ) {
		$this->cache->setMultiple( [ $key => 'foobar' ] );
		$result = $this->cache->getMultiple( [ $key ] );
		$keys   = [];
		foreach ( $result as $i => $r ) {
			$keys[] = $i;
			$this->assertEquals( $key, $i );
			$this->assertEquals( 'foobar', $r );
		}
		$this->assertSame( [ $key ], $keys );
	}

	/**
	 * Test set() with valid data.
	 *
	 * @dataProvider valid_data
	 *
	 * @param mixed $data Data to test.
	 */
	public function test_set_valid_data( $data ) {
		$this->cache->set( 'key', $data );
		$this->assertEquals( $data, $this->cache->get( 'key' ) );
	}

	/**
	 * Test setMultiple() with valid data.
	 *
	 * @dataProvider valid_data
	 *
	 * @param mixed $data Data to test.
	 */
	public function test_setMultiple_valid_data( $data ) {
		$this->cache->setMultiple( [ 'key' => $data ] );
		$result = $this->cache->getMultiple( [ 'key' ] );
		$keys   = [];
		foreach ( $result as $i => $r ) {
			$keys[] = $i;
			$this->assertEquals( $data, $r );
		}
		$this->assertSame( [ 'key' ], $keys );
	}

	/**
	 * Test use of object as default value.
	 */
	public function test_object_as_default_value() {
		$obj      = new \stdClass();
		$obj->foo = 'value';
		$this->assertEquals( $obj, $this->cache->get( 'key', $obj ) );
	}

	/**
	 * Test that an object does not change between storage and retrieval.
	 */
	public function test_object_does_not_change_in_cache() {
		$obj      = new \stdClass();
		$obj->foo = 'value';
		$this->cache->set( 'key', $obj );
		$obj->foo = 'changed';

		$cache_object = $this->cache->get( 'key' );
		$this->assertEquals( 'value', $cache_object->foo, 'Object in cache should not have their values changed.' );
	}
}
