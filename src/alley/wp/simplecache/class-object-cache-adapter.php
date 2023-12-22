<?php
/**
 * Object_Cache_Adapter class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * PSR-16 implementation that caches data with the WordPress cache API.
 */
final class Object_Cache_Adapter implements CacheInterface {
	/**
	 * Constructor.
	 *
	 * @param string $group Cache group.
	 */
	private function __construct(
		private readonly string $group,
	) {}

	/**
	 * Create an instance using the default composition.
	 *
	 * @param string $group Cache group.
	 * @return CacheInterface
	 */
	public static function create( string $group ): CacheInterface {
		return new PSR16_Compliant(
			new NativeClock(),
			new Prefixed_Keys(
				'_psr16_',
				new self( $group ),
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
		$found = null;

		$out = wp_cache_get( $key, $this->group, false, $found );

		// Not all implementations honor $found.
		if ( ( null !== $found && ! $found ) || false === $out ) {
			$out = $default;
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
		return (bool) wp_cache_set(
			$key,
			$value,
			$this->group,
			// @phpstan-ignore-next-line The PSR16_Compliant cache normalizes TTLs to int|null.
			null === $ttl ? 0 : (int) $ttl, // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		);
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
			$result = wp_cache_delete( $key, $this->group );
		}

		return (bool) $result;
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool True on success and false on failure.
	 */
	public function clear(): bool {
		return wp_cache_flush_group( $this->group );
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
		$out = wp_cache_get_multiple( \is_array( $keys ) ? $keys : iterator_to_array( $keys ), $this->group );

		foreach ( $out as $key => $value ) {
			if ( false === $value ) {
				$out[ $key ] = $default;
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
		$results = wp_cache_set_multiple(
			\is_array( $values ) ? $values : iterator_to_array( $values ),
			$this->group,
			// @phpstan-ignore-next-line
			null === $ttl ? 0 : (int) $ttl,
		);

		$success = true;

		foreach ( $results as $result ) {
			if ( false === $result ) {
				$success = false;
				break;
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
	 * @phpstan-param iterable<string> $keys
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 * @return bool True if the items were successfully removed. False if there was an error.
	 */
	public function deleteMultiple( iterable $keys ): bool {
		$delete = [];

		foreach ( $keys as $key ) {
			if ( $this->has( $key ) ) {
				$delete[] = $key;
			}
		}

		$results = wp_cache_delete_multiple( $delete, $this->group );

		$success = true;

		foreach ( $results as $result ) {
			if ( false === $result ) {
				$success = false;
				break;
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
}
