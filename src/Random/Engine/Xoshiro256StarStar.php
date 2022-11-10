<?php

/**
 * @copyright Copyright © 2022 Anton Smirnov
 * @license BSD-3-Clause https://spdx.org/licenses/BSD-3-Clause.html
 *
 * Includes adaptation of C code from the PHP Interpreter
 * @license PHP-3.01 https://spdx.org/licenses/PHP-3.01.html
 * @see https://github.com/php/php-src/blob/master/ext/random/engine_xoshiro256starstar.c
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */

declare(strict_types=1);

namespace Random\Engine;

use Arokettu\Random\Math;
use Arokettu\Random\NoDynamicProperties;
use Arokettu\Random\Serialization;
use Exception;
use GMP;
use Random\Engine;
use Random\RandomException;
use Serializable;
use TypeError;
use ValueError;

/**
 * @noinspection PhpComposerExtensionStubsInspection
 */
final class Xoshiro256StarStar implements Engine, Serializable
{
    use NoDynamicProperties;
    use Serialization;

    /**
     * @var GMP[]|string[]|int[]
     * @psalm-suppress PropertyNotSetInConstructor Psalm doesn't traverse several levels apparently
     */
    private $state;

    /** @var Math */
    private static $math;

    /** @var GMP|string|int */
    private static $SPLITMIX64_1;
    /** @var GMP|string|int */
    private static $SPLITMIX64_2;
    /** @var GMP|string|int */
    private static $SPLITMIX64_3;
    /** @var GMP|string|int */
    private static $JUMP1;
    /** @var GMP|string|int */
    private static $JUMP2;
    /** @var GMP|string|int */
    private static $JUMP3;
    /** @var GMP|string|int */
    private static $JUMP4;
    /** @var GMP|string|int */
    private static $JUMP_LONG1;
    /** @var GMP|string|int */
    private static $JUMP_LONG2;
    /** @var GMP|string|int */
    private static $JUMP_LONG3;
    /** @var GMP|string|int */
    private static $JUMP_LONG4;
    /** @var GMP|string|int */
    private static $ZERO;
    /** @var GMP|string|int */
    private static $ONE;

    /**
     * @param string|int|null $seed
     * @throws RandomException
     */
    public function __construct($seed = null)
    {
        $this->initMath();

        if ($seed === null) {
            try {
                do {
                    $seed = \random_bytes(32);
                } while ($seed === "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
                $this->seedString($seed);
                // @codeCoverageIgnoreStart
                // catch unreproducible
            } catch (Exception $e) {
                throw new RandomException('Failed to generate a random seed', 0, $e);
                // @codeCoverageIgnoreEnd
            }
            return;
        }

        if (\is_string($seed)) {
            if (\strlen($seed) !== 32) {
                throw new ValueError(__METHOD__ . '(): Argument #1 ($seed) must be a 32 byte (256 bit) string');
            }

            if ($seed === "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0") {
                throw new ValueError(__METHOD__ . '(): Argument #1 ($seed) must not consist entirely of NUL bytes');
            }

            $this->seedString($seed);
            return;
        }

        /** @psalm-suppress RedundantConditionGivenDocblockType we don't trust user input */
        if (\is_int($seed)) {
            $this->seedInt($seed);
            return;
        }

        throw new TypeError(
            __METHOD__ .
            '(): Argument #1 ($seed) must be of type string|int|null, ' .
            \get_debug_type($seed) . ' given'
        );
    }

    /**
     * @codeCoverageIgnore
     * @psalm-suppress TraitMethodSignatureMismatch abstract private is 8.0+
     * @psalm-suppress DocblockTypeContradiction the "constants" are initialized here
     */
    private function initMath(): void
    {
        $math = &self::$math;

        if ($math === null) {
            $math = Math::create(Math::SIZEOF_UINT64_T);

            self::$SPLITMIX64_1 = $math->fromHex('9e3779b97f4a7c15');
            self::$SPLITMIX64_2 = $math->fromHex('bf58476d1ce4e5b9');
            self::$SPLITMIX64_3 = $math->fromHex('94d049bb133111eb');

            self::$JUMP1 = $math->fromHex('180ec6d33cfd0aba');
            self::$JUMP2 = $math->fromHex('d5a61266f0c9392c');
            self::$JUMP3 = $math->fromHex('a9582618e03fc9aa');
            self::$JUMP4 = $math->fromHex('39abdc4529b1661c');

            self::$JUMP_LONG1 = $math->fromHex('76e15d3efefdcbbf');
            self::$JUMP_LONG2 = $math->fromHex('c5004e441c522fb3');
            self::$JUMP_LONG3 = $math->fromHex('77710069854ee241');
            self::$JUMP_LONG4 = $math->fromHex('39109bb02acbe635');

            self::$ZERO = $math->fromInt(0);
            self::$ONE  = $math->fromInt(1);
        }
    }

    private function seedInt(int $seed): void
    {
        $seed = self::$math->fromInt($seed);

        $this->seed256(
            $this->splitmix64($seed),
            $this->splitmix64($seed),
            $this->splitmix64($seed),
            $this->splitmix64($seed)
        );
    }

    /**
     * @param string|GMP $seed
     * @return string|GMP
     */
    private function splitmix64(&$seed)
    {
        $math = &self::$math;

        $r = $seed = $math->add($seed, self::$SPLITMIX64_1);
        $r = $math->mul(($r ^ $math->shiftRight($r, 30)), self::$SPLITMIX64_2);
        $r = $math->mul(($r ^ $math->shiftRight($r, 27)), self::$SPLITMIX64_3);
        return $r ^ $math->shiftRight($r, 31);
    }

    private function seedString(string $seed): void
    {
        $seeds = \str_split($seed, 8);

        $math = &self::$math;

        $this->seed256(
            $math->fromBinary($seeds[0]),
            $math->fromBinary($seeds[1]),
            $math->fromBinary($seeds[2]),
            $math->fromBinary($seeds[3])
        );
    }

    /**
     * @param GMP|string|int $s0
     * @param GMP|string|int $s1
     * @param GMP|string|int $s2
     * @param GMP|string|int $s3
     */
    private function seed256($s0, $s1, $s2, $s3): void
    {
        $this->state = [$s0, $s1, $s2, $s3];
    }

    public function generate(): string
    {
        $math = &self::$math;

        $r = $math->mulInt(
            $this->rotl($math->mulInt($this->state[1], 5), 7),
            9
        );
        $t = $math->shiftLeft($this->state[1], 17);

        $this->state[2] ^= $this->state[0];
        $this->state[3] ^= $this->state[1];
        $this->state[1] ^= $this->state[2];
        $this->state[0] ^= $this->state[3];

        $this->state[2] ^= $t;

        $this->state[3] = $this->rotl($this->state[3], 45);

        return $math->toBinary($r);
    }

    /**
     * @param GMP|string|int $x
     * @return GMP|string|int
     */
    private function rotl($x, int $k)
    {
        $math = &self::$math;

        return
            $math->shiftLeft($x, $k) |
            $math->shiftRight($x, 64 - $k);
    }

    public function jump(): void
    {
        $this->doJump([
            self::$JUMP1,
            self::$JUMP2,
            self::$JUMP3,
            self::$JUMP4,
        ]);
    }

    public function jumpLong(): void
    {
        $this->doJump([
            self::$JUMP_LONG1,
            self::$JUMP_LONG2,
            self::$JUMP_LONG3,
            self::$JUMP_LONG4,
        ]);
    }

    private function doJump(array $jmp): void
    {
        $s0 = $s1 = $s2 = $s3 = self::$ZERO;
        $one = self::$ONE;

        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 64; $j++) {
                if (($jmp[$i] & self::$math->shiftLeft($one, $j)) != self::$ZERO) {
                    $s0 ^= $this->state[0];
                    $s1 ^= $this->state[1];
                    $s2 ^= $this->state[2];
                    $s3 ^= $this->state[3];
                }

                $this->generate();
            }
        }

        $this->state[0] = $s0;
        $this->state[1] = $s1;
        $this->state[2] = $s2;
        $this->state[3] = $s3;
    }

    /**
     * @psalm-suppress TraitMethodSignatureMismatch abstract private is 8.0+
     */
    private function getStates(): array
    {
        $math = &self::$math;

        return [
            \bin2hex($math->toBinary($this->state[0])),
            \bin2hex($math->toBinary($this->state[1])),
            \bin2hex($math->toBinary($this->state[2])),
            \bin2hex($math->toBinary($this->state[3])),
        ];
    }

    /**
     * @psalm-suppress TraitMethodSignatureMismatch abstract private is 8.0+
     */
    private function loadStates(array $states): bool
    {
        /* Verify the expected number of elements, this implicitly ensures that no additional elements are present. */
        if (\count($states) !== 4) {
            return false;
        }
        $this->state = [];
        for ($i = 0; $i < 4; $i++) {
            $stateBin = @\hex2bin($states[$i]);
            if ($stateBin === false) {
                return false;
            }
            $this->state[$i] = self::$math->fromBinary($stateBin);
        }

        return true;
    }
}
