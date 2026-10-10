<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Prayer\HighLatitudeRule;
use RtlyKit\Prayer\PrayerTimes;

/**
 * Range guards, instant arithmetic before 1970, far-away time zones and the
 * Tehran Maghrib/Isha ordering at high latitudes.
 */
final class PrayerTimesRobustnessTest extends TestCase
{
    private static function mins(string $hhmm): int
    {
        return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
    }

    /* ---------------- years 1..9999 ---------------- */

    public function test_get_times_rejects_a_local_year_after_9999(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(35.6892, 51.389, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, new DateTimeZone('Asia/Tehran'));

        // 9999-12-31 23:59 UT is already 10000-01-01 in Tehran.
        try {
            $pt->getTimes(new DateTimeImmutable('9999-12-31 23:59', $utc));
            $this->fail('expected InvalidDateException');
        } catch (InvalidDateException $e) {
            $this->assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
        }

        $this->expectException(InvalidDateException::class);
        $pt->getTimes(new DateTimeImmutable('+10000-06-01 12:00', $utc));
    }

    public function test_get_times_rejects_year_zero_and_negative_years(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(51.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);

        foreach ([0, -5] as $year) {
            $date = (new DateTimeImmutable('2000-06-01 12:00', $utc))->setDate($year, 6, 1);
            try {
                $pt->getTimes($date);
                $this->fail("expected InvalidDateException for year {$year}");
            } catch (InvalidDateException $e) {
                $this->assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
            }
        }
    }

    public function test_next_prayer_rejects_a_year_outside_the_range(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(51.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);

        $this->expectException(InvalidDateException::class);
        $pt->nextPrayer(new DateTimeImmutable('+10000-01-01 00:00', $utc));
    }

    public function test_the_first_and_last_supported_days_work(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(51.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);

        $first = $pt->getTimes(new DateTimeImmutable('0001-01-01 12:00', $utc));
        $last  = $pt->getTimes(new DateTimeImmutable('9999-12-31 12:00', $utc));
        // Longitude 0: solar noon is within the equation of time (+-17 min) of 12:00.
        $this->assertEqualsWithDelta(12 * 60, self::mins($first['dhuhr']), 17);
        $this->assertEqualsWithDelta(12 * 60, self::mins($last['dhuhr']), 17);

        // The day before the first supported day does not exist: nextPrayer still answers.
        $next = $pt->nextPrayer(new DateTimeImmutable('0001-01-01 00:00', $utc));
        $this->assertNotNull($next);
        $this->assertSame('0001-01-01', $next['date']);
    }

    public function test_next_prayer_after_the_last_evening_of_9999_is_null(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(51.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);

        $this->assertNotNull($pt->nextPrayer(new DateTimeImmutable('9999-12-31 12:00', $utc)));
        $this->assertNull($pt->nextPrayer(new DateTimeImmutable('9999-12-31 23:59', $utc)));
    }

    /* ---------------- floor to the minute before 1970 ---------------- */

    public function test_next_prayer_thirty_seconds_before_dhuhr_before_1970_returns_dhuhr(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(51.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);
        $day = new DateTimeImmutable('1960-03-21 00:00', $utc);

        $dhuhr   = $pt->getTimes($day)['dhuhr'];
        [$h, $m] = array_map('intval', explode(':', $dhuhr));
        $query   = $day->setTime($h, $m)->modify('-30 seconds');
        $this->assertLessThan(0, $query->getTimestamp());

        $next = $pt->nextPrayer($query);

        $this->assertNotNull($next);
        $this->assertSame('dhuhr', $next['name']);
        $this->assertSame($dhuhr, $next['time']);
        $this->assertSame('1960-03-21', $next['date']);
    }

    /* ---------------- constructor ranges ---------------- */

    /**
     * @return array<string, array{float, float, float, string}>
     */
    public static function invalidCoordinates(): array
    {
        return [
            'NaN latitude'       => [NAN, 0.0, 0.0, 'latitude'],
            'INF latitude'       => [INF, 0.0, 0.0, 'latitude'],
            'latitude too high'  => [90.01, 0.0, 0.0, 'latitude'],
            'latitude too low'   => [-91.0, 0.0, 0.0, 'latitude'],
            'NaN longitude'      => [0.0, NAN, 0.0, 'longitude'],
            '-INF longitude'     => [0.0, -INF, 0.0, 'longitude'],
            'longitude too high' => [0.0, 180.5, 0.0, 'longitude'],
            'longitude too low'  => [0.0, -181.0, 0.0, 'longitude'],
            'NaN elevation'      => [0.0, 0.0, NAN, 'elevation'],
            'INF elevation'      => [0.0, 0.0, INF, 'elevation'],
            'absurd elevation'   => [0.0, 0.0, 1e300, 'elevation'],
            'elevation too high' => [0.0, 0.0, 20000.5, 'elevation'],
            'elevation too low'  => [0.0, 0.0, -20001.0, 'elevation'],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_constructor_rejects_invalid_numbers(float $lat, float $lng, float $elevation, string $option): void
    {
        try {
            new PrayerTimes($lat, $lng, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, new DateTimeZone('UTC'), $elevation);
            $this->fail('expected InvalidPrayerConfigException');
        } catch (InvalidPrayerConfigException $e) {
            $this->assertSame(ErrorCode::InvalidPrayerConfig, $e->getErrorCode());
            $this->assertSame(['option' => $option], $e->getContext());
            $this->assertStringContainsString($option, $e->getMessage());
        }
    }

    public function test_constructor_accepts_the_boundary_values(): void
    {
        $utc = new DateTimeZone('UTC');
        $day = new DateTimeImmutable('2025-03-21 12:00', $utc);

        foreach ([[90.0, 180.0, 20000.0], [-90.0, -180.0, -20000.0], [0.0, 0.0, 0.0]] as [$lat, $lng, $h]) {
            $pt = new PrayerTimes($lat, $lng, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc, $h);
            $this->assertMatchesRegularExpression('/^\d\d:\d\d$/', $pt->getTimes($day)['dhuhr']);
        }
    }

    public function test_negative_elevation_is_treated_as_zero(): void
    {
        $utc = new DateTimeZone('UTC');
        $day = new DateTimeImmutable('2025-03-21 12:00', $utc);
        $a   = new PrayerTimes(40.0, 10.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc, 0.0);
        $b   = new PrayerTimes(40.0, 10.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc, -20000.0);

        $this->assertSame($a->getTimes($day), $b->getTimes($day));
    }

    /* ---------------- Tehran Maghrib < Isha ---------------- */

    /**
     * @return array<string, array{HighLatitudeRule}>
     */
    public static function rules(): array
    {
        $cases = [];
        foreach (HighLatitudeRule::cases() as $rule) {
            $cases[$rule->value] = [$rule];
        }

        return $cases;
    }

    #[DataProvider('rules')]
    public function test_tehran_maghrib_is_strictly_before_isha_across_latitudes(HighLatitudeRule $rule): void
    {
        $utc  = new DateTimeZone('UTC');
        $days = ['2025-03-21', '2025-05-03', '2025-06-01', '2025-06-21', '2025-09-23', '2025-12-21'];

        for ($lat = 25.0; $lat <= 66.0; $lat += 1.0) {
            $pt = (new PrayerTimes($lat, 0.0, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, $utc))
                ->withHighLatitudeRule($rule);

            foreach ($days as $d) {
                $t = $pt->getTimes(new DateTimeImmutable($d.' 12:00', $utc));
                if ($t['maghrib'] === null || $t['isha'] === null) {
                    continue;
                }

                // Minutes after solar noon, so a value past midnight still counts as later.
                $noon = self::mins($t['dhuhr']);
                $mag  = (self::mins($t['maghrib']) - $noon + 1440) % 1440;
                $isha = (self::mins($t['isha']) - $noon + 1440) % 1440;
                $this->assertLessThan($isha, $mag, "lat {$lat} {$d} {$rule->value}: {$t['maghrib']} / {$t['isha']}");
            }
        }
    }

    public function test_tehran_maghrib_and_isha_differ_in_oslo_with_one_seventh(): void
    {
        $tz = new DateTimeZone('Europe/Oslo');
        $t  = (new PrayerTimes(59.9139, 10.7522, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, $tz))
            ->withHighLatitudeRule(HighLatitudeRule::OneSeventh)
            ->getTimes(new DateTimeImmutable('2025-06-01 12:00', $tz));

        $this->assertNotSame($t['maghrib'], $t['isha']);
    }

    public function test_tehran_maghrib_and_isha_differ_in_tromso_with_night_middle(): void
    {
        $tz = new DateTimeZone('Europe/Oslo');
        $t  = (new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, $tz))
            ->withHighLatitudeRule(HighLatitudeRule::NightMiddle)
            ->getTimes(new DateTimeImmutable('2025-05-03 12:00', $tz));

        $this->assertNotNull($t['maghrib']);
        $this->assertNotSame($t['maghrib'], $t['isha']);
    }

    /* ---------------- time zones far from the longitude ---------------- */

    /**
     * @return array<string, array{string, float, float, string, string}>
     */
    public static function farZones(): array
    {
        return [
            'Kiritimati 2025' => ['Pacific/Kiritimati', 1.8721, -157.4278, '2025-01-01', '2025-12-31'],
            'Apia 2025'       => ['Pacific/Apia', -13.8333, -171.7667, '2025-01-01', '2025-12-31'],
            'Apia DST 2019'   => ['Pacific/Apia', -13.8333, -171.7667, '2019-01-01', '2019-12-31'],
            'Pago Pago 2025'  => ['Pacific/Pago_Pago', -14.2756, -170.7020, '2025-01-01', '2025-12-31'],
            'UTC-12 2025'     => ['Etc/GMT+12', 0.0, -179.0, '2025-01-01', '2025-12-31'],
            'UTC+14 2025'     => ['Etc/GMT-14', 40.0, -150.0, '2025-01-01', '2025-12-31'],
            'Kwajalein 2025'  => ['Pacific/Kwajalein', 9.1927, 167.4726, '2025-01-01', '2025-12-31'],
        ];
    }

    #[DataProvider('farZones')]
    public function test_every_prayer_falls_on_the_requested_local_date(string $zone, float $lat, float $lng, string $first, string $last): void
    {
        $tz    = new DateTimeZone($zone);
        $pt    = new PrayerTimes($lat, $lng, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz);
        $order = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];

        $day = new DateTimeImmutable($first.' 00:00', $tz);
        $end = new DateTimeImmutable($last.' 00:00', $tz);
        for (; $day <= $end; $day = $day->modify('+1 day')->setTime(0, 0)) {
            $ymd  = $day->format('Y-m-d');
            $from = $day->modify('-1 minute');

            foreach ($order as $name) {
                $next = $pt->nextPrayer($from);
                $this->assertNotNull($next);
                $this->assertSame($name, $next['name'], $ymd);
                $this->assertSame($ymd, $next['date'], "{$name} on {$ymd}");

                [$h, $m] = array_map('intval', explode(':', $next['time']));
                $from    = $day->setTime($h, $m);
            }
        }
    }

    public function test_kiritimati_clock_times_equal_those_of_the_same_longitude_24_hours_behind(): void
    {
        // Etc/GMT+10 is UTC-10, exactly 24 h behind Kiritimati (UTC+14): the same wall-clock times on the same date.
        $far  = new PrayerTimes(1.8721, -157.4278, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, new DateTimeZone('Pacific/Kiritimati'));
        $near = new PrayerTimes(1.8721, -157.4278, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, new DateTimeZone('Etc/GMT+10'));

        $day = new DateTimeImmutable('2025-01-01 12:00', new DateTimeZone('UTC'));
        for ($i = 0; $i < 365; $i++, $day = $day->modify('+1 day')) {
            $this->assertSame(
                $near->getTimes($day->setTimezone(new DateTimeZone('Etc/GMT+10'))),
                $far->getTimes($day->setTimezone(new DateTimeZone('Pacific/Kiritimati'))),
                $day->format('Y-m-d'),
            );
        }
    }

    /**
     * The first zone is 24 hours ahead of the second. Zones 24 hours apart at the same longitude see the same sun at the same wall-clock time, one calendar
     * date apart. Their results must be identical (every minute, 366 days, 2 methods). The same calendar
     * date in both zones is a different physical day (24 hours of declination drift, up to a minute), so
     * that comparison is not expected to match.
     *
     * @return array<string, array{string, string, float, float}>
     */
    public static function zonesOneDayApart(): array
    {
        return [
            'Kiritimati / UTC-10'  => ['Pacific/Kiritimati', 'Etc/GMT+10', 1.8721, -157.4278],
            'Apia / UTC-11'        => ['Pacific/Apia', 'Etc/GMT+11', -13.8333, -171.7667],
            'UTC+13 / Pago Pago'   => ['Etc/GMT-13', 'Pacific/Pago_Pago', -14.2756, -170.7020],
            'Kwajalein / UTC-12'   => ['Pacific/Kwajalein', 'Etc/GMT+12', 9.1927, 167.4726],
            'UTC+12 / UTC-12'      => ['Etc/GMT-12', 'Etc/GMT+12', 0.0, -179.0],
            'UTC+14 / UTC-10 (40N)' => ['Etc/GMT-14', 'Etc/GMT+10', 40.0, -150.0],
        ];
    }

    #[DataProvider('zonesOneDayApart')]
    public function test_far_zones_equal_the_zone_24_hours_behind_with_exact_minutes(string $far, string $near, float $lat, float $lng): void
    {
        $farZone  = new DateTimeZone($far);
        $nearZone = new DateTimeZone($near);
        // The pairs differ by exactly 24 h in 2025 (Apia has no DST since 2021).
        $instant = new DateTimeImmutable('2025-06-01 12:00', new DateTimeZone('UTC'));
        $this->assertSame(86400, $farZone->getOffset($instant) - $nearZone->getOffset($instant), "{$far} / {$near}");

        foreach ([PrayerTimes::METHOD_MWL, PrayerTimes::METHOD_TEHRAN] as $method) {
            $a = new PrayerTimes($lat, $lng, $method, PrayerTimes::ASR_STANDARD, $farZone);
            $b = new PrayerTimes($lat, $lng, $method, PrayerTimes::ASR_STANDARD, $nearZone);

            $day = new DateTimeImmutable('2025-01-01 12:00', new DateTimeZone('UTC'));
            for ($i = 0; $i < 366; $i++, $day = $day->modify('+1 day')) {
                $this->assertSame(
                    $b->getTimes($day->setTimezone($nearZone)),
                    $a->getTimes($day->setTimezone($farZone)),
                    "{$far} {$method} ".$day->format('Y-m-d'),
                );
            }
        }
    }

    public function test_kiritimati_dhuhr_is_after_noon_clock_time(): void
    {
        // Zone UTC+14 at 157.4 W: mean solar time is 24.49 h behind the clock, so noon is at about 12:29
        // (equation of time -17..+14 min).
        $tz = new DateTimeZone('Pacific/Kiritimati');
        $t  = (new PrayerTimes(1.8721, -157.4278, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))
            ->getTimes(new DateTimeImmutable('2025-03-21 12:00', $tz));

        $this->assertEqualsWithDelta(12 * 60 + 29, self::mins($t['dhuhr']), 17);
    }
}
