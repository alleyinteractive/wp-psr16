<?php
/**
 * PSR16_Compliant class file
 *
 * @package wp-psr16
 */

namespace Alley\WP\SimpleCache;

use DateInterval;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

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
	 * @throws Invalid_Argument_Exception If the $key string is not a legal value.
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 */
	public function get( $key, $default = null ): mixed {
		$this->validate_key( $key );

		$cache = $this->origin->get( $key, $default );

		return $this->decoded_value( $key, $cache, $default );
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @throws Invalid_Argument_Exception If the $key string is not a legal value.
	 *
	 * @param string                 $key   The key of the item to store.
	 * @param mixed                  $value The value of the item to store, must be serializable.
	 * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item.
	 * @return bool True on success and false on failure.
	 */
	public function set( $key, $value, $ttl = null ): bool {
		$this->validate_key( $key );
		$this->validate_ttl( $ttl );

		$ttl = $this->normalized_ttl( $ttl );

		if ( \is_int( $ttl ) && $ttl <= 0 ) {
			return $this->origin->delete( $key );
		}

		$enc = $this->encoded_value( $key, $value, $ttl );

		return $this->origin->set( $key, $enc, $ttl );
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @throws Invalid_Argument_Exception If the $key string is not a legal value.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 * @return bool True if the item was successfully removed. False if there was an error.
	 */
	public function delete( $key ): bool {
		$this->validate_key( $key );

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
	 * @phpstan-param iterable<string> $keys
	 * @phpstan-return iterable<string, mixed>
	 *
	 * @param iterable $keys    A list of keys that can be obtained in a single operation.
	 * @param mixed    $default Default value to return for keys that do not exist.
	 * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 */
	public function getMultiple( iterable $keys, $default = null ): iterable {
		$this->validate_iterable( $keys );

		$keys = $this->iterable_keys( $keys );

		foreach ( $keys as $key ) {
			$this->validate_key( $key );
		}

		$cache = $this->origin->getMultiple( $keys, $default );
		$final = [];

		foreach ( $cache as $key => $value ) {
			$final[ $key ] = $this->decoded_value( $key, $value, $default );
		}

		return $final;
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
	public function setMultiple( iterable $values, $ttl = null ): bool {
		$this->validate_iterable( $values );
		$this->validate_ttl( $ttl );

		$ttl = $this->normalized_ttl( $ttl );

		if ( \is_int( $ttl ) && $ttl <= 0 ) {
			$keys = \is_array( $values ) ? array_keys( $values ) : array_keys( iterator_to_array( $values ) );

			return $this->origin->deleteMultiple( $keys );
		}

		$final = [];

		foreach ( $values as $key => $value ) {
			$this->validate_key( $key );

			$final[ $key ] = $this->encoded_value( $key, $value, $ttl );
		}

		return $this->origin->setMultiple( $final, $ttl );
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
		$this->validate_iterable( $keys );

		$keys = $this->iterable_keys( $keys );

		foreach ( $keys as $key ) {
			$this->validate_key( $key );
		}

		return $this->origin->deleteMultiple( $keys );
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * @throws Invalid_Argument_Exception If the $key string is not a legal value.
	 *
	 * @param string $key The cache item key.
	 * @return bool
	 */
	public function has( $key ): bool {
		$this->validate_key( $key );

		return $this->origin->has( $key );
	}

	/**
	 * Validate cache key. Adapted from https://github.com/laminas/laminas-cache/.
	 *
	 * @throws Invalid_Argument_Exception If the $key string is not a legal value.
	 *
	 * @param mixed $key The key to validate.
	 */
	private function validate_key( mixed $key ): void {
		if ( ! \is_string( $key ) ) {
			throw new Invalid_Argument_Exception(
				esc_html(
					sprintf(
						'Key must be string; got type "%s" (%s)',
						\gettype( $key ),
						var_export( $key, true ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					),
				),
			);
		}

		if ( '' === $key ) {
			throw new Invalid_Argument_Exception( 'Key cannot be empty' );
		}

		if ( preg_match( sprintf( '/[%s]/', preg_quote( self::INVALID_KEY_CHARS, '/' ) ), $key ) ) {
			throw new Invalid_Argument_Exception(
				esc_html(
					sprintf(
						'Key cannot contain any of (%s)',
						self::INVALID_KEY_CHARS,
					),
				),
			);
		}
	}

	/**
	 * Validate iterable.
	 *
	 * @throws Invalid_Argument_Exception If input is invalid.
	 *
	 * @param mixed $iterable The iterable to validate.
	 */
	private function validate_iterable( mixed $iterable ): void {
		if ( ! is_iterable( $iterable ) ) {
			throw new Invalid_Argument_Exception(
				esc_html(
					sprintf(
						'Input must be iterable; got type "%s" (%s)',
						\gettype( $iterable ),
						var_export( $iterable, true ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					),
				),
			);
		}
	}

	/**
	 * Get keys from iterable.
	 *
	 * @phpstan-param iterable<string> $keys
	 * @phpstan-return array<string>
	 *
	 * @param iterable $keys The iterable.
	 * @return array
	 */
	private function iterable_keys( iterable $keys ): array {
		$out = [];

		foreach ( $keys as $key ) {
			$out[] = $key;
		}

		return $out;
	}

	/**
	 * Validate TTL type.
	 *
	 * @throws Invalid_Argument_Exception If input is invalid.
	 *
	 * @param mixed $ttl The TTL to validate.
	 */
	private function validate_ttl( mixed $ttl ): void {
		if ( ! \is_int( $ttl ) && ! $ttl instanceof DateInterval && null !== $ttl ) {
			throw new Invalid_Argument_Exception(
				esc_html(
					sprintf(
						'TTL must be integer, null, or DateInterval; got type "%s" (%s)',
						\gettype( $ttl ),
						var_export( $ttl, true ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					),
				),
			);
		}
	}

	/**
	 * Normalize possible TTL types to seconds, if any. From https://github.com/laminas/laminas-cache/.
	 *
	 * @param int|DateInterval|null $ttl The TTL to normalize.
	 * @return int|null
	 */
	private function normalized_ttl( int|DateInterval|null $ttl ): int|null {
		// null === absence of a TTL.
		if ( null === $ttl ) {
			return null;
		}

		// Integers are always okay.
		if ( \is_int( $ttl ) ) {
			return $ttl;
		}

		$now = $this->clock->now();
		$end = $now->add( $ttl );

		return $end->getTimestamp() - $now->getTimestamp();
	}

	/**
	 * Encoded cache item to preserve original key, data type, and expiration time.
	 *
	 * @param mixed    $key   The item's key.
	 * @param mixed    $value The item's value.
	 * @param int|null $ttl   The item's TTL.
	 * @return string
	 */
	private function encoded_value( mixed $key, mixed $value, int|null $ttl ): string {
		$value = [
			'iss' => self::ISS,
			'exp' => null,
			'key' => $key,
			'val' => $value,
		];

		if ( \is_int( $ttl ) ) {
			$value['exp'] = $this->clock->now()->getTimestamp() + $ttl;
		}

		// Serializing is necessary to preserve data types, per the PSR-16 spec.
		return serialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Decoded cache item, if it was encoded by us and not stale.
	 *
	 * @throws Invalid_Argument_Exception If stale value cannot be deleted.
	 *
	 * @param string $key     The item's expected key.
	 * @param mixed  $cache   The value to decode.
	 * @param mixed  $default The default value.
	 * @return mixed
	 */
	private function decoded_value( string $key, mixed $cache, mixed $default ): mixed {
		if ( ! \is_string( $cache ) || ! is_serialized( $cache ) ) {
			// Not recognized.
			return $default;
		}

		$cache = unserialize( $cache ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		$is_encoded = (
			\is_array( $cache )
			&& isset( $cache['iss'] )
			&& self::ISS === $cache['iss']
			&& isset( $cache['key'] )
			&& $cache['key'] === $key
			&& \array_key_exists( 'val', $cache )
			&& \array_key_exists( 'exp', $cache )
			&& ( \is_int( $cache['exp'] ) || \is_null( $cache['exp'] ) )
		);

		if ( ! $is_encoded ) {
			// Not recognized.
			return $default;
		}

		if ( \is_int( $cache['exp'] ) && $cache['exp'] <= $this->clock->now()->getTimestamp() ) {
			$this->origin->delete( $key );

			return $default;
		}

		return $cache['val'];
	}
}
