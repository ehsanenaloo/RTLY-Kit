<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Prayer\PrayerTimes;

/**
 * External reference check of the twilight/sunrise/sunset maths.
 *
 * Reference values: https://www.timeanddate.com/sun/<country>/<city>?month=M&year=2025
 * ("Astronomical Twilight" start/end = sun 18 degrees below the horizon,
 * "Sunrise"/"Sunset"), read on 2026-10-08. The Karachi method uses 18 degrees for
 * both fajr and isha, so fajr/isha must equal astronomical twilight start/end.
 *
 * This verifies the astronomy only. It does NOT verify any authority's own
 * published timetable or its other angles (e.g. Tehran 17.7/14).
 * Tolerance: 1 minute (reference is minute-truncated; we round to nearest).
 */
final class PrayerTimesTwilightReferenceTest extends TestCase
{
    /**
     * @return iterable<string, array{0: float, 1: float, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}>
     */
    public static function references(): iterable
    {
        // lat, lon, tz, date, twilightStart, twilightEnd, sunrise, sunset
        yield 'Tehran 03-21' => [35.6892, 51.3890, 'Asia/Tehran', '2025-03-21', '04:41', '19:42', '06:06', '18:16'];
        yield 'Tehran 06-21' => [35.6892, 51.3890, 'Asia/Tehran', '2025-06-21', '02:59', '21:12', '04:48', '19:23'];
        yield 'Tehran 09-23' => [35.6892, 51.3890, 'Asia/Tehran', '2025-09-23', '04:27', '19:24', '05:53', '17:59'];
        yield 'Tehran 12-21' => [35.6892, 51.3890, 'Asia/Tehran', '2025-12-21', '05:38', '18:26', '07:10', '16:54'];
        yield 'Istanbul 06-21' => [41.0082, 28.9784, 'Europe/Istanbul', '2025-06-21', '03:24', '22:47', '05:32', '20:39'];
        yield 'Istanbul 12-21' => [41.0082, 28.9784, 'Europe/Istanbul', '2025-12-21', '06:46', '19:18', '08:25', '17:38'];
        yield 'Jakarta 03-21' => [-6.2088, 106.8456, 'Asia/Jakarta', '2025-03-21', '04:47', '19:12', '05:56', '18:02'];
        yield 'Cairo 06-21 (DST)' => [30.0444, 31.2357, 'Africa/Cairo', '2025-06-21', '04:17', '21:36', '05:54', '19:59'];
        yield 'Cairo 12-21' => [30.0444, 31.2357, 'Africa/Cairo', '2025-12-21', '05:21', '18:25', '06:46', '16:59'];
        yield 'Karachi 06-21' => [24.8607, 67.0011, 'Asia/Karachi', '2025-06-21', '04:14', '20:53', '05:43', '19:24'];
        yield 'Karachi 12-21' => [24.8607, 67.0011, 'Asia/Karachi', '2025-12-21', '05:50', '19:09', '07:12', '17:47'];
    }

    #[DataProvider('references')]
    public function test_twilight_sunrise_sunset_match_reference(
        float $lat,
        float $lon,
        string $tz,
        string $date,
        string $twilightStart,
        string $twilightEnd,
        string $sunrise,
        string $sunset,
    ): void {
        $zone  = new DateTimeZone($tz);
        $times = (new PrayerTimes($lat, $lon, PrayerTimes::METHOD_KARACHI, PrayerTimes::ASR_STANDARD, $zone))
            ->getTimes(new DateTimeImmutable($date . ' 12:00', $zone));

        $this->assertWithinMinute($twilightStart, $times['fajr'], 'fajr (18° twilight start)');
        $this->assertWithinMinute($twilightEnd, $times['isha'], 'isha (18° twilight end)');
        $this->assertWithinMinute($sunrise, $times['sunrise'], 'sunrise');
        $this->assertWithinMinute($sunset, $times['maghrib'], 'sunset (maghrib angle 0)');
    }

    private function assertWithinMinute(string $expected, ?string $actual, string $what): void
    {
        $this->assertNotNull($actual, $what);
        $diff = abs($this->minutes($actual) - $this->minutes($expected));
        $this->assertLessThanOrEqual(1, $diff, sprintf('%s: expected %s, got %s', $what, $expected, $actual));
    }

    private function minutes(string $hhmm): int
    {
        return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
    }
}
