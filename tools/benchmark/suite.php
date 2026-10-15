<?php

declare(strict_types=1);

/**
 * Package-wide benchmark of RTLY-Kit (no comparison library needed).
 *
 *   php -d opcache.enable_cli=1 tools/benchmark/suite.php [iterations] [repeats] [--json]
 *
 * Run it from a copy of the sources inside the container, not from a Windows bind mount, and
 * compare only numbers produced on the same machine in the same session.
 *
 * For every scenario:
 *  - the result is checked against a known answer BEFORE and AFTER timing, never inside it;
 *  - "cold" is the first call in the process, "warm" is the median of the timed runs;
 *  - memory is the peak growth over a batch of operations (memory_reset_peak_usage).
 *
 * Scenarios with "limit" in the name feed inputs at the documented size limits.
 */

use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;
use RtlyKit\Prayer\PrayerTimes;
use RtlyKit\Text\Detector;
use RtlyKit\Text\Normalizer;
use RtlyKit\Text\Slugify;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\Mobile;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\PostalCode;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;

require __DIR__ . '/../../vendor/autoload.php';

$json = in_array('--json', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => $a !== '--json'));
$iterations = (int) ($args[0] ?? 20000);
$repeats = (int) ($args[1] ?? 5);

$utc = new DateTimeZone('UTC');
$persian = str_repeat('سلام دنیا، این یک متن آزمایشی است. ', 30);
$limitText = str_repeat('سلام ', 800); // 4800 bytes of Persian: well above the Slugify limit, so cut below
$slugLimit = mb_strcut($limitText, 0, 4096, 'UTF-8');
$longDigits = str_repeat('1', 4096);

/**
 * name => [callable(int): mixed, expected result of call 0 (compared with ===), group]
 *
 * @var array<string, array{0: callable(int): mixed, 1: mixed, 2: string}> $scenarios
 */
$scenarios = [
    'Jalali::make(ISO string) + format' => [static fn (int $i): string => Jalali::make('2025-03-21', $GLOBALS['utc'])->format('Y/m/d'), '1404/01/01', 'calendar'],
    'Jalali::make(Persian-digit text)' => [static fn (int $i): string => Jalali::make('۱۴۰۴/۰۱/۰۱', $GLOBALS['utc'])->format('Y/m/d'), '1404/01/01', 'calendar'],
    'Jalali::createFromFormat' => [static fn (int $i): string => Jalali::createFromFormat('Y/m/d H:i', '1404/01/01 10:30', $GLOBALS['utc'])->format('Y/m/d H:i'), '1404/01/01 10:30', 'calendar'],
    'Jalali::create + toGregorian' => [static fn (int $i): string => Jalali::create(1404, 1, 1, 0, 0, 0, $GLOBALS['utc'])->toGregorian()->format('Y-m-d'), '2025-03-21', 'calendar'],
    'Jalali addMonths + diffInDays' => [static fn (int $i): int => Jalali::create(1404, 1, 1, 0, 0, 0, $GLOBALS['utc'])->diffInDays(Jalali::create(1404, 1, 1, 0, 0, 0, $GLOBALS['utc'])->addMonths(3)), 93, 'calendar'],
    'Hijri::create + toGregorian' => [static fn (int $i): string => Hijri::create(1446, 9, 1, 0, 0, 0, $GLOBALS['utc'])->toGregorian()->format('Y-m-d'), '2025-03-01', 'calendar'],
    'Hebrew::create + toGregorian' => [static fn (int $i): string => Hebrew::create(5785, 1, 1, 0, 0, 0, $GLOBALS['utc'])->toGregorian()->format('Y-m-d'), '2024-10-03', 'calendar'],
    'Jalali serialize + unserialize' => [static fn (int $i): string => unserialize(serialize(Jalali::create(1404, 1, 1, 12, 0, 0, $GLOBALS['utc'])))->format('Y/m/d H:i'), '1404/01/01 12:00', 'calendar'],
    'Jalali::make, text at the 256-byte limit' => [static fn (int $i): string => Jalali::make('1404/01/01' . str_repeat(' ', 246), $GLOBALS['utc'])->format('Y/m/d'), '1404/01/01', 'calendar'],
    'Jalali::make, invalid text' => [static function (int $i): string {
        try {
            Jalali::make('not a date at all', $GLOBALS['utc']);
        } catch (Throwable $e) {
            return get_class($e);
        }

        return 'no exception';
    }, 'RtlyKit\Exceptions\InvalidDateException', 'calendar'],

    'NationalCode::isValid' => [static fn (int $i): bool => NationalCode::isValid('0013542419'), true, 'validators'],
    'NationalCode::validate, wrong checksum' => [static fn (int $i): array => NationalCode::validate('0013542418')->errors(), ['invalid_checksum'], 'validators'],
    'NationalCode::validate, 4096-byte limit' => [static fn (int $i): array => NationalCode::validate($GLOBALS['longDigits'])->errors(), ['invalid_format'], 'validators'],
    'Sheba::isValid' => [static fn (int $i): bool => Sheba::isValid('IR270170000000100324200001'), true, 'validators'],
    'BankCard::isValid' => [static fn (int $i): bool => BankCard::isValid('6037991899071116'), true, 'validators'],
    'Mobile::isValid' => [static fn (int $i): bool => Mobile::isValid('09121234567'), true, 'validators'],
    'PostalCode::isValid' => [static fn (int $i): bool => PostalCode::isValid('1593715416'), true, 'validators'],
    'VehiclePlate::isValid' => [static fn (int $i): bool => VehiclePlate::isValid('12ب345-67'), true, 'validators'],

    'Format::withSeparator' => [static fn (int $i): string => Format::withSeparator('1234567'), "۱٬۲۳۴٬۵۶۷", 'numbers'],
    'NumberToWords::convert (fa)' => [static fn (int $i): string => NumberToWords::convert(21, 'fa'), 'بیست و یک', 'numbers'],
    'NumberToWords convert + fromWords, 21 digits' => [static fn (int $i): int|string => NumberToWords::fromWords(NumberToWords::convert('123456789012345678901', 'fa')), '123456789012345678901', 'numbers'],

    'Normalizer::normalize, 1 KB text' => [static fn (int $i): bool => Normalizer::normalize($GLOBALS['persian']) !== '', true, 'text'],
    'Normalizer::normalize, Arabic letters' => [static fn (int $i): string => Normalizer::normalize('كي'), 'کی', 'text'],
    'Slugify::make, short title' => [static fn (int $i): string => Slugify::make('سلام دنیا'), 'سلام-دنیا', 'text'],
    'Slugify::make, 4096-byte limit' => [static fn (int $i): bool => Slugify::make($GLOBALS['slugLimit']) !== '', true, 'text'],
    'Detector::direction' => [static fn (int $i): string => Detector::direction('سلام'), 'rtl', 'text'],

    'HolidayCalendar::all, official year (warm)' => [static fn (int $i): bool => isset($GLOBALS['calendar']->all(1404)['1404/01/01']), true, 'holidays'],
    'HolidayCalendar::all, estimated year (warm)' => [static fn (int $i): bool => count($GLOBALS['calendar']->all(1450)) > 5, true, 'holidays'],
    'HolidayCalendar::all, official year (new instance)' => [static fn (int $i): bool => isset(HolidayCalendar::default()->all(1404)['1404/01/01']), true, 'holidays'],
    'HolidayCalendar::all, 12 month-start overrides' => [static fn (int $i): bool => count($GLOBALS['overridden']->all(1405)) > 5, true, 'holidays'],

    'PrayerTimes::getTimes' => [static fn (int $i): bool => isset((new PrayerTimes(35.6892, 51.389, 'Tehran', PrayerTimes::ASR_STANDARD, $GLOBALS['utc']))->getTimes(new DateTimeImmutable('2025-03-21', $GLOBALS['utc']))['fajr']), true, 'prayer'],
];

$calendar = HolidayCalendar::default();
$overridden = HolidayCalendar::default();
for ($m = 1; $m <= 12; $m++) {
    $overridden = $overridden->withHijriMonthStart(1447, $m, Hijri::create(1447, $m, 1)->toGregorian());
}

/** @return float ns per operation */
function timeRun(callable $fn, int $n): float
{
    $t = hrtime(true);
    for ($i = 0; $i < $n; $i++) {
        $fn($i);
    }

    return (hrtime(true) - $t) / $n;
}

$rows = [];
$failed = false;
foreach ($scenarios as $name => [$fn, $expected, $group]) {
    $t = hrtime(true);
    $first = $fn(0);
    $cold = (hrtime(true) - $t) / 1000; // µs
    if ($first !== $expected) {
        fwrite(STDERR, "WRONG RESULT before timing: {$name}\n");
        $failed = true;
        continue;
    }

    // estimated cost of one call decides how many iterations are worth running
    $per = max(1, (int) round($iterations / max(1, (int) ceil(timeRun($fn, 20) / 20000))));
    $runs = [];
    for ($r = 0; $r < $repeats; $r++) {
        $runs[] = timeRun($fn, $per);
    }
    sort($runs);
    $warm = $runs[intdiv($repeats, 2)] / 1000; // µs

    gc_collect_cycles();
    memory_reset_peak_usage();
    $base = memory_get_usage();
    for ($i = 0; $i < 200; $i++) {
        $fn($i);
    }
    $memory = max(0, memory_get_peak_usage() - $base);

    if ($fn($per) !== $expected) {
        fwrite(STDERR, "WRONG RESULT after timing: {$name}\n");
        $failed = true;
    }

    $rows[] = ['group' => $group, 'name' => $name, 'cold_us' => $cold, 'warm_us' => $warm, 'iterations' => $per, 'peak_growth_bytes' => $memory];
}

$env = [
    'php' => PHP_VERSION,
    'os' => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
    'sapi' => PHP_SAPI,
    'opcache.enable_cli' => ini_get('opcache.enable_cli') ?: '0',
    'opcache.jit' => ini_get('opcache.jit') ?: 'off',
    'extensions' => implode(',', array_filter(get_loaded_extensions(), static fn (string $e): bool => in_array($e, ['mbstring', 'intl', 'pcov', 'xdebug', 'Zend OPcache', 'pcre', 'bcmath', 'gmp'], true))),
    'iterations' => $iterations,
    'repeats' => $repeats,
    'rtly-kit' => (string) (json_decode((string) @file_get_contents(__DIR__ . '/../../composer.json'), true)['version'] ?? 'working tree'),
    'date' => gmdate('Y-m-d'),
];

if ($json) {
    echo json_encode(['environment' => $env, 'results' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit($failed ? 1 : 0);
}

echo '```text', "\n";
foreach ($env as $k => $v) {
    echo str_pad($k, 20), $v, "\n";
}
echo '```', "\n\n| Group | Scenario | Cold first call (µs) | Warm (µs/op) | Peak growth over 200 ops (KiB) |\n|---|---|---:|---:|---:|\n";
foreach ($rows as $r) {
    printf("| %s | %s | %.1f | %.2f | %.1f |\n", $r['group'], $r['name'], $r['cold_us'], $r['warm_us'], $r['peak_growth_bytes'] / 1024);
}
exit($failed ? 1 : 0);
