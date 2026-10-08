<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Prayer\HighLatitudeRule;
use RtlyKit\Prayer\PrayerTimes;

/**
 * Manual tuning, Asr schools, elevation and DST.
 *
 * Asr and elevation expectations come from an independent calculation (the
 * Astronomical Almanac low-precision Sun, hour angle from cos H, Asr altitude
 * arccot(factor + tan|lat - decl|), horizon -0.833 - 0.0347 sqrt(h) degrees),
 * accurate to about a minute, so comparisons allow 2 minutes.
 */
final class PrayerTimesTuneAndConfigTest extends TestCase
{
    /* ---------------- withTune ---------------- */

    public function test_tune_adds_minutes_to_the_published_tehran_times(): void
    {
        // University of Tehran table 2026-03-21: fajr 04:43, sunrise 06:07, dhuhr 12:12, maghrib 18:35.
        $tz    = new DateTimeZone('Asia/Tehran');
        $day   = new DateTimeImmutable('2026-03-21 10:00', $tz);
        $plain = PrayerTimes::forCity('tehran');
        $tuned = $plain->withTune(['fajr' => 5, 'sunrise' => -3, 'dhuhr' => 10, 'maghrib' => 30]);

        $t = $tuned->getTimes($day);
        $this->assertSame('04:48', $t['fajr']);
        $this->assertSame('06:04', $t['sunrise']);
        $this->assertSame('12:22', $t['dhuhr']);
        $this->assertSame('19:05', $t['maghrib']);

        // untouched names are unchanged
        $p = $plain->getTimes($day);
        $this->assertSame($p['asr'], $t['asr']);
        $this->assertSame($p['isha'], $t['isha']);
    }

    public function test_tune_shifts_asr_and_isha_by_whole_minutes(): void
    {
        $day   = new DateTimeImmutable('2026-03-21 10:00', new DateTimeZone('Asia/Tehran'));
        $plain = PrayerTimes::forCity('tehran')->getTimes($day);
        $tuned = PrayerTimes::forCity('tehran')->withTune(['asr' => -7, 'isha' => 25])->getTimes($day);

        $this->assertSame(-7, self::mins($tuned['asr']) - self::mins((string) $plain['asr']));
        $this->assertSame(25, self::mins($tuned['isha']) - self::mins((string) $plain['isha']));
    }

    public function test_tune_wraps_over_midnight(): void
    {
        // Reykjavik 21 June: Maghrib 00:04 and Isha 00:53 (reference calculation).
        $tz   = new DateTimeZone('Atlantic/Reykjavik');
        $day  = new DateTimeImmutable('2024-06-21 12:00', $tz);
        $base = new PrayerTimes(64.1466, -21.9426, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz);

        $back = $base->withTune(['maghrib' => -30])->getTimes($day);
        $this->assertSame(self::wrap(self::mins((string) $base->getTimes($day)['maghrib']) - 30), self::mins((string) $back['maghrib']));
        $this->assertGreaterThan(23 * 60, self::mins((string) $back['maghrib']));

        $fwd = $base->withTune(['dhuhr' => 30, 'isha' => 30])->getTimes($day);
        $this->assertSame(self::wrap(self::mins((string) $base->getTimes($day)['isha']) + 30), self::mins((string) $fwd['isha']));
    }

    public function test_tune_is_applied_by_next_prayer(): void
    {
        $tz   = new DateTimeZone('Asia/Tehran');
        $next = PrayerTimes::forCity('tehran')->withTune(['dhuhr' => 30])
            ->nextPrayer(new DateTimeImmutable('2026-03-21 12:20', $tz));

        $this->assertNotNull($next);
        $this->assertSame('dhuhr', $next['name']);
        $this->assertSame('12:42', $next['time']);
    }

    public function test_tune_boundaries_are_inclusive(): void
    {
        $day = new DateTimeImmutable('2026-03-21 10:00', new DateTimeZone('Asia/Tehran'));
        $p   = PrayerTimes::forCity('tehran')->getTimes($day);

        $hi = PrayerTimes::forCity('tehran')->withTune(['dhuhr' => 30])->getTimes($day);
        $lo = PrayerTimes::forCity('tehran')->withTune(['dhuhr' => -30])->getTimes($day);

        $this->assertSame(30, self::mins($hi['dhuhr']) - self::mins($p['dhuhr']));
        $this->assertSame(-30, self::mins($lo['dhuhr']) - self::mins($p['dhuhr']));
        $this->assertSame(30, PrayerTimes::MAX_TUNE_MINUTES);
    }

    public function test_tune_is_immutable_replaces_and_clears(): void
    {
        $day   = new DateTimeImmutable('2026-03-21 10:00', new DateTimeZone('Asia/Tehran'));
        $plain = PrayerTimes::forCity('tehran');
        $a     = $plain->withTune(['fajr' => 5]);
        $b     = $a->withTune(['sunrise' => 5]);

        $this->assertNotSame($plain, $a);
        $this->assertSame('04:43', $plain->getTimes($day)['fajr']);
        $this->assertSame('04:48', $a->getTimes($day)['fajr']);
        $this->assertSame('04:43', $b->getTimes($day)['fajr'], 'a new tune replaces the old one');
        $this->assertSame('06:12', $b->getTimes($day)['sunrise']);
        $this->assertSame($plain->getTimes($day), $a->withTune([])->getTimes($day));
    }

    public function test_tune_keeps_other_settings(): void
    {
        $tz  = new DateTimeZone('Europe/Oslo');
        $day = new DateTimeImmutable('2024-08-05 12:00', $tz);
        $pt  = (new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))
            ->withHighLatitudeRule(HighLatitudeRule::None);

        $this->assertNull($pt->withTune(['fajr' => 5])->getTimes($day)['fajr'], 'a null time stays null');
        $this->assertNull($pt->withTune(['isha' => 5])->getTimes($day)['isha']);
    }

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function invalidTunes(): array
    {
        return [
            'unknown key'      => [['zuhr' => 1], 'zuhr'],
            'numeric key'      => [[0 => 1], '0'],
            'too high'         => [['fajr' => 31], 'fajr'],
            'too low'          => [['isha' => -31], 'isha'],
            'float'            => [['asr' => 1.5], 'asr'],
            'numeric string'   => [['asr' => '5'], 'asr'],
            'null'             => [['maghrib' => null], 'maghrib'],
            'whole float'      => [['sunrise' => 2.0], 'sunrise'],
        ];
    }

    /**
     * @param array<mixed> $tune
     */
    #[DataProvider('invalidTunes')]
    public function test_invalid_tune_throws_with_error_code(array $tune, string $key): void
    {
        try {
            PrayerTimes::forCity('tehran')->withTune($tune); // @phpstan-ignore argument.type
            $this->fail('expected InvalidPrayerConfigException');
        } catch (InvalidPrayerConfigException $e) {
            $this->assertSame(ErrorCode::InvalidPrayerConfig, $e->getErrorCode());
            $this->assertSame(['key' => $key], $e->getContext());
            $this->assertStringContainsString($key, $e->getMessage());
        }
    }

    /* ---------------- Asr schools ---------------- */

    /**
     * [label, lat, lon, zone, date, standard asr, hanafi asr] (local clock, reference calculation).
     *
     * @return array<string, array{float, float, string, string, string, string}>
     */
    public static function asrReference(): array
    {
        return [
            'tehran equinox' => [35.6892, 51.3890, 'Asia/Tehran', '2025-03-21', '15:39', '16:32'],
            'mecca june'     => [21.4225, 39.8262, 'Asia/Riyadh', '2025-06-21', '15:42', '17:02'],
            'mecca december' => [21.4225, 39.8262, 'Asia/Riyadh', '2025-12-21', '15:23', '16:08'],
            'oslo september' => [59.9139, 10.7522, 'Europe/Oslo', '2025-09-23', '16:14', '17:03'],
        ];
    }

    #[DataProvider('asrReference')]
    public function test_asr_for_both_schools_matches_the_reference_calculation(
        float $lat,
        float $lon,
        string $zone,
        string $day,
        string $standard,
        string $hanafi,
    ): void {
        $tz   = new DateTimeZone($zone);
        $date = new DateTimeImmutable($day.' 10:00', $tz);

        $std = (new PrayerTimes($lat, $lon, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))->getTimes($date);
        $han = (new PrayerTimes($lat, $lon, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_HANAFI, $tz))->getTimes($date);

        $this->assertEqualsWithDelta(self::mins($standard), self::mins((string) $std['asr']), 2, 'standard');
        $this->assertEqualsWithDelta(self::mins($hanafi), self::mins((string) $han['asr']), 2, 'hanafi');
        // The school affects only Asr.
        $this->assertSame($std['dhuhr'], $han['dhuhr']);
        $this->assertSame($std['maghrib'], $han['maghrib']);
        $this->assertSame($std['isha'], $han['isha']);
    }

    /* ---------------- elevation ---------------- */

    /**
     * [metres, sunrise, sunset] at Tehran 2025-03-21 (Asia/Tehran), reference calculation.
     *
     * @return array<string, array{float, string, string}>
     */
    public static function elevationReference(): array
    {
        return [
            '0 m'    => [0.0, '06:06', '18:17'],
            '500 m'  => [500.0, '06:02', '18:21'],
            '1500 m' => [1500.0, '06:00', '18:24'],
            '3000 m' => [3000.0, '05:57', '18:26'],
        ];
    }

    #[DataProvider('elevationReference')]
    public function test_elevation_moves_sunrise_and_sunset_by_the_horizon_dip(float $metres, string $sunrise, string $sunset): void
    {
        $tz = new DateTimeZone('Asia/Tehran');
        $t  = (new PrayerTimes(35.6892, 51.3890, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz, $metres))
            ->getTimes(new DateTimeImmutable('2025-03-21 10:00', $tz));

        $this->assertEqualsWithDelta(self::mins($sunrise), self::mins((string) $t['sunrise']), 2, 'sunrise');
        $this->assertEqualsWithDelta(self::mins($sunset), self::mins((string) $t['maghrib']), 2, 'sunset');
    }

    public function test_negative_elevation_is_treated_as_sea_level_and_twilight_angles_are_unaffected(): void
    {
        $tz   = new DateTimeZone('Asia/Tehran');
        $day  = new DateTimeImmutable('2025-03-21 10:00', $tz);
        $flat = new PrayerTimes(35.6892, 51.3890, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz);
        $deep = new PrayerTimes(35.6892, 51.3890, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz, -400.0);
        $high = new PrayerTimes(35.6892, 51.3890, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz, 3000.0);

        $this->assertSame($flat->getTimes($day), $deep->getTimes($day));

        $f = $flat->getTimes($day);
        $h = $high->getTimes($day);
        foreach (['fajr', 'dhuhr', 'asr', 'isha'] as $name) {
            $this->assertSame($f[$name], $h[$name], $name);
        }
    }

    public function test_elevation_moves_makkah_isha_with_maghrib(): void
    {
        $tz   = new DateTimeZone('Asia/Riyadh');
        $day  = new DateTimeImmutable('2025-06-21 10:00', $tz);
        $flat = (new PrayerTimes(21.4225, 39.8262, PrayerTimes::METHOD_MAKKAH, PrayerTimes::ASR_STANDARD, $tz))->getTimes($day);
        $high = (new PrayerTimes(21.4225, 39.8262, PrayerTimes::METHOD_MAKKAH, PrayerTimes::ASR_STANDARD, $tz, 3000.0))->getTimes($day);

        $this->assertSame(90, self::mins((string) $high['isha']) - self::mins((string) $high['maghrib']));
        $this->assertGreaterThan(self::mins((string) $flat['maghrib']), self::mins((string) $high['maghrib']));
    }

    /* ---------------- DST ---------------- */

    /**
     * Local dhuhr jumps by the clock change: [lat, lon, zone, day before, day after, expected jump].
     *
     * @return array<string, array{float, float, string, string, string, int}>
     */
    public static function dstChanges(): array
    {
        return [
            'new york spring forward' => [40.7128, -74.0060, 'America/New_York', '2025-03-08', '2025-03-09', 60],
            'new york fall back'      => [40.7128, -74.0060, 'America/New_York', '2025-11-01', '2025-11-02', -60],
            'sydney fall back'        => [-33.8688, 151.2093, 'Australia/Sydney', '2025-04-05', '2025-04-06', -60],
            'london spring forward'   => [51.5074, -0.1278, 'Europe/London', '2025-03-29', '2025-03-30', 60],
        ];
    }

    #[DataProvider('dstChanges')]
    public function test_every_time_follows_the_real_utc_offset_across_a_clock_change(
        float $lat,
        float $lon,
        string $zone,
        string $before,
        string $after,
        int $jump,
    ): void {
        $tz = new DateTimeZone($zone);
        $pt = new PrayerTimes($lat, $lon, PrayerTimes::METHOD_ISNA, PrayerTimes::ASR_STANDARD, $tz);
        $b  = $pt->getTimes(new DateTimeImmutable($before.' 12:00', $tz));
        $a  = $pt->getTimes(new DateTimeImmutable($after.' 12:00', $tz));

        // The sun moves by well under 2 minutes per day, so a one-day difference is the clock change.
        foreach (['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'] as $name) {
            $this->assertEqualsWithDelta($jump, self::mins((string) $a[$name]) - self::mins((string) $b[$name]), 3, $name);
        }
    }

    private static function mins(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private static function wrap(int $minutes): int
    {
        return (($minutes % 1440) + 1440) % 1440;
    }
}
