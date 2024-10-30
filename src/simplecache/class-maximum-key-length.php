<?php
/**
 * Maximum_Key_Length class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;
use Stringable;

/**
 * PSR-16 implementation that truncates cache keys longer than a given limit.
 */
final class Maximum_Key_Length implements CacheInterface {
	/**
	 * Constructor.
	 *
	 * @throws Invalid_Argument_Exception If the limit is not a legal value.
	 *
	 * @param int            $limit  Maximum key length.
	 * @param CacheInterface $origin The underlying cache implementation.
	 */
	public function __construct(
		private readonly int $limit,
		private readonly CacheInterface $origin,
	) {
		if ( $limit <= 64 ) {
			throw new Invalid_Argument_Exception( 'PSR-16 libraries must support keys of a length of up to 64 characters.' );
		}
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
		return $this->origin->get( $this->truncated_key( $key ), $default );
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
		return $this->origin->set( $this->truncated_key( $key ), $value, $ttl );
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
		return $this->origin->delete( $this->truncated_key( $key ) );
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool True on success and false on failure.
	 */
	public function clear(): bool {
		return $this->origin->clear();
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
		$map = [];

		foreach ( $keys as $key ) {
			$map[ $this->truncated_key( $key ) ] = $key;
		}

		$cache = $this->origin->getMultiple( array_keys( $map ), $default );

		$get = [];

		foreach ( $cache as $key => $value ) {
			$get[ $map[ $key ] ] = $value;
		}

		return $get;
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
		$set = [];

		foreach ( $values as $key => $value ) {
			$set[ $this->truncated_key( $key ) ] = $value;
		}

		return $this->origin->setMultiple( $set, $ttl );
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
		$delete = [];

		foreach ( $keys as $key ) {
			$delete[] = $this->truncated_key( $key );
		}

		return $this->origin->deleteMultiple( $delete );
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
		return $this->origin->has( $this->truncated_key( $key ) );
	}

	/**
	 * Truncates a key.
	 *
	 * @param string $key The cache item key.
	 * @return string
	 */
	private function truncated_key( string $key ): string {
		return substr( $key, 0, $this->limit );
	}
}
