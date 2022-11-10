<?php

declare(strict_types = 1);

require __DIR__ . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';

const SEED      = 12345;
const ATTEMPT   = 10000;
const ITERATION = 10000;

function bench(\Closure $procedure, int $attempt): float
{
    \sleep(1);

    $begin = \microtime(\true);

    for ($i = 0; $i < $attempt; $i++) {
        $procedure();
    }
    return \microtime(\true) - $begin;
}

function entry(string $class, string $name, \Closure $procedure, int $attempt): void
{
    printf("%s (%s)\t%f\n", $class, $name, \bench($procedure, $attempt));
}

\entry(
    \Random\Engine\Mt19937::class,
    'seed',
    static function (): void {
        new \Random\Engine\Mt19937(\SEED);
    },
    \ATTEMPT,
);
\entry(
    \Random\Engine\Mt19937::class,
    'iter',
    static function (): void {
        $engine = new \Random\Engine\Mt19937(\SEED);
        for ($i = 0; $i < \ITERATION; $i++) {
            $engine->generate();
        }
    },
    1,
);
\entry(
    \Random\Engine\Mt19937::class,
    'rndm',
    static function (): void {
        (new \Random\Randomizer(new \Random\Engine\Mt19937(\SEED)))->getInt(\PHP_INT_MIN, \PHP_INT_MAX);
    },
    \ATTEMPT,
);

\entry(
    \Random\Engine\PcgOneseq128XslRr64::class,
    'seed',
    static function (): void {
        new \Random\Engine\PcgOneseq128XslRr64(\SEED);
    },
    \ATTEMPT,
);
\entry(
    \Random\Engine\PcgOneseq128XslRr64::class,
    'iter',
    static function (): void {
        $engine = new \Random\Engine\PcgOneseq128XslRr64(\SEED);
        for ($i = 0; $i < \ITERATION; $i++) {
            $engine->generate();
        }
    },
    1,
);
\entry(
    \Random\Engine\PcgOneseq128XslRr64::class,
    'rndm',
    static function (): void {
        (new \Random\Randomizer(new \Random\Engine\PcgOneseq128XslRr64(\SEED)))->getInt(\PHP_INT_MIN, \PHP_INT_MAX);
    },
    \ATTEMPT,
);

\entry(
    \Random\Engine\Xoshiro256StarStar::class,
    'seed',
    static function (): void {
        new \Random\Engine\Xoshiro256StarStar(\SEED);
    },
    \ATTEMPT,
);
\entry(
    \Random\Engine\Xoshiro256StarStar::class,
    'iter',
    static function (): void {
        $engine = new \Random\Engine\Xoshiro256StarStar(\SEED);
        for ($i = 0; $i < \ITERATION; $i++) {
            $engine->generate();
        }
    },
    1,
);
\entry(
    \Random\Engine\Xoshiro256StarStar::class,
    'rndm',
    static function (): void {
        (new \Random\Randomizer(new \Random\Engine\Xoshiro256StarStar(\SEED)))->getInt(\PHP_INT_MIN, \PHP_INT_MAX);
    },
    \ATTEMPT,
);
