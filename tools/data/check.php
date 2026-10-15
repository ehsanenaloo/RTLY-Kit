<?php

declare(strict_types=1);

/**
 * Checks the reference data against resources/data/manifest.json.
 *
 *   php tools/data/check.php                          check; exit 1 and list the problems
 *   php tools/data/check.php --update                 after an intended data change: rewrite entry counts and hashes
 *   php tools/data/check.php --update --verified=2026-11-02
 *                                                     also set the verification date of the tables that changed
 *
 * The procedure for a data update is in resources/data/SOURCES.md ("Updating the data").
 */

require __DIR__.'/DataManifest.php';

$root = dirname(__DIR__, 2);
$update = in_array('--update', $argv, true);
$verified = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--verified=')) {
        $verified = substr($arg, 11);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $verified) !== 1) {
            fwrite(STDERR, "--verified needs a date like 2026-11-02\n");
            exit(2);
        }
    }
}

if ($update) {
    $changed = DataManifest::update($root, $verified);
    echo $changed === [] ? "Nothing changed.\n" : 'Updated: '.implode(', ', $changed)."\n";
}

$problems = DataManifest::problems($root);
if ($problems === []) {
    echo 'Reference data matches the manifest (', count(DataManifest::load($root)['tables']), " tables).\n";
    exit(0);
}
foreach ($problems as $line) {
    fwrite(STDERR, "- {$line}\n");
}
exit(1);
