<?php

/**
 * @copyright Copyright © 2022 Anton Smirnov
 * @license BSD-3-Clause https://spdx.org/licenses/BSD-3-Clause.html
 *
 * Includes adaptation of C code from the PHP Interpreter
 * @license PHP-3.01 https://spdx.org/licenses/PHP-3.01.html
 * @see https://github.com/php/php-src/blob/eff9aed/ext/random/randomizer.c
 */

declare(strict_types=1);

namespace Random;

use Closure;
use GMP;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use RuntimeException;
use Serializable;
use ValueError;

use function array_values;
use function count;
use function gmp_import;
use function gmp_init;
use function gmp_intval;
use function str_pad;
use function strlen;
use function substr;

use const GMP_LITTLE_ENDIAN;
use const MT_RAND_PHP;

/**
 * @property-read Engine $engine
 */
final class Randomizer implements Serializable
{
    private const SIZEOF_UINT_64_T = 8;
    private const SIZEOF_UINT_32_T = 4;
    private const PHP_MT_RAND_MAX = 0x7FFFFFFF;

    /** @var Engine */
    private $engine;
    /** @var GMP|null */
    private static $UINT32_MAX = null;
    /** @var GMP|null */
    private static $UINT64_MAX = null;

    public function __construct(?Engine $engine = null)
    {
        $this->initConst();

        $this->engine = $engine ?? new Engine\Secure();
    }

    private function initConst(): void
    {
        if (self::$UINT32_MAX === null) {
            self::$UINT32_MAX = gmp_init('ffff' . 'ffff', 16);
        }
        if (self::$UINT64_MAX === null) {
            self::$UINT64_MAX = gmp_init('ffff' . 'ffff' . 'ffff' . 'ffff', 16);
        }
    }

    private function generate(): string
    {
        $retval = $this->engine->generate();

        $size = strlen($retval);

        if ($size === 0) {
            throw new RuntimeException('Random number generation failed');
        } elseif ($size > self::SIZEOF_UINT_64_T) {
            $retval = substr($retval, 0, self::SIZEOF_UINT_64_T);
        }

        return $retval;
    }

    public function getInt(int ...$args): int
    {
        if ($args === []) {
            return $this->doNextInt();
        }

        return $this->doGetInt(...$args);
    }

    private function doGetInt(int $min, int $max): int
    {
        if ($max < $min) {
            throw new ValueError('Argument #2 ($max) must be greater than or equal to argument #1 ($min)');
        }

        // engine has range func
        if (
            $this->engine instanceof Secure
        ) {
            /** @psalm-suppress PossiblyInvalidFunctionCall */
            $result = Closure::bind(function (int $min, int $max): ?int {
                /** @psalm-suppress UndefinedMethod */
                return $this->range($min, $max);
            }, $this->engine, $this->engine)($min, $max);

            if ($result === null) {
                throw new RuntimeException('Random number generation failed');
            }

            return $result;
        }

        // handle MT_RAND_PHP
        /** @psalm-suppress PossiblyInvalidFunctionCall */
        if (
            $this->engine instanceof Mt19937 &&
            Closure::bind(function () {
                /** @psalm-suppress UndefinedThisPropertyFetch */
                return $this->mode === MT_RAND_PHP; // read private property
            }, $this->engine, $this->engine)()
        ) {
            return $this->rangeBadscaling($min, $max);
        }

        $umax = gmp_init($max) - gmp_init($min);

        $bit32 =
            $this->engine instanceof Mt19937 ||
            $umax > self::$UINT32_MAX;

        return gmp_intval(($bit32 ? $this->range32($umax) : $this->range64($umax)) + $min);
    }

    private function range32(GMP $umax): GMP
    {
        $result = $this->generate();
        if (strlen($result) < self::SIZEOF_UINT_32_T) {
            $result .= $this->generate();
        }

        $result = $this->importGmp32($result);

        if ($umax == self::$UINT32_MAX) {
            return $result;
        }

        $umax += 1;

        if (($umax & ($umax - 1)) == 0) {
            return $result & ($umax - 1);
        }

        $limit = self::$UINT32_MAX - (self::$UINT32_MAX % $umax) - 1;

        $count = 0;

        while ($result > $limit) {
            if (++$count > 50) {
                throw new RuntimeException('Random number generation failed');
            }

            $result = $this->generate();
            if (strlen($result) < self::SIZEOF_UINT_32_T) {
                $result .= $this->generate();
            }

            $result = $this->importGmp32($result);
        }

        return $result % $umax;
    }

    private function range64(GMP $umax): GMP
    {
        $result = $this->generate();
        if (strlen($result) < self::SIZEOF_UINT_64_T) {
            $result .= $this->generate();
        }

        $result = $this->importGmp64($result);

        if ($umax == self::$UINT64_MAX) {
            return $result;
        }

        $umax += 1;

        if (($umax & ($umax - 1)) == 0) {
            return $result & ($umax - 1);
        }

        $limit = self::$UINT64_MAX - (self::$UINT64_MAX % $umax) - 1;

        $count = 0;

        while ($result > $limit) {
            if (++$count > 50) {
                throw new RuntimeException('Random number generation failed');
            }

            $result = $this->generate();
            if (strlen($result) < self::SIZEOF_UINT_64_T) {
                $result .= $this->generate();
            }

            $result = $this->importGmp64($result);
        }

        return $result % $umax;
    }

    private function rangeBadscaling(int $min, int $max): int
    {
        $n = $this->generate();
        $n = $this->importGmp32($n);
        $n = gmp_intval($n >> 1);
        // (__n) = (__min) + (zend_long) ((double) ( (double) (__max) - (__min) + 1.0) * ((__n) / ((__tmax) + 1.0)))
        return $min + (int) (( (float)$max - $min + 1.0) * ($n / (self::PHP_MT_RAND_MAX + 1.0)));
    }

    private function doNextInt(): int
    {
        $result = $this->generate();
        $result = $this->importGmp64($result);

        return gmp_intval($result >> 1);
    }

    private function importGmp32(string $value): GMP
    {
        $value = substr($value, 0, self::SIZEOF_UINT_32_T);
        $value = str_pad($value, self::SIZEOF_UINT_32_T, "\0");
        return gmp_import($value, self::SIZEOF_UINT_32_T, GMP_LITTLE_ENDIAN | GMP_LSW_FIRST);
    }

    private function importGmp64(string $value): GMP
    {
        $value = substr($value, 0, self::SIZEOF_UINT_64_T);
        $value = str_pad($value, self::SIZEOF_UINT_64_T, "\0");
        return gmp_import($value, self::SIZEOF_UINT_64_T, GMP_LITTLE_ENDIAN | GMP_LSW_FIRST);
    }

    public function getBytes(int $length): string
    {
        if ($length < 1) {
            throw new ValueError('Argument #1 ($length) must be greater than 0');
        }

        $retval = '';

        do {
            $result = $this->engine->generate();
            if ($result === '') {
                throw new RuntimeException('Random number generation failed');
            }
            $retval .= $result;
        } while (strlen($retval) < $length);

        return substr($retval, 0, $length);
    }

    public function shuffleArray(array $array): array
    {
        // handle empty
        if ($array === []) {
            return [];
        }

        $hash = array_values($array);
        $nLeft = count($hash);

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
        if (strlen($bytes) <= 1) {
            return $bytes;
        }

        $nLeft = strlen($bytes);

        while (--$nLeft) {
            $rndIdx = $this->getInt(0, $nLeft);
            $tmp = $bytes[$nLeft];
            $bytes[$nLeft] = $bytes[$rndIdx];
            $bytes[$rndIdx] = $tmp;
        }

        return $bytes;
    }

    public function __serialize(): array
    {
        return [['engine' => $this->engine]];
    }

    public function __unserialize(array $data): void
    {
        $this->initConst();

        [$fields] = $data;
        ['engine' => $this->engine] = $fields;
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * @param string $data
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function unserialize($data): void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($name === 'engine') {
            return $this->engine;
        }

        trigger_error('Undefined property: $' . $name);
        return null;
    }
}
