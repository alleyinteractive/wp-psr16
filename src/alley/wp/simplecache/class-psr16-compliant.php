<?php
/**
 * PSR16_Compliant class file
 *
 * @package wp-psr16
 */

// declare(strict_types=1);

namespace Alley\WP\SimpleCache;

use DateInterval;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Ensures PSR-16 compliance for the underlying cache implementation.
 */
final class PSR16_Compliant implements CacheInterface {
	/**
	 * Invalid cache key characters.
	 *
	 * @var string
	 */
	private const INVALID_KEY_CHARS = '{}()/\@:';

	/**
	 * Cache object issuer.
	 *
	 * @var string
	 */
	private const ISS = 'wp-psr16';

	/**
	 * Constructor.
	 *
	 * @param ClockInterface $clock  The current time.
	 * @param CacheInterface $origin The underlying cache implementation.
	 */
	public function __construct(
		private readonly ClockInterface $clock,
		private readonly CacheInterface $origin,
	) {}

	/**
	 * Fetches a value from the cache.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 */
	public function get( mixed $key, mixed $default = null ): mixed {
		$this->validateKey( $key );

		$cache = $this->origin->get( $key, $default );

		return $this->decodedValue( $key, $cache, $default );
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
	public function set( mixed $key, mixed $value, mixed $ttl = null ): bool {
		$this->validateKey( $key );
		$this->validateTtl( $ttl );

		$ttl = $this->normalizedTtl( $ttl );

		if ( \is_int( $ttl ) && $ttl <= 0 ) {
			return $this->origin->delete( $key );
		}

		$enc = $this->encodedValue( $key, $value, $ttl );

		return $this->origin->set( $key, $enc, $ttl );
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 * @return bool True if the item was successfully removed. False if there was an error.
	 */
	public function delete( mixed $key ): bool {
		$this->validateKey( $key );

		return $this->origin->delete( $key );
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
	 * @param iterable<string> $keys    A list of keys that can be obtained in a single operation.
	 * @param mixed            $default Default value to return for keys that do not exist.
	 * @return iterable<string, mixed> A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 */
	public function getMultiple( iterable $keys, mixed $default = null ): iterable {
		$this->validateIterable( $keys );

		foreach ( $keys as $key ) {
			$this->validateKey( $key );
		}

		$cache = $this->origin->getMultiple( $keys, $default );
		$final = [];

		foreach ( $cache as $key => $value ) {
			$final[ $key ] = $this->decodedValue( $key, $value, $default );
		}

		return $final;
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
	public function setMultiple( iterable $values, mixed $ttl = null ): bool {
		$this->validateIterable( $values );
		$this->validateTtl( $ttl );

		$ttl = $this->normalizedTtl( $ttl );

		if ( \is_int( $ttl ) && $ttl <= 0 ) {
			$keys = \is_array( $values ) ? array_keys( $values ) : array_keys( iterator_to_array( $values ) );

			return $this->origin->deleteMultiple( $keys );
		}

		$final = [];

		foreach ( $values as $key => $value ) {
			$this->validateKey( $key );

			$final[ $key ] = $this->encodedValue( $key, $value, $ttl );
		}

		return $this->origin->setMultiple( $final, $ttl );
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
		$this->validateIterable( $keys );

		foreach ( $keys as $key ) {
			$this->validateKey( $key );
		}

		return $this->origin->deleteMultiple( $keys );
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
	 *
	 * @param string $key The cache item key.
	 * @return bool
	 */
	public function has( mixed $key ): bool {
		$this->validateKey( $key );

		return $this->origin->has( $key );
	}

	/**
	 * Adapted from https://github.com/laminas/laminas-cache/.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If key is invalid.
	 */
	private function validateKey( mixed $key ): void {
		if ( ! \is_string( $key ) ) {
			throw new Invalid_Argument_Exception(
				sprintf(
					'Key must be string; got type "%s" %s',
					\gettype( $key ),
					sprintf( ' (%s)', var_export( $key, true ) ),
				),
			);
		}

		if ( '' === $key ) {
			throw new Invalid_Argument_Exception( 'Key cannot be empty' );
		}

		if ( preg_match( sprintf( '/[%s]/', preg_quote( self::INVALID_KEY_CHARS, '/' ) ), $key ) ) {
			throw new Invalid_Argument_Exception(
				sprintf(
					'Key cannot contain any of (%s)',
					self::INVALID_KEY_CHARS,
				),
			);
		}
	}

	/**
	 * @throws \Psr\SimpleCache\InvalidArgumentException If input is invalid.
	 */
	private function validateIterable( mixed $iterable ): void {
		if ( ! is_iterable( $iterable ) ) {
			throw new Invalid_Argument_Exception(
				sprintf(
					'Input must be iterable; got type "%s" %s',
					\gettype( $iterable ),
					sprintf( ' (%s)', var_export( $iterable, true ) ),
				),
			);
		}
	}

	/**
	 * @throws \Psr\SimpleCache\InvalidArgumentException If input is invalid.
	 */
	private function validateTtl( mixed $ttl ): void {
		if ( ! \is_int( $ttl ) && ! $ttl instanceof DateInterval && null !== $ttl ) {
			throw new Invalid_Argument_Exception(
				sprintf(
					'TTL must be integer, null, or DateInterval; got type "%s" %s',
					\gettype( $ttl ),
					sprintf( ' (%s)', var_export( $ttl, true ) ),
				),
			);
		}
	}

	/**
	 * From https://github.com/laminas/laminas-cache/.
	 */
	private function normalizedTtl( mixed $ttl ): int|null {
		// null === absence of a TTL
		if ( null === $ttl ) {
			return null;
		}

		// integers are always okay
		if ( \is_int( $ttl ) ) {
			return $ttl;
		}

		$now = $this->clock->now();
		$end = $now->add( $ttl );

		return $end->getTimestamp() - $now->getTimestamp();
	}

	private function encodedValue( mixed $key, mixed $value, int|null $ttl ): string {
		$value = [
			'iss' => self::ISS,
			'exp' => null,
			'key' => $key,
			'val' => $value,
		];

		if ( \is_int( $ttl ) ) {
			$value['exp'] = $this->clock->now()->getTimestamp() + $ttl;
		}

		return serialize( $value );
	}

	private function decodedValue( mixed $key, mixed $cache, mixed $default ): mixed {
		if ( $cache === $default ) {
			return $default;
		}

		if ( ! \is_string( $cache ) ) {
			return $cache;
		}

		$cache = unserialize( $cache );

		$is_encoded = (
			\is_array( $cache )
			&& isset( $cache['iss'] )
			&& $cache['iss'] === self::ISS
			&& isset( $cache['key'] )
			&& $cache['key'] === $key
			&& \array_key_exists( 'val', $cache )
			&& \array_key_exists( 'exp', $cache )
			&& ( \is_int( $cache['exp'] ) || \is_null( $cache['exp'] ) )
		);

		if ( ! $is_encoded ) {
			return $cache;
		}

		if ( \is_int( $cache['exp'] ) && $cache['exp'] <= $this->clock->now()->getTimestamp() ) {
			$this->origin->delete( $key );

			return $default;
		}

		return $cache['val'];
	}
}
