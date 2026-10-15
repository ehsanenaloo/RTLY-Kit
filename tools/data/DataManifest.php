<?php

declare(strict_types=1);

/**
 * Checks the bundled reference data against resources/data/manifest.json.
 *
 * The manifest records, per table, where the data comes from, when it was last verified, what it
 * covers and whether it is official, reported or community data, plus its entry count and a
 * SHA-256 of the file. A data change that does not update the manifest fails the check, so a
 * change of data is always a conscious, reviewable step. Used by tools/data/check.php and by
 * tests/Unit/Validation/DataManifestTest.php. Development tooling: the package never reads it.
 */
final class DataManifest
{
    public const STATUSES = ['official', 'official_and_reported', 'official_and_community', 'official_and_generated', 'community'];

    public static function manifestPath(string $root): string
    {
        return $root.'/resources/data/manifest.json';
    }

    /** @return array{schema: int, tables: array<string, array<string, mixed>>} */
    public static function load(string $root): array
    {
        $json = (string) file_get_contents(self::manifestPath($root));
        /** @var array{schema: int, tables: array<string, array<string, mixed>>} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /** SHA-256 of the file with Windows line endings turned into Unix ones, so a checkout setting cannot change it. */
    public static function hashOf(string $path): string
    {
        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($path)));
    }

    /**
     * @return array{entries: int, sha256: string, problems: list<string>}
     */
    public static function inspect(string $root, string $name, array $entry): array
    {
        $path = $root.'/resources/data/'.$entry['file'];
        $problems = [];
        if (! is_file($path)) {
            return ['entries' => 0, 'sha256' => '', 'problems' => ["{$name}: the file {$entry['file']} does not exist"]];
        }

        /** @var mixed $data */
        $data = require $path;
        if (! is_array($data)) {
            return ['entries' => 0, 'sha256' => self::hashOf($path), 'problems' => ["{$name}: the file does not return an array"]];
        }

        $entries = match ($name) {
            'umm-al-qura' => is_array($data['months'] ?? null) ? count($data['months']) : 0,
            default => count($data),
        };

        array_push($problems, ...self::invariants($name, $data));
        array_push($problems, ...self::knownAnswers($name, $data, $entry));

        return ['entries' => $entries, 'sha256' => self::hashOf($path), 'problems' => $problems];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    private static function invariants(string $name, array $data): array
    {
        $p = [];
        switch ($name) {
            case 'bank-bins':
                foreach ($data as $bin => $bank) {
                    if (! is_int($bin) || $bin < 100000 || $bin > 999999) {
                        $p[] = "bank-bins: the key {$bin} is not a 6-digit BIN";
                    }
                    if (! is_string($bank) || trim($bank) === '') {
                        $p[] = "bank-bins: the bank of {$bin} is empty";
                    }
                }
                break;
            case 'sheba-banks':
                foreach ($data as $code => $bank) {
                    if (preg_match('/^\d{3}$/D', (string) $code) !== 1) {
                        $p[] = "sheba-banks: the key {$code} is not a 3-digit code";
                    }
                    if (! is_string($bank) || trim($bank) === '') {
                        $p[] = "sheba-banks: the bank of {$code} is empty";
                    }
                }
                break;
            case 'national-code-locations':
                foreach ($data as $prefix => $place) {
                    if (! is_int($prefix) || $prefix < 1 || $prefix > 999) {
                        $p[] = "national-code-locations: the key {$prefix} is not a prefix from 001 to 999";
                    }
                    if (! is_array($place) || ! is_string($place['province'] ?? null) || ! is_string($place['city'] ?? null) || trim((string) $place['province']) === '' || trim((string) $place['city']) === '') {
                        $p[] = "national-code-locations: the place of {$prefix} needs a province and a city";
                    }
                }
                break;
            case 'iran-official-holidays':
                foreach ($data as $year => $row) {
                    if (! is_int($year) || ! is_array($row) || ! is_string($row['status'] ?? null) || ! is_array($row['sources'] ?? null) || $row['sources'] === [] || ! is_array($row['holidays'] ?? null)) {
                        $p[] = "iran-official-holidays: the year {$year} needs status, sources and holidays";
                        continue;
                    }
                    foreach ($row['holidays'] as $key => $titles) {
                        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/D', (string) $key, $m) !== 1 || (int) $m[1] < 1 || (int) $m[1] > 12 || (int) $m[2] < 1 || (int) $m[2] > 31) {
                            $p[] = "iran-official-holidays: {$year} has the bad day key {$key}";
                        }
                        if (! is_array($titles) || $titles === [] || array_filter($titles, static fn ($t): bool => ! is_string($t) || trim($t) === '') !== []) {
                            $p[] = "iran-official-holidays: {$year} {$key} needs a list of titles";
                        }
                    }
                }
                break;
            case 'umm-al-qura':
                $first = $data['first_year'] ?? null;
                $last = $data['last_year'] ?? null;
                if (! is_int($first) || ! is_int($last) || $last <= $first) {
                    $p[] = 'umm-al-qura: first_year and last_year are not a range';
                } elseif (! is_array($data['months'] ?? null) || count($data['months']) !== $last - $first + 1) {
                    $p[] = 'umm-al-qura: months has not one entry per year';
                }
                if (! is_int($data['start_jdn'] ?? null)) {
                    $p[] = 'umm-al-qura: start_jdn is missing';
                }
                break;
            default:
                $p[] = "{$name}: no check is defined for this table";
        }

        return $p;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed>    $entry
     *
     * @return list<string>
     */
    private static function knownAnswers(string $name, array $data, array $entry): array
    {
        $p = [];
        foreach (($entry['known_answers'] ?? []) as $answer) {
            [$key, $expected] = $answer;
            $actual = $name === 'umm-al-qura' ? ($data[$key] ?? null) : ($data[is_numeric($key) && $name !== 'sheba-banks' ? (int) $key : $key] ?? null);
            if ($name === 'iran-official-holidays' && is_array($actual)) {
                $actual = $actual['status'] ?? null;
            }
            if ($actual !== $expected) {
                $p[] = "{$name}: known answer for {$key} is ".json_encode($actual, JSON_UNESCAPED_UNICODE).', expected '.json_encode($expected, JSON_UNESCAPED_UNICODE);
            }
        }

        return $p;
    }

    /**
     * Everything that is wrong or out of date, one line each. Empty when the data matches its manifest.
     *
     * @return list<string>
     */
    public static function problems(string $root): array
    {
        $manifest = self::load($root);
        $out = [];
        if (($manifest['schema'] ?? null) !== 1) {
            $out[] = 'manifest: unknown schema';
        }

        $seen = [];
        foreach ($manifest['tables'] as $name => $entry) {
            foreach (['file', 'what', 'status', 'publisher', 'verified', 'coverage', 'sources_section'] as $field) {
                if (! is_string($entry[$field] ?? null) || trim((string) $entry[$field]) === '') {
                    $out[] = "{$name}: the field {$field} is missing";
                }
            }
            if (! in_array($entry['status'] ?? null, self::STATUSES, true)) {
                $out[] = "{$name}: the status must be one of ".implode(', ', self::STATUSES);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) ($entry['verified'] ?? '')) !== 1 || strtotime((string) $entry['verified']) === false) {
                $out[] = "{$name}: verified is not a date (YYYY-MM-DD)";
            }

            $sources = (string) @file_get_contents($root.'/resources/data/SOURCES.md');
            if (! str_contains($sources, '## '.(string) ($entry['sources_section'] ?? '###'))) {
                $out[] = "{$name}: SOURCES.md has no section \"".($entry['sources_section'] ?? '').'"';
            }

            $seen[(string) ($entry['file'] ?? '')] = true;
            $r = self::inspect($root, $name, $entry);
            array_push($out, ...$r['problems']);
            if ($r['entries'] !== ($entry['entries'] ?? null)) {
                $out[] = "{$name}: the file has {$r['entries']} entries, the manifest says ".json_encode($entry['entries'] ?? null);
            }
            if ($r['sha256'] !== ($entry['sha256'] ?? null)) {
                $out[] = "{$name}: the file changed since the manifest was written (run php tools/data/check.php --update, then review the diff and the sources)";
            }
        }

        foreach (glob($root.'/resources/data/*.php') ?: [] as $file) {
            if (! isset($seen[basename($file)])) {
                $out[] = basename($file).': the data file has no entry in the manifest';
            }
        }

        return $out;
    }

    /**
     * Rewrites entry counts and hashes from the files. `verified` changes only when a date is given.
     *
     * @return list<string> the tables whose numbers changed
     */
    public static function update(string $root, ?string $verified): array
    {
        $manifest = self::load($root);
        $changed = [];
        foreach ($manifest['tables'] as $name => $entry) {
            $r = self::inspect($root, $name, $entry);
            if ($r['entries'] !== $entry['entries'] || $r['sha256'] !== $entry['sha256']) {
                $changed[] = $name;
            }
            $manifest['tables'][$name]['entries'] = $r['entries'];
            $manifest['tables'][$name]['sha256'] = $r['sha256'];
            if ($verified !== null && in_array($name, $changed, true)) {
                $manifest['tables'][$name]['verified'] = $verified;
            }
        }
        file_put_contents(self::manifestPath($root), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return $changed;
    }
}
