<?php

/**
 * @copyright Copyright © 2022 Anton Smirnov
 * @license BSD-3-Clause https://spdx.org/licenses/BSD-3-Clause.html
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */

declare(strict_types=1);

namespace Arokettu\Random;

/**
 * @template TValueType
 * @psalm-type TIntSize int<4, 16>
 * @codeCoverageIgnore We don't care about math that was not used
 */
abstract class Math
{
    public const SIZEOF_UINT32_T = 4;
    public const SIZEOF_UINT64_T = 8;
    public const SIZEOF_UINT128_T = 16;

    /** @var Math[] */
    private static $maths = [];

    /**
     * @param TIntSize $sizeof
     */
    public static function create(int $sizeof): self
    {
        return self::$maths[$sizeof] ?? self::$maths[$sizeof] = self::build($sizeof);
    }

    /**
     * @param TIntSize $sizeof
     */
    protected static function build(int $sizeof): self
    {
        // only less because PHP int is always signed
        if ($sizeof < \PHP_INT_SIZE) {
            return new MathNative($sizeof);
        }

        if (\extension_loaded('gmp')) {
            return new MathGMP($sizeof);
        }

        return new MathUnsigned($sizeof);
    }

    /**
     * @param TIntSize $sizeof
     */
    abstract protected function __construct(int $sizeof);

    /**
     * @param TValueType $value
     * @param int $shift
     * @return TValueType
     */
    abstract public function shiftLeft($value, int $shift);

    /**
     * @param TValueType $value
     * @param int $shift
     * @return TValueType
     */
    abstract public function shiftRight($value, int $shift);

    /**
     * @param TValueType $value1
     * @param TValueType $value2
     * @return TValueType
     */
    abstract public function add($value1, $value2);

    /**
     * @param TValueType $value1
     * @param int $value2
     * @return TValueType
     */
    abstract public function addInt($value1, int $value2);

    /**
     * @param TValueType $value1
     * @param TValueType $value2
     * @return TValueType
     */
    abstract public function sub($value1, $value2);

    /**
     * @param TValueType $value1
     * @param int $value2
     * @return TValueType
     */
    abstract public function subInt($value1, int $value2);

    /**
     * @param TValueType $value1
     * @param TValueType $value2
     * @return TValueType
     */
    abstract public function mul($value1, $value2);

    /**
     * @param TValueType $value1
     * @param int $value2
     * @return TValueType
     */
    abstract public function mulInt($value1, int $value2);

    /**
     * @param TValueType $value1
     * @param TValueType $value2
     * @return TValueType
     */
    abstract public function mod($value1, $value2);

    /**
     * @param TValueType $value1
     * @param TValueType $value2
     * @return int
     */
    abstract public function compare($value1, $value2): int;

    /**
     * @param string $value
     * @return TValueType
     */
    abstract public function fromHex(string $value);

    /**
     * @param int $value
     * @return TValueType
     */
    abstract public function fromInt(int $value);

    /**
     * @param string $value
     * @return TValueType
     */
    abstract public function fromBinary(string $value);

    /**
     * @param TValueType $value
     */
    abstract public function toInt($value): int;

    /**
     * @param TValueType $value
     */
    abstract public function toSignedInt($value): int;

    /**
     * @param TValueType $value
     */
    abstract public function toBinary($value): string;

    /**
     * @param TValueType $value
     * @return list<TValueType>
     */
    abstract public function splitHiLo($value): array;
}
