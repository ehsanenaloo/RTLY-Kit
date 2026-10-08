<?php

declare(strict_types=1);

/*
 * Smoke test for the "needs only PHP" promise. CI runs it after
 * `composer install --no-dev` on a PHP build without mbstring: it loads the
 * package the way an application would and checks a few known answers.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use RtlyKit\Holiday\IranHolidays;
use RtlyKit\Prayer\PrayerTimes;
use RtlyKit\Text\Slugify;

use function RtlyKit\is_national_code;
use function RtlyKit\jdate;
use function RtlyKit\number_to_words;
use function RtlyKit\to_persian;

$checks = [
    'jalali date' => jdate('2026-03-21')->format('Y/m/d') === '1405/01/01',
    'persian digits' => to_persian(1405) === '۱۴۰۵',
    'persian words' => number_to_words(1234) === 'یک هزار و دویست و سی و چهار',
    'arabic words' => number_to_words(1234, 'ar') === 'ألف ومئتان وأربعة وثلاثون',
    'national code' => is_national_code('0499370899') === true,
    'slug' => Slugify::make('سلام دنیا') !== '',
    'holiday' => IranHolidays::isHoliday(1405, 1, 1) === true,
    'prayer times' => is_string(PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)->getTimes(new DateTimeImmutable('2026-06-01'))['fajr']),
];

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => ! $ok));

echo 'mbstring loaded: ' . (extension_loaded('mbstring') ? 'yes' : 'no') . PHP_EOL;

if (getenv('RTLY_EXPECT_NO_MBSTRING') === '1' && extension_loaded('mbstring')) {
    fwrite(STDERR, 'The minimal job must run without mbstring, but it is loaded.' . PHP_EOL);
    exit(1);
}

if ($failed !== []) {
    fwrite(STDERR, 'Smoke test failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Smoke test passed (' . count($checks) . ' checks)' . PHP_EOL;
