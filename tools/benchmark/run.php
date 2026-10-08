<?php

declare(strict_types=1);

/**
 * Micro-benchmark: RTLY-Kit vs morilog/jalali.
 *
 *   php -d opcache.enable_cli=1 tools/benchmark/run.php [iterations] [repeats]
 *
 * Each scenario runs $iterations operations, $repeats times; the MEDIAN run is
 * reported. Results depend on hardware, PHP build and settings - compare only
 * numbers produced on the same machine in the same session.
 */

use Morilog\Jalali\Jalalian;
use RtlyKit\Calendar\Jalali;

require __DIR__ . '/vendor/autoload.php';

$iterations = (int) ($argv[1] ?? 50000);
$repeats    = (int) ($argv[2] ?? 5);

/** @var array<string, array{0: callable(int): mixed, 1: callable(int): mixed}> $scenarios */
$scenarios = [
    'Gregorian string -> Jalali Y/m/d' => [
        static fn (int $i): string => Jalali::make('2025-03-21')->format('Y/m/d'),
        static fn (int $i): string => Jalalian::fromDateTime('2025-03-21')->format('Y/m/d'),
    ],
    'Jalali y/m/d -> Gregorian Y-m-d' => [
        static fn (int $i): string => Jalali::create(1404, 1 + $i % 12, 1 + $i % 28)->toGregorian()->format('Y-m-d'),
        static fn (int $i): string => (new Jalalian(1404, 1 + $i % 12, 1 + $i % 28))->toCarbon()->format('Y-m-d'),
    ],
    'addDays + format' => [
        static fn (int $i): string => Jalali::create(1404, 1, 1)->addDays($i % 400)->format('Y/m/d'),
        static fn (int $i): string => (new Jalalian(1404, 1, 1))->addDays($i % 400)->format('Y/m/d'),
    ],
];

/** @param callable(int): mixed $fn */
function timeRun(callable $fn, int $n): float
{
    $t = hrtime(true);
    for ($i = 0; $i < $n; $i++) {
        $fn($i);
    }

    return (hrtime(true) - $t) / $n; // ns per operation
}

echo 'PHP ', PHP_VERSION, ' | opcache.enable_cli=', ini_get('opcache.enable_cli') ?: '0',
    ' | jit=', ini_get('opcache.jit') ?: 'off', ' (buffer=', ini_get('opcache.jit_buffer_size') ?: '0', ')', " | iterations=$iterations x $repeats runs (median)\n\n";
echo "| Scenario | RTLY-Kit (µs/op) | morilog/jalali (µs/op) | ratio |\n|---|---:|---:|---:|\n";

foreach ($scenarios as $name => [$ours, $theirs]) {
    $ours(0);
    $theirs(0); // warm up autoload / caches
    $a = $b = [];
    for ($r = 0; $r < $repeats; $r++) {
        $a[] = timeRun($ours, $iterations);
        $b[] = timeRun($theirs, $iterations);
    }
    sort($a);
    sort($b);
    $ma = $a[intdiv($repeats, 2)] / 1000;
    $mb = $b[intdiv($repeats, 2)] / 1000;
    printf("| %s | %.2f | %.2f | %.1fx |\n", $name, $ma, $mb, $mb / $ma);
}

echo "\nPeak memory: ", round(memory_get_peak_usage(true) / 1048576, 1), " MiB (process total, both libraries loaded)\n";
