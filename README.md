# Random Extension Polyfill for PHP

This is a polyfill for the new `ext-random` extension that will be released with PHP 8.2.

RFC:

* https://wiki.php.net/rfc/rng_extension
* https://wiki.php.net/rfc/random_extension_improvement

## Requirements

* PHP 7.1
* GMP extension

## Installation

```bash
composer require 'arokettu/random-polyfill'
```

## Compatibility

The library is compatible with `ext-random` as released in PHP 8.2.0 beta 1.

## What works

* `Random\Randomizer`
  * `getInt($min, $max)`
  * `getInt()`
* Engines
  * `Random\Engine` interface
  * `Random\CryptoSafeEngine` interface
  * Secure Engine: `Random\Engine\Secure`
  * Mersenne Twister: `Random\Engine\Mt19937`

## TODO

* `Random\Randomizer`
  * `getBytes($length)`
  * `shuffleArray($array)`
  * `shuffleBytes($bytes)`
  * `pickArrayKeys($array, $num)`
* Keep updating with fixes from the upcoming betas and release 1.0.0 around PHP 8.2.0 rc 1
* Empty `arokettu/random-polyfill` v1.99 for PHP 8.2.0 users
* Other engines
  * Maybe
  * Some day
  * If I have time
  * Don't count on it

## License

The library is available as open source under the terms of the [3-Clause BSD License].
See `COPYING.adoc` for additional licenses.

[3-Clause BSD License]: https://opensource.org/licenses/BSD-3-Clause
