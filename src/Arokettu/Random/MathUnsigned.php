<?php

/**
 * @copyright Copyright © 2022 Anton Smirnov
 * @license BSD-3-Clause https://spdx.org/licenses/BSD-3-Clause.html
 *
 * @noinspection PhpMissingReturnTypeInspection
 */

declare(strict_types=1);

namespace Arokettu\Random;

use function Arokettu\Unsigned\add;
use function Arokettu\Unsigned\add_int;
use function Arokettu\Unsigned\compare;
use function Arokettu\Unsigned\from_hex;
use function Arokettu\Unsigned\from_int;
use function Arokettu\Unsigned\mod;
use function Arokettu\Unsigned\mul;
use function Arokettu\Unsigned\mul_int;
use function Arokettu\Unsigned\shift_left;
use function Arokettu\Unsigned\shift_right;
use function Arokettu\Unsigned\sub;
use function Arokettu\Unsigned\sub_int;
use function Arokettu\Unsigned\to_int;
use function Arokettu\Unsigned\to_signed_int;

/**
 * @internal
 * @psalm-suppress MoreSpecificImplementedParamType
 * @codeCoverageIgnore We don't care about math that was not used
 * @extends Math<string>
 */
// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
final class MathUnsigned extends Math
{
    /** @var int */
    private $sizeof;

    public function __construct(int $sizeof)
    {
        $this->sizeof = $sizeof;
    }

    public function shiftLeft($value, int $shift)
    {
        return shift_left($value, $shift);
    }

    public function shiftRight($value, int $shift)
    {
        return shift_right($value, $shift);
    }

    public function add($value1, $value2)
    {
        return add($value1, $value2);
    }

    public function addInt($value1, int $value2)
    {
        return add_int($value1, $value2);
    }

    public function sub($value1, $value2)
    {
        return sub($value1, $value2);
    }

    public function subInt($value1, int $value2)
    {
        return sub_int($value1, $value2);
    }

    public function mul($value1, $value2)
    {
        return mul($value1, $value2);
    }

    public function mulInt($value1, int $value2)
    {
        return mul_int($value1, $value2);
    }

    public function mod($value1, $value2)
    {
        return mod($value1, $value2);
    }

    public function compare($value1, $value2): int
    {
        return compare($value1, $value2);
    }

    public function fromHex(string $value)
    {
        return from_hex($value, $this->sizeof);
    }

    public function fromInt(int $value)
    {
        return from_int($value, $this->sizeof);
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

        return $value;
    }

    public function toInt($value): int
    {
        return to_int($value);
    }

    public function toSignedInt($value): int
    {
        return to_signed_int($value);
    }

    public function toBinary($value): string
    {
        return $value;
    }

    public function splitHiLo($value): array
    {
        /** @psalm-suppress PossiblyInvalidArrayAccess */
        [$lo, $hi] = \str_split($value, $this->sizeof >> 1);
        return [$hi, $lo];
    }
}
