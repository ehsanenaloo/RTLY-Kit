<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../tools/data/DataManifest.php';

/**
 * The bundled reference data and resources/data/manifest.json must agree. A change of
 * data that does not update the manifest (entry count, SHA-256, date, sources) fails here:
 * follow "Updating the data" in resources/data/SOURCES.md.
 */
final class DataManifestTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_the_data_matches_its_manifest(): void
    {
        self::assertSame([], \DataManifest::problems(self::root()));
    }

    public function test_every_table_is_described_completely(): void
    {
        $manifest = \DataManifest::load(self::root());

        self::assertSame(1, $manifest['schema']);
        self::assertSame(
            ['bank-bins', 'iran-official-holidays', 'national-code-locations', 'sheba-banks', 'umm-al-qura'],
            $this->sorted(array_keys($manifest['tables'])),
        );
        foreach ($manifest['tables'] as $name => $entry) {
            self::assertContains($entry['status'], \DataManifest::STATUSES, $name);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $entry['verified'], $name);
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $entry['sha256'], $name);
            self::assertGreaterThan(0, $entry['entries'], $name);
            self::assertNotSame('', trim($entry['publisher']), $name);
            self::assertNotSame('', trim($entry['coverage']), $name);
        }
    }

    public function test_a_changed_file_is_detected(): void
    {
        $root = sys_get_temp_dir().'/rtly-manifest-'.bin2hex(random_bytes(4));
        mkdir($root.'/resources/data', 0o777, true);
        foreach (glob(self::root().'/resources/data/*') ?: [] as $file) {
            copy($file, $root.'/resources/data/'.basename($file));
        }

        try {
            self::assertSame([], \DataManifest::problems($root));

            $path = $root.'/resources/data/bank-bins.php';
            file_put_contents($path, str_replace('603799 =>', '603798 =>', (string) file_get_contents($path)));
            $problems = \DataManifest::problems($root);
            self::assertNotSame([], $problems);
            self::assertStringContainsString('bank-bins: the file changed', implode("\n", $problems));

            // After the intended change the update command brings the manifest up to date.
            self::assertSame(['bank-bins'], \DataManifest::update($root, '2030-01-02'));
            $manifest = \DataManifest::load($root);
            self::assertSame('2030-01-02', $manifest['tables']['bank-bins']['verified']);
            self::assertSame('2026-10-08', $manifest['tables']['sheba-banks']['verified'], 'untouched tables keep their date');
            // ...but the known answer of the changed table is still checked.
            self::assertStringContainsString('known answer for 603799', implode("\n", \DataManifest::problems($root)));
        } finally {
            foreach (glob($root.'/resources/data/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($root.'/resources/data');
            rmdir($root.'/resources');
            rmdir($root);
        }
    }

    public function test_the_hash_ignores_windows_line_endings(): void
    {
        $a = tempnam(sys_get_temp_dir(), 'rtly');
        $b = tempnam(sys_get_temp_dir(), 'rtly');
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        file_put_contents($a, "<?php\nreturn [1];\n");
        file_put_contents($b, "<?php\r\nreturn [1];\r\n");

        self::assertSame(\DataManifest::hashOf($a), \DataManifest::hashOf($b));
        unlink($a);
        unlink($b);
    }

    public function test_the_diff_tool_lists_added_removed_and_changed_keys(): void
    {
        $old = tempnam(sys_get_temp_dir(), 'rtly');
        $new = tempnam(sys_get_temp_dir(), 'rtly');
        self::assertNotFalse($old);
        self::assertNotFalse($new);
        file_put_contents($old, "<?php\nreturn [1 => 'a', 2 => 'b', 3 => 'c'];\n");
        file_put_contents($new, "<?php\nreturn [1 => 'a', 2 => 'B', 4 => 'd'];\n");

        $output = [];
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(self::root().'/tools/data/diff.php').' '.escapeshellarg($old).' '.escapeshellarg($new), $output, $code);

        self::assertSame(1, $code);
        self::assertSame(['+ 4: "d"', '- 3: "c"', '~ 2: "b"  =>  "B"', '1 added, 1 removed, 1 changed (3 -> 3 entries)'], $output);

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(self::root().'/tools/data/diff.php').' '.escapeshellarg($old).' '.escapeshellarg($old), $same, $codeSame);
        self::assertSame(0, $codeSame);
        unlink($old);
        unlink($new);
    }

    /**
     * @param list<string|int> $names
     *
     * @return list<string|int>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
