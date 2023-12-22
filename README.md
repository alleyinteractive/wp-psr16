# PSR16

This library provides PSR-16 implementations for WordPress projects. It includes adapters for caching data in the object cache, as transients, as options, and as post, term, or other object metadata.

## Installation

Install the latest version with:

```bash
$ composer require alleyinteractive/wp-psr16
```

## Basic usage

All the adapters in this library implement the `\Psr\SimpleCache\CacheInterface` interface and have no other public API apart from their static constructors.

```php
<?php

$transient    = \Alley\WP\SimpleCache\Transient_Adapter::create();
$object_cache = \Alley\WP\SimpleCache\Object_Cache_Adapter::create();
$option       = \Alley\WP\SimpleCache\Option_Adapter::create();
$post_meta    = \Alley\WP\SimpleCache\Metadata_Adapter::for_post( 123 );
$term_meta    = \Alley\WP\SimpleCache\Metadata_Adapter::for_term( 123 );
$user_meta    = \Alley\WP\SimpleCache\Metadata_Adapter::create( 'user', 123 );
$custom_meta  = \Alley\WP\SimpleCache\Metadata_Adapter::create( 'custom', 123 );
```

## Implementation details

WordPress [returns scalar values in the option and metadata database tables as strings](https://core.trac.wordpress.org/ticket/31820#comment:2), which is incompatible with PSR-16's requirement that data be returned from the cache "exactly as passed [including] the variable type."

This library works around the default behavior of the database by serializing and unserializing values when they are saved to and retrieved from storage, which has some side effects:

* Cached items are stored in a custom array structure that includes the serialized value.
* Cache keys are stored with a prefix to avoid giving the impression that the cache key as passed to the adapter can be used to retrieve the original value using WordPress APIs directly.
* The custom array structure includes the item's expiration time, which allows option and metadata adapters to support TTLs.

The `\Alley\WP\SimpleCache\PSR16_Compliant` decorator class is responsible for ensuring PSR-16 compatibility with respect to data types, expiration times, legal cache keys, and other requirements of the specification. You are free to use this decorator with your own cache adapters or with those of another library.

## Limitations

* The transient, option, and metadata adapters do not support the `clear()` method.
* The transient, option, and metadata adapters do not support saving binary data. Consult with the provider of your persistent object cache drop-in to determine whether it supports saving binary data.
* The metadata adapter bypasses type-specific functions like `get_post_meta()` in favor of underlying functions like `get_metadata()` for compatibility with other metadata functions like `metadata_exists()`.

## About

### License

[GPL-2.0-or-later](https://github.com/alleyinteractive/wp-psr16/blob/main/LICENSE)

### Maintainers

[Alley Interactive](https://github.com/alleyinteractive)
