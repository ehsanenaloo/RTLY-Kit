<?php

declare(strict_types=1);

/**
 * Key-level difference between two versions of a data file, for review.
 *
 *   php tools/data/diff.php <old-file> <new-file>
 *   git show HEAD:resources/data/bank-bins.php > /tmp/old.php && php tools/data/diff.php /tmp/old.php resources/data/bank-bins.php
 *
 * Lists added, removed and changed keys with their values. Exit code 0 when the files are equal, 1 when they differ.
 */

if ($argc !== 3) {
    fwrite(STDERR, "usage: php tools/data/diff.php <old-file> <new-file>\n");
    exit(2);
}

/** @return array<array-key, mixed> */
function loadTable(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "not a file: {$path}\n");
        exit(2);
    }
    $data = require $path;
    if (! is_array($data)) {
        fwrite(STDERR, "{$path} does not return an array\n");
        exit(2);
    }

    return $data;
}

/** @param mixed $v */
function show(mixed $v): string
{
    return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$old = loadTable($argv[1]);
$new = loadTable($argv[2]);
$added = array_diff_key($new, $old);
$removed = array_diff_key($old, $new);
$changed = [];
foreach (array_intersect_key($new, $old) as $key => $value) {
    if ($value !== $old[$key]) {
        $changed[$key] = [$old[$key], $value];
    }
}

foreach ($added as $key => $value) {
    echo "+ {$key}: ", show($value), "\n";
}
foreach ($removed as $key => $value) {
    echo "- {$key}: ", show($value), "\n";
}
foreach ($changed as $key => [$was, $now]) {
    echo "~ {$key}: ", show($was), '  =>  ', show($now), "\n";
}
echo sprintf("%d added, %d removed, %d changed (%d -> %d entries)\n", count($added), count($removed), count($changed), count($old), count($new));
exit($added === [] && $removed === [] && $changed === [] ? 0 : 1);
