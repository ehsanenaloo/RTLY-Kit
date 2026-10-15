<?php

declare(strict_types=1);

/**
 * Builds the LOWER table of src/Text/Utf8.php from the Unicode Character Database.
 *
 *   php tools/unicode/generate-lower.php [path-or-url-of-UnicodeData.txt] [--check]
 *
 * Without an argument it reads https://www.unicode.org/Public/UCD/latest/ucd/UnicodeData.txt.
 * It prints the PHP lines of the table (simple lower-case mapping, field 13 of UnicodeData.txt,
 * code points from U+0080) as ranges [first, last, step, delta], plus the Unicode version when
 * it can be read from ReadMe.txt next to the file. With --check it compares the result with the
 * table in src/Text/Utf8.php and exits 1 when they differ.
 *
 * U+0130 is left out on purpose: Utf8::lower() maps it to "i" + U+0307 by itself.
 */

$args = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => $a !== '--check'));
$check = in_array('--check', $argv, true);
$source = $args[0] ?? 'https://www.unicode.org/Public/UCD/latest/ucd/UnicodeData.txt';

$text = @file_get_contents($source);
if ($text === false) {
    fwrite(STDERR, "Cannot read {$source}\n");
    exit(2);
}

/** @var array<int, int> $map */
$map = [];
foreach (explode("\n", $text) as $line) {
    $f = explode(';', $line);
    if (count($f) < 14 || $f[13] === '') {
        continue;
    }
    $cp = hexdec($f[0]);
    $to = hexdec($f[13]);
    if ($cp >= 0x80 && $cp !== 0x130) {
        $map[$cp] = $to;
    }
}
ksort($map);

/** @return list<array{int, int, int, int}> */
function ranges(array $map): array
{
    $out = [];
    $cps = array_keys($map);
    $i = 0;
    $n = count($cps);
    while ($i < $n) {
        $first = $cps[$i];
        $delta = $map[$first] - $first;
        $step = 1;
        $last = $first;
        // try a run with step 1, then step 2 (alternating upper/lower pairs)
        foreach ([1, 2] as $try) {
            $j = $i;
            while ($j + 1 < $n && $cps[$j + 1] === $cps[$j] + $try && $map[$cps[$j + 1]] - $cps[$j + 1] === $delta) {
                $j++;
            }
            if ($j > $i) {
                $step = $try;
                $last = $cps[$j];
                $i = $j;
                break;
            }
        }
        $out[] = [$first, $last, $step, $delta];
        $i++;
    }

    return $out;
}

$table = ranges($map);
$expand = static function (array $rows): array {
    $m = [];
    foreach ($rows as [$first, $last, $step, $delta]) {
        for ($cp = $first; $cp <= $last; $cp += $step) {
            $m[$cp] = $cp + $delta;
        }
    }
    ksort($m);

    return $m;
};
if ($expand($table) !== $map) {
    fwrite(STDERR, "Internal error: the ranges do not rebuild the mapping.\n");
    exit(2);
}

if ($check) {
    $php = (string) file_get_contents(dirname(__DIR__, 2).'/src/Text/Utf8.php');
    if (preg_match_all('/^\s*\[(\d+),(\d+),(\d+),(-?\d+)\],$/m', $php, $m, PREG_SET_ORDER) < 1) {
        fwrite(STDERR, "Cannot find the LOWER table in src/Text/Utf8.php\n");
        exit(2);
    }
    $current = array_map(static fn (array $r): array => [(int) $r[1], (int) $r[2], (int) $r[3], (int) $r[4]], $m);
    $now = $expand($current);
    $added = array_diff_key($map, $now);
    $removed = array_diff_key($now, $map);
    $changed = array_filter($map, static fn (int $to, int $cp): bool => isset($now[$cp]) && $now[$cp] !== $to, ARRAY_FILTER_USE_BOTH);
    printf("%d mappings in the current table, %d in the data: %d added, %d removed, %d changed\n", count($now), count($map), count($added), count($removed), count($changed));
    foreach (array_slice($added, 0, 40, true) as $cp => $to) {
        printf("  + U+%04X -> U+%04X\n", $cp, $to);
    }
    exit($added === [] && $removed === [] && $changed === [] ? 0 : 1);
}

$version = 'unknown';
$readme = @file_get_contents(dirname($source).'/../ReadMe.txt') ?: @file_get_contents(preg_replace('#/ucd/UnicodeData\.txt$#', '/ReadMe.txt', $source) ?? '');
if (is_string($readme) && preg_match('/version\s+(\d+\.\d+(?:\.\d+)?)/i', $readme, $v) === 1) {
    $version = $v[1];
}
fwrite(STDERR, 'Unicode version: '.$version.', '.count($map).' mappings, '.count($table)." ranges\n");
foreach ($table as [$first, $last, $step, $delta]) {
    echo "        [{$first},{$last},{$step},{$delta}],\n";
}
