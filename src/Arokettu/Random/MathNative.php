<?php

/**
 * @copyright Copyright © 2022 Anton Smirnov
 * @license BSD-3-Clause https://spdx.org/licenses/BSD-3-Clause.html
 *
 * @noinspection PhpMissingReturnTypeInspection
 */

declare(strict_types=1);

namespace Arokettu\Random;

/**
 * @internal
 * @psalm-suppress MoreSpecificImplementedParamType
 * @psalm-import-type TIntSize from Math
 * @extends Math<int>
 * @codeCoverageIgnore We don't care about math that was not used
 */
// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
final class MathNative extends Math
{
    /** @var int */
    private $mask;
    /** @var int */
    private $halfMask;
    /** @var TIntSize */
    private $sizeof;

    /**
     * @inheritDoc
     */
    public function __construct(int $sizeof)
    {
        $this->mask = (2 ** ($sizeof * 8)) - 1;
        $this->halfMask = (2 ** ($sizeof * 4)) - 1;
        /** @var TIntSize $sizeof */
        $this->sizeof = $sizeof;
    }

    public function shiftLeft($value, int $shift)
    {
        return ($value << $shift) & $this->mask;
    }

    public function shiftRight($value, int $shift)
    {
        return $value >> $shift;
    }

    public function add($value1, $value2)
    {
        return ($value1 + $value2) & $this->mask;
    }

    public function addInt($value1, int $value2)
    {
        return ($value1 + $value2) & $this->mask;
    }

    public function sub($value1, $value2)
    {
        return ($value1 - $value2) & $this->mask;
    }

    public function subInt($value1, int $value2)
    {
        return ($value1 - $value2) & $this->mask;
    }

    public function mul($value1, $value2)
    {
        // do some crazy stuff to avoid overflow larger than a byte
        // split like splitHiLo but do not create an array
        $halfBits = $this->sizeof * 4;

        $v1hi = $value1 >> $halfBits;
        $v2hi = $value2 >> $halfBits;
        $v1lo = $value1 & $this->halfMask;
        $v2lo = $value2 & $this->halfMask;

        $hi = $v1hi * $v2lo + $v1lo * $v2hi;
        $lo = $v1lo * $v2lo;

        return (($hi << $halfBits) + $lo) & $this->mask;
    }

    public function mulInt($value1, int $value2)
    {
        return $this->mul($value1, $value2);
    }

    public function mod($value1, $value2)
    {
        return $value1 % $value2;
    }

    public function compare($value1, $value2): int
    {
        return $value1 <=> $value2;
    }

    public function fromHex(string $value)
    {
        return \hexdec($value);
    }

    public function fromInt(int $value)
    {
        return $value & $this->mask;
    }

    public function fromBinary(string $value)
    {
        switch (\strlen($value) <=> $this->sizeof) {
            case -1:
                $value = \str_pad($value, $this->sizeof, "\0");
                break;

            case 1:
                $value = \substr($value, 0, $this->sizeof);
        }

        return $this->fromHex(\bin2hex(\strrev($value)));
    }

    public function toInt($value): int
    {
        return $value;
    }

    public function toSignedInt($value): int
    {
        if ($value & 1 << ($this->sizeof * 8 - 1)) { // sign
            $value -= 1 << $this->sizeof * 8;
        }

        return $value;
    }

    public function toBinary($value): string
    {
        $hex = \dechex($value);
        $bin = \hex2bin(\strlen($hex) % 2 ? '0' . $hex : $hex);
        return \str_pad(\strrev($bin), $this->sizeof, "\0");
    }

    /**
     * @psalm-suppress InvalidReturnType always array{int, int}
     */
    public function splitHiLo($value): array
    {
        /** @psalm-suppress InvalidReturnStatement always array{int, int} */
        return [
            $value >> ($this->sizeof * 8 / 2),
            $value & $this->halfMask,
        ];
    }
}
