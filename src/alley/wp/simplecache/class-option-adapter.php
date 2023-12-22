<?php
/**
 * Option_Adapter class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * PSR-16 implementation that caches data in a WordPress option.
 */
final class Option_Adapter implements CacheInterface {
	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Create an instance using the default composition.
	 *
	 * @return CacheInterface
	 */
	public static function create(): CacheInterface {
		return new PSR16_Compliant(
			new NativeClock(),
			new Prefixed(
				'_psr16_',
				new self(),
			),
		);
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
		return get_option( $this->truncated_key( $key ), $default );
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
		return (bool) update_option( $this->truncated_key( $key ), $value, false );
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
		$result = true;

		if ( $this->has( $key ) ) {
			$result = delete_option( $this->truncated_key( $key ) );
		}

		return (bool) $result;
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
	 * @param iterable<string> $keys    A list of keys that can be obtained in a single operation.
	 * @param mixed            $default Default value to return for keys that do not exist.
	 * @return iterable<string, mixed> A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 */
	public function getMultiple( iterable $keys, mixed $default = null ): iterable {
		$out = [];

		foreach ( $keys as $key ) {
			$out[ $key ] = $this->get( $key, $default );
		}

		return $out;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is neither an array nor a Traversable, or if any of
	 *                                                    the $keys are not a legal value.
	 *
	 * @param iterable               $values A list of key => value pairs for a multiple-set operation.
	 * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
	 *                                       the driver supports TTL then the library may set a default value
	 *                                       for it or let the driver take care of that.
	 * @return bool True on success and false on failure.
	 */
	public function setMultiple( iterable $values, \DateInterval|int|null $ttl = null ): bool {
		$success = true;

		foreach ( $values as $key => $value ) {
			if ( ! $this->set( $key, $value, $ttl ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is neither an array nor a Traversable, or if any of
	 *                                                     the $keys are not a legal value.
	 *
	 * @param iterable<string> $keys A list of string-based keys to be deleted.
	 * @return bool True if the items were successfully removed. False if there was an error.
	 */
	public function deleteMultiple( iterable $keys ): bool {
		$success = true;

		foreach ( $keys as $key ) {
			if ( ! $this->delete( $key ) ) {
				$success = false;
			}
		}

		return $success;
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
		$ref = new \stdClass();
		return $this->get( $key, $ref ) !== $ref;
	}

	/**
	 * Truncate a key to the maximum length allowed by WordPress.
	 *
	 * @param string $key The key to truncate.
	 * @return string
	 */
	private function truncated_key( string $key ): string {
		return substr( $key, 0, 172 );
	}
}
