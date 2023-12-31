<?php
/**
 * Metadata_Adapter class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * PSR-16 implementation that caches data in WordPress object metadata.
 */
final class Metadata_Adapter implements CacheInterface {
	/**
	 * Constructor.
	 *
	 * @param string $type Object type.
	 * @param int    $id   Object ID.
	 */
	private function __construct(
		private readonly string $type,
		private readonly int $id,
	) {}

	/**
	 * Create an instance using the default composition.
	 *
	 * @param string $type Object type.
	 * @param int    $id   Object ID.
	 * @return CacheInterface
	 */
	public static function create( string $type, int $id ): CacheInterface {
		return new PSR16_Compliant(
			new NativeClock(),
			new Prefixed_Keys(
				'_psr16_',
				new Maximum_Key_Length(
					255,
					new self(
						$type,
						$id,
					),
				),
			),
		);
	}

	/**
	 * Create an instance for caching to post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return CacheInterface
	 */
	public static function for_post( int $post_id ): CacheInterface {
		return self::create( 'post', $post_id );
	}

	/**
	 * Create an instance for caching to term meta.
	 *
	 * @param int $term_id Term ID.
	 * @return CacheInterface
	 */
	public static function for_term( int $term_id ): CacheInterface {
		return self::create( 'term', $term_id );
	}

	/**
	 * Fetches a value from the cache.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$get = $this->getMultiple( [ $key ], $default );

		$out = $default;

		foreach ( $get as $index => $value ) {
			if ( $index === $key ) {
				$out = $value;
			}
		}

		return $out;
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
	 *
	 * @param string                 $key   The key of the item to store.
	 * @param mixed                  $value The value of the item to store, must be serializable.
	 * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item.
	 * @return bool True on success and false on failure.
	 */
	public function set( string $key, mixed $value, \DateInterval|int|null $ttl = null ): bool {
		return $this->setMultiple( [ $key => $value ], $ttl );
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 * @return bool True if the item was successfully removed. False if there was an error.
	 */
	public function delete( string $key ): bool {
		delete_metadata( $this->type, $this->id, $key );

		return true;
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool True on success and false on failure.
	 */
	public function clear(): bool {
		return false; // Not supported by default in WordPress.
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is neither an array nor a Traversable, or if any of
	 *                                                   the $keys are not a legal value.
	 *
	 * @phpstan-param iterable<string> $keys
	 * @phpstan-return iterable<string, mixed>
	 *
	 * @param iterable $keys    A list of keys that can be obtained in a single operation.
	 * @param mixed    $default Default value to return for keys that do not exist.
	 * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 */
	public function getMultiple( iterable $keys, mixed $default = null ): iterable {
		$metadata = get_metadata( $this->type, $this->id );

		$out = [];

		if ( \is_array( $metadata ) ) {
			foreach ( $keys as $key ) {
				$out[ $key ] = $default;

				if ( isset( $metadata[ $key ][0] ) ) {
					$out[ $key ] = maybe_unserialize( $metadata[ $key ][0] );
				}
			}
		}

		return $out;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is neither an array nor a Traversable, or if any of
	 *                                                    the $keys are not a legal value.
	 *
	 * @phpstan-param iterable<string, mixed> $values
	 *
	 * @param iterable               $values A list of key => value pairs for a multiple-set operation.
	 * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
	 *                                       the driver supports TTL then the library may set a default value
	 *                                       for it or let the driver take care of that.
	 * @return bool True on success and false on failure.
	 */
	public function setMultiple( iterable $values, \DateInterval|int|null $ttl = null ): bool {
		foreach ( $values as $key => $value ) {
			update_metadata( $this->type, $this->id, $key, $value );
		}

		return true;
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is neither an array nor a Traversable, or if any of
	 *                                                     the $keys are not a legal value.
	 *
	 * @phpstan-param iterable<string> $keys
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 * @return bool True if the items were successfully removed. False if there was an error.
	 */
	public function deleteMultiple( iterable $keys ): bool {
		foreach ( $keys as $key ) {
			$this->delete( $key );
		}

		return true;
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
	 *
	 * @param string $key The cache item key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return (bool) metadata_exists( $this->type, $this->id, $key );
	}
}
