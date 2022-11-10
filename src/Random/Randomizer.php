<?php

/**
 * @copyright Copyright © 2022 Anton Smirnov
 * @license BSD-3-Clause https://spdx.org/licenses/BSD-3-Clause.html
 *
 * Includes adaptation of C code from the PHP Interpreter
 * @license PHP-3.01 https://spdx.org/licenses/PHP-3.01.html
 * @see https://github.com/php/php-src/blob/eff9aed/ext/random/randomizer.c
 * @see https://github.com/php/php-src/blob/eff9aed/ext/standard/array.c
 * @see https://github.com/php/php-src/blob/eff9aed/ext/standard/string.c
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */

declare(strict_types=1);

namespace Random;

use Arokettu\Random\Math;
use Arokettu\Random\NoDynamicProperties;
use Closure;
use Error;
use Exception;
use GMP;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Serializable;
use ValueError;

/**
 * @property-read Engine $engine
 */
final class Randomizer implements Serializable
{
    use NoDynamicProperties {
        __set as nodyn__set;
    }

    private const PHP_MT_RAND_MAX = 0x7FFFFFFF;
    private const RANDOM_RANGE_ATTEMPTS = 50;

    /** @var Math */
    private static $math32;
    /** @var Math */
    private static $math64;
    /** @var GMP|int|string */
    private static $UINT32_ZERO;
    /** @var GMP|int|string */
    private static $UINT32_MAX;
    /** @var GMP|int|string */
    private static $UINT64_ZERO;
    /** @var GMP|int|string */
    private static $UINT64_MAX;
    /** @var GMP|int|string */
    private static $UINT32_MAX_64;

    /** @var Engine */
    private $engine;

    public function __construct(?Engine $engine = null)
    {
        $this->initMath();

        /** @psalm-suppress RedundantConditionGivenDocblockType not yet initialized */
        if ($this->engine !== null) {
            throw new Error('Cannot modify readonly property Random\Randomizer::$engine');
        }

        $this->engine = $engine ?? new Engine\Secure();
    }

    /**
     * @codeCoverageIgnore
     * @psalm-suppress DocblockTypeContradiction the "constants" are initialized here
     */
    private function initMath(): void
    {
        $math32 = &self::$math32;
        $math64 = &self::$math64;

        if ($math32 === null) {
            $math32 = Math::create(Math::SIZEOF_UINT32_T);
            $math64 = Math::create(Math::SIZEOF_UINT64_T);

            self::$UINT32_ZERO = $math32->fromInt(0);
            self::$UINT32_MAX  = $math32->fromHex('ffffffff');

            self::$UINT64_ZERO = $math64->fromInt(0);
            self::$UINT64_MAX  = $math64->fromHex('ffffffffffffffff');

            self::$UINT32_MAX_64 = $math64->fromHex('ffffffff');
        }
    }

    private function generate(): string
    {
        $retval = $this->engine->generate();

        $size = \strlen($retval);

        if ($size === 0) {
            throw new BrokenRandomEngineError('A random engine must return a non-empty string');
        } elseif ($size > Math::SIZEOF_UINT64_T) {
            $retval = \substr($retval, 0, Math::SIZEOF_UINT64_T);
        }

        return $retval;
    }

    public function getInt(int $min, int $max): int
    {
        if ($max < $min) {
            throw new ValueError(
                __METHOD__ . '(): Argument #2 ($max) must be greater than or equal to argument #1 ($min)'
            );
        }

        // engine has range func
        if (
            $this->engine instanceof Secure
        ) {
            /** @psalm-suppress PossiblyInvalidFunctionCall */
            return Closure::bind(function (int $min, int $max): ?int {
                /** @psalm-suppress UndefinedMethod */
                return $this->range($min, $max);
            }, $this->engine, $this->engine)($min, $max);
        }

        // handle MT_RAND_PHP
        /** @psalm-suppress PossiblyInvalidFunctionCall */
        if (
            $this->engine instanceof Mt19937 &&
            Closure::bind(function () {
                /** @psalm-suppress UndefinedThisPropertyFetch */
                return $this->mode === \MT_RAND_PHP; // read private property
            }, $this->engine, $this->engine)()
        ) {
            return $this->rangeBadscaling($min, $max);
        }

        $math32 = &self::$math32;
        $math64 = &self::$math64;

        $umax = $math64->subInt($math64->fromInt($max), $min);

        if ($math64->compare($umax, self::$UINT32_MAX_64) > 0) {
            $rangeval = $this->range64($umax);
            return $math64->toSignedInt($math64->addInt($rangeval, $min));
        } else {
            $umax = $math32->subInt($math32->fromInt($max), $min);
            $rangeval = $this->range32($umax);
            return $math32->toSignedInt($math32->addInt($rangeval, $min));
        }
    }

    /**
     * @param GMP|string|int $umax
     * @return GMP|string|int
     */
    private function range32($umax)
    {
        $result = '';
        do {
            $result .= $this->generate();
        } while (\strlen($result) < Math::SIZEOF_UINT32_T);

        $math32 = &self::$math32;
        $UINT32_MAX = &self::$UINT32_MAX;

        $result = $math32->fromBinary($result);

        if ($umax == $UINT32_MAX) {
            return $result;
        }

        $umax1 = $umax;
        $umax = $math32->addInt($umax, 1);

        if (($umax & $umax1) == self::$UINT32_ZERO) {
            return $result & $umax1;
        }

        $limit = // $UINT32_MAX - ($UINT32_MAX % $umax) - 1;
            $math32->subInt(
                $math32->sub(
                    $UINT32_MAX,
                    $math32->mod(self::$UINT32_MAX, $umax)
                ),
                1
            );

        $count = 0;

        while ($math32->compare($result, $limit) > 0) {
            if (++$count > self::RANDOM_RANGE_ATTEMPTS) {
                throw new BrokenRandomEngineError('Failed to generate an acceptable random number in 50 attempts');
            }

            $result = '';
            do {
                $result .= $this->generate();
            } while (\strlen($result) < Math::SIZEOF_UINT32_T);

            $result = $math32->fromBinary($result);
        }

        return $math32->mod($result, $umax);
    }

    /**
     * @param GMP|string|int $umax
     * @return GMP|string|int
     */
    private function range64($umax)
    {
        $result = '';
        do {
            $result .= $this->generate();
        } while (\strlen($result) < Math::SIZEOF_UINT64_T);

        $math64 = &self::$math64;
        $UINT64_MAX = &self::$UINT64_MAX;

        $result = $math64->fromBinary($result);

        if ($umax == $UINT64_MAX) {
            return $result;
        }

        $umax1 = $umax;
        $umax = $math64->addInt($umax, 1);

        if (($umax & $umax1) == self::$UINT64_ZERO) {
            return $result & $umax1;
        }

        $limit = // $UINT64_MAX - ($UINT64_MAX % $umax) - 1;
            $math64->subInt(
                $math64->sub(
                    $UINT64_MAX,
                    $math64->mod($UINT64_MAX, $umax)
                ),
                1
            );

        $count = 0;

        while ($math64->compare($result, $limit) > 0) {
            if (++$count > self::RANDOM_RANGE_ATTEMPTS) {
                throw new BrokenRandomEngineError('Failed to generate an acceptable random number in 50 attempts');
            }

            $result = '';
            do {
                $result .= $this->generate();
            } while (\strlen($result) < Math::SIZEOF_UINT64_T);

            $result = $math64->fromBinary($result);
        }

        return $math64->mod($result, $umax);
    }

    private function rangeBadscaling(int $min, int $max): int
    {
        $math32 = &self::$math32;

        $n = $this->generate();
        $n = $math32->fromBinary($n);
        $n = $math32->toInt($math32->shiftRight($n, 1));
        // (__n) = (__min) + (zend_long) ((double) ( (double) (__max) - (__min) + 1.0) * ((__n) / ((__tmax) + 1.0)))
        /** @noinspection PhpCastIsUnnecessaryInspection */
        return \intval($min + \intval((\floatval($max) - $min + 1.0) * ($n / (self::PHP_MT_RAND_MAX + 1.0))));
    }

    public function nextInt(): int
    {
        $result = $this->generate();
        // @codeCoverageIgnoreStart
        // coverage runs on 64 but this stuff is for 32
        if (\strlen($result) > \PHP_INT_SIZE) {
            throw new RandomException('Generated value exceeds size of int');
        }
        // @codeCoverageIgnoreEnd

        $math64 = &self::$math64;

        $result = $math64->fromBinary($result);

        return $math64->toInt($math64->shiftRight($result, 1));
    }

    public function getBytes(int $length): string
    {
        if ($length < 1) {
            throw new ValueError(__METHOD__ . '(): Argument #1 ($length) must be greater than 0');
        }

        $retval = '';

        do {
            $retval .= $this->generate();
        } while (\strlen($retval) < $length);

        return \substr($retval, 0, $length);
    }

    public function shuffleArray(array $array): array
    {
        // handle empty
        if ($array === []) {
            return [];
        }

        $hash = \array_values($array);
        $nLeft = \count($hash);

        while (--$nLeft) {
            $rndIdx = $this->getInt(0, $nLeft);
            $tmp = $hash[$nLeft];
            $hash[$nLeft] = $hash[$rndIdx];
            $hash[$rndIdx] = $tmp;
        }

        return $hash;
    }

    public function shuffleBytes(string $bytes): string
    {
        if (\strlen($bytes) <= 1) {
            return $bytes;
        }

        $nLeft = \strlen($bytes);

        while (--$nLeft) {
            $rndIdx = $this->getInt(0, $nLeft);
            $tmp = $bytes[$nLeft];
            $bytes[$nLeft] = $bytes[$rndIdx];
            $bytes[$rndIdx] = $tmp;
        }

        return $bytes;
    }

    public function pickArrayKeys(array $array, int $num): array
    {
        if (!($this->engine instanceof CryptoSafeEngine)) {
            // Crypto-safe engines are not expected to produce reproducible sequences
            \trigger_error('pickArrayKeys() may produce results incompatible with native ext-random', \E_USER_WARNING);
        }

        if ($array === []) {
            throw new ValueError(__METHOD__ . '(): Argument #1 ($array) cannot be empty');
        }

        $numAvail = \count($array);
        $keys = \array_keys($array);

        if ($num === 1) {
            return [$keys[$this->getInt(0, $numAvail - 1)]];
        }

        if ($num <= 0 || $num > $numAvail) {
            throw new ValueError(
                __METHOD__ .
                    '(): Argument #2 ($num) must be between 1 and the number of elements in argument #1 ($array)'
            );
        }

        $retval = [];

        $i = $num;

        while ($i) {
            $idx = $this->getInt(0, $numAvail - 1);

            if (\array_key_exists($idx, $retval) === false) {
                $retval[$idx] = $keys[$idx];
                $i--;
            }
        }

        \ksort($retval, \SORT_NUMERIC); // sort by indexes

        return \array_values($retval); // remove indexes
    }

    public function __serialize(): array
    {
        return [['engine' => $this->engine]];
    }

    public function __unserialize(array $data): void
    {
        if (\count($data) !== 1 || !isset($data[0])) {
            throw new Exception(\sprintf('Invalid serialization data for %s object', self::class));
        }

        $this->initMath();

        [$fields] = $data;
        ['engine' => $this->engine] = $fields;
    }

    public function serialize(): string
    {
        \trigger_error('Serialized object will be incompatible with PHP 8.2', \E_USER_WARNING);
        return \serialize($this->__serialize());
    }

    /**
     * @param string $data
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function unserialize($data): void
    {
        $this->__unserialize(\unserialize($data));
    }

    /**
     * @return mixed
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
    public function __get(string $name)
    {
        if ($name === 'engine') {
            return $this->engine;
        }

        \trigger_error('Undefined property: ' . self::class . '::$' . $name, \E_USER_WARNING);
        return null;
    }

    /**
     * @param mixed $value
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function __set(string $name, $value): void
    {
        if ($name === 'engine') {
            throw new Error('Cannot modify readonly property Random\Randomizer::$engine');
        }

        $this->nodyn__set($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $name === 'engine';
    }
}
