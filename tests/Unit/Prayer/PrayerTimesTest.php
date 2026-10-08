<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RtlyKit\Prayer\HighLatitudeRule;
use RtlyKit\Prayer\PrayerTimes;

final class PrayerTimesTest extends TestCase
{
    /* ---------------- PrayerTimes ---------------- */

    public function test_prayer_times_tehran_in_january_use_previous_year_julian_day(): void
    {
        // January/February take the Julian-day year-shift branch; a wrong shift moves
        // the sun's declination by months and the times by far more than the window.
        $tz = new DateTimeZone('Asia/Tehran');
        $t = PrayerTimes::forCity('tehran')->getTimes(new DateTimeImmutable('2024-01-15 10:00', $tz));

        $min = static fn (?string $hhmm): int => (int) substr((string) $hhmm, 0, 2) * 60 + (int) substr((string) $hhmm, 3, 2);

        $this->assertEqualsWithDelta($min('12:14'), $min($t['dhuhr']), 5);
        $this->assertEqualsWithDelta($min('07:21'), $min($t['sunrise']), 8);
        $this->assertEqualsWithDelta($min('17:35'), $min($t['maghrib']), 10);
    }

    public function test_prayer_times_at_the_pole_only_yield_dhuhr(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt = (new PrayerTimes(90.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc))
            ->withHighLatitudeRule(HighLatitudeRule::None);
        $t = $pt->getTimes(new DateTimeImmutable('2024-06-21 10:00', $utc));

        $this->assertMatchesRegularExpression('/^\d\d:\d\d$/', $t['dhuhr']);
        $this->assertNull($t['fajr']);
        $this->assertNull($t['sunrise']);
        $this->assertNull($t['asr']);
        $this->assertNull($t['maghrib']);
        $this->assertNull($t['isha']);
    }

    public function test_next_prayer_at_the_pole_rolls_over_to_tomorrows_dhuhr(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt = (new PrayerTimes(90.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc))
            ->withHighLatitudeRule(HighLatitudeRule::None);

        $next = $pt->nextPrayer(new DateTimeImmutable('2024-06-21 23:30', $utc));

        $this->assertNotNull($next);
        $this->assertSame('dhuhr', $next['name']);
        $this->assertSame('2024-06-22', $next['date']);
    }

    public function test_midnight_sun_and_polar_night_have_no_sunrise(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt = new PrayerTimes(78.22, 15.65, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc); // Longyearbyen

        $summer = $pt->getTimes(new DateTimeImmutable('2024-06-21 12:00', $utc));
        $winter = $pt->getTimes(new DateTimeImmutable('2024-12-21 12:00', $utc));

        foreach ([$summer, $winter] as $t) {
            $this->assertNull($t['sunrise']);
            $this->assertNull($t['maghrib']);
            $this->assertNotNull($t['dhuhr']);
        }
    }

    public function test_tehran_equinox_known_values(): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');
        $times  = PrayerTimes::forCity('tehran')->getTimes(new DateTimeImmutable('2024-03-20 10:00', $tehran));

        // Solar noon: 12:00 + (52.5E - 51.389E)/15 h shift + equation of time (-7.4 min) => ~12:12
        $this->assertNear('12:12', $times['dhuhr'], 3, 'dhuhr');
        // Equinox: day length ~12h08m, sunrise ~06:07 (+-5 min tolerance for the simple algorithm)
        $this->assertNear('06:07', $times['sunrise'], 5, 'sunrise');
        $this->assertNear('04:44', $times['fajr'], 6, 'fajr');
        $this->assertNear('18:34', $times['maghrib'], 6, 'maghrib');

        $order = array_map(fn (?string $t): int => $this->minutes($t), array_values($times));
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'times must be chronologically ordered');
    }

    public function test_sunrise_and_dhuhr_are_symmetric_with_sunset(): void
    {
        $times = (new PrayerTimes(51.5074, -0.1278, PrayerTimes::METHOD_MWL, 1, new DateTimeZone('UTC')))
            ->getTimes(new DateTimeImmutable('2024-03-20 12:00', new DateTimeZone('UTC')));

        // London at the March equinox: solar noon ~12:07 UTC (EqT -7.4 min, lon -0.13)
        $this->assertNear('12:07', $times['dhuhr'], 3);
        $this->assertNear('06:00', $times['sunrise'], 6);
    }

    public function test_makkah_method_isha_is_90_minutes_after_maghrib(): void
    {
        $riyadh = new DateTimeZone('Asia/Riyadh');
        $times  = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)
            ->getTimes(new DateTimeImmutable('2024-06-21 09:00', $riyadh));

        $this->assertNear('12:23', $times['dhuhr'], 3, 'mecca dhuhr');
        $this->assertLessThanOrEqual(1, abs($this->minutes($times['isha']) - $this->minutes($times['maghrib']) - 90));
    }

    public function test_hanafi_asr_is_later_than_standard(): void
    {
        $date = new DateTimeImmutable('2024-03-20', new DateTimeZone('Asia/Tehran'));
        $std  = PrayerTimes::forCity('tehran', 'Tehran', PrayerTimes::ASR_STANDARD)->getTimes($date);
        $han  = PrayerTimes::forCity('tehran', 'Tehran', PrayerTimes::ASR_HANAFI)->getTimes($date);

        $this->assertGreaterThan($this->minutes($std['asr']), $this->minutes($han['asr']));
    }

    public function test_unknown_method_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown calculation method');
        new PrayerTimes(35.0, 51.0, 'Nope');
    }

    public function test_unknown_method_via_for_city_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PrayerTimes::forCity('tehran', 'Nope');
    }

    public function test_invalid_asr_factor_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PrayerTimes(35.0, 51.0, PrayerTimes::METHOD_MWL, 3);
    }

    public function test_unknown_city_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PrayerTimes::forCity('atlantis');
    }

    public function test_default_timezone_is_php_default(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tehran');

        try {
            // 01:30 on 2024-03-20 in Tehran == 22:00 UTC on 03-19
            $calc  = new PrayerTimes(35.6892, 51.3890);
            $times = $calc->getTimes(new DateTimeImmutable('2024-03-19 22:00', new DateTimeZone('UTC')));

            // Output is local (Tehran) time, so dhuhr is ~12:12, not ~08:42 (UTC)
            $this->assertNear('12:12', $times['dhuhr'], 3);
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_next_prayer_within_day(): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');
        $calc   = PrayerTimes::forCity('tehran');

        $next = $calc->nextPrayer(new DateTimeImmutable('2024-03-20 12:00', $tehran));
        $this->assertNotNull($next);
        $this->assertSame('dhuhr', $next['name']);
        $this->assertSame('2024-03-20', $next['date']);

        $next = $calc->nextPrayer(new DateTimeImmutable('2024-03-20 00:30', $tehran));
        $this->assertNotNull($next);
        $this->assertSame('fajr', $next['name']);
        $this->assertSame('2024-03-20', $next['date']);
    }

    public function test_next_prayer_after_isha_rolls_to_tomorrow_fajr(): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');
        $calc   = PrayerTimes::forCity('tehran');

        $next = $calc->nextPrayer(new DateTimeImmutable('2024-03-20 23:30', $tehran));
        $this->assertNotNull($next);
        $this->assertSame('fajr', $next['name']);
        $this->assertSame('2024-03-21', $next['date']);

        $tomorrow = $calc->getTimes(new DateTimeImmutable('2024-03-21', $tehran));
        $this->assertSame($tomorrow['fajr'], $next['time']);
    }

    public function test_next_prayer_converts_input_timezone(): void
    {
        // 20:00 UTC == 23:30 Tehran -> after isha -> tomorrow's fajr (Tehran date)
        $next = PrayerTimes::forCity('tehran')->nextPrayer(new DateTimeImmutable('2024-03-20 20:00', new DateTimeZone('UTC')));

        $this->assertNotNull($next);
        $this->assertSame('fajr', $next['name']);
        $this->assertSame('2024-03-21', $next['date']);
    }

    public function test_high_latitude_returns_null_instead_of_clamping(): void
    {
        // Tromso midsummer: midnight sun, so no sunrise/sunset/twilight angles are reached
        $calc  = (new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_MWL, 1, new DateTimeZone('UTC')))
            ->withHighLatitudeRule(HighLatitudeRule::None);
        $times = $calc->getTimes(new DateTimeImmutable('2024-06-21 12:00', new DateTimeZone('UTC')));

        $this->assertNull($times['fajr']);
        $this->assertNull($times['sunrise']);
        $this->assertNull($times['maghrib']);
        $this->assertNull($times['isha']);
        $this->assertNear('10:46', $times['dhuhr'], 3, 'solar noon in Tromso');

        // nextPrayer skips unavailable times
        $next = $calc->nextPrayer(new DateTimeImmutable('2024-06-21 00:30', new DateTimeZone('UTC')));
        $this->assertNotNull($next);
        $this->assertSame('dhuhr', $next['name']);
    }

    public function test_high_latitude_winter_has_all_times(): void
    {
        $calc  = new PrayerTimes(59.9139, 10.7522, PrayerTimes::METHOD_MWL, 1, new DateTimeZone('UTC'));
        $times = $calc->getTimes(new DateTimeImmutable('2024-12-21 12:00', new DateTimeZone('UTC')));

        $this->assertNotNull($times['fajr']);
        $this->assertNotNull($times['isha']);
    }

    /**
     * Sunrise/sunset from the NOAA Solar Calculator (gml.noaa.gov/grad/solcalc),
     * independent reference; solar noon is the NOAA value rounded to the minute.
     *
     * @return array<string, array{string, float, float, string, string, string, string}>
     */
    public static function noaaReference(): array
    {
        return [
            'tehran jan 1'    => ['Asia/Tehran', 35.6892, 51.3890, '2025-01-01', '07:14', '12:08', '17:02'],
            'tehran aug 21'   => ['Asia/Tehran', 35.6892, 51.3890, '2025-08-21', '05:28', '12:08', '18:47'],
            'jakarta mar 21'  => ['Asia/Jakarta', -6.2088, 106.8456, '2025-03-21', '05:57', '12:00', '18:03'],
            'jakarta sep 12'  => ['Asia/Jakarta', -6.2088, 106.8456, '2025-09-12', '05:47', '11:49', '17:50'],
            'istanbul jan 1'  => ['Europe/Istanbul', 41.0082, 28.9784, '2025-01-01', '08:29', '13:08', '17:47'],
            'istanbul sep 12' => ['Europe/Istanbul', 41.0082, 28.9784, '2025-09-12', '06:42', '13:00', '19:18'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('noaaReference')]
    public function test_sunrise_dhuhr_sunset_match_noaa_calculator(string $tz, float $lat, float $lng, string $day, string $rise, string $noon, string $set): void
    {
        $zone = new DateTimeZone($tz);
        $t    = (new PrayerTimes($lat, $lng, PrayerTimes::METHOD_MWL, 1, $zone))->getTimes(new DateTimeImmutable($day.' 10:00', $zone));

        $this->assertEqualsWithDelta(self::mins($rise), self::mins((string) $t['sunrise']), 1);
        $this->assertEqualsWithDelta(self::mins($noon), self::mins($t['dhuhr']), 1);
        $this->assertEqualsWithDelta(self::mins($set), self::mins((string) $t['maghrib']), 1);
    }

    public function test_asr_shadow_factors_at_equator_equinox(): void
    {
        // lat 0, declination ~0: shadow = factor, so the sun is at arccot(factor):
        // standard => 45 deg => hour angle 45 deg (180 min); Hanafi => 26.57 deg => 63.43 deg (253.7 min)
        $utc = new DateTimeZone('UTC');
        $day = new DateTimeImmutable('2025-03-20 10:00', $utc);

        $std = (new PrayerTimes(0.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc))->getTimes($day);
        $han = (new PrayerTimes(0.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_HANAFI, $utc))->getTimes($day);

        $this->assertEqualsWithDelta(180, self::mins((string) $std['asr']) - self::mins($std['dhuhr']), 2);
        $this->assertEqualsWithDelta(254, self::mins((string) $han['asr']) - self::mins($han['dhuhr']), 2);
    }

    public function test_polar_night_gives_null_sunrise_but_still_dhuhr(): void
    {
        $utc = new DateTimeZone('UTC');
        $t   = (new PrayerTimes(-80.0, 0.0, PrayerTimes::METHOD_MWL, 1, $utc))->getTimes(new DateTimeImmutable('2025-06-21 12:00', $utc));

        foreach (['sunrise', 'asr', 'maghrib'] as $name) {
            $this->assertNull($t[$name], $name);
        }
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $t['dhuhr']);
    }

    public function test_midnight_sun_with_makkah_method_has_null_isha(): void
    {
        $utc = new DateTimeZone('UTC');
        $t   = (new PrayerTimes(80.0, 0.0, PrayerTimes::METHOD_MAKKAH, 1, $utc))->getTimes(new DateTimeImmutable('2025-06-21 12:00', $utc));

        $this->assertNull($t['maghrib']);
        $this->assertNull($t['isha']);
    }

    public function test_dst_shift_is_applied_with_the_real_offset(): void
    {
        // Egypt starts DST on Fri 2025-04-25 (00:00): solar noon moves one hour later on the clock.
        $cairo = new DateTimeZone('Africa/Cairo');
        $pt    = PrayerTimes::forCity('cairo', PrayerTimes::METHOD_EGYPT);
        $before = $pt->getTimes(new DateTimeImmutable('2025-04-24 10:00', $cairo));
        $after  = $pt->getTimes(new DateTimeImmutable('2025-04-25 10:00', $cairo));

        $this->assertEqualsWithDelta(61, self::mins($after['dhuhr']) - self::mins($before['dhuhr']), 2);
        $this->assertEqualsWithDelta(61, self::mins((string) $after['isha']) - self::mins((string) $before['isha']), 3);
    }

    public function test_elevation_lowers_the_horizon(): void
    {
        $tz   = new DateTimeZone('Asia/Tehran');
        $day  = new DateTimeImmutable('2025-03-21 10:00', $tz);
        $flat = (new PrayerTimes(35.7, 51.4, PrayerTimes::METHOD_MWL, 1, $tz))->getTimes($day);
        $high = (new PrayerTimes(35.7, 51.4, PrayerTimes::METHOD_MWL, 1, $tz, 1500.0))->getTimes($day);

        // dip 0.0347*sqrt(1500) = 1.34 deg => roughly 6 minutes at this latitude
        $this->assertEqualsWithDelta(6, self::mins((string) $flat['sunrise']) - self::mins((string) $high['sunrise']), 2);
        $this->assertEqualsWithDelta(6, self::mins((string) $high['maghrib']) - self::mins((string) $flat['maghrib']), 2);
        $this->assertSame($flat['fajr'], $high['fajr']);
    }

    public function test_next_prayer_rolls_over_the_year_boundary(): void
    {
        $tz   = new DateTimeZone('Asia/Tehran');
        $next = PrayerTimes::forCity('tehran')->nextPrayer(new DateTimeImmutable('2025-12-31 23:30', $tz));

        $this->assertNotNull($next);
        $this->assertSame('fajr', $next['name']);
        $this->assertSame('2026-01-01', $next['date']);
    }

    private function minutes(?string $hhmm): int
    {
        $this->assertNotNull($hhmm);
        [$h, $m] = array_map('intval', explode(':', (string) $hhmm));

        return $h * 60 + $m;
    }

    private function assertNear(string $expected, ?string $actual, int $tolerance, string $msg = ''): void
    {
        $this->assertLessThanOrEqual(
            $tolerance,
            abs($this->minutes($expected) - $this->minutes($actual)),
            "{$msg} expected ~{$expected}, got ".($actual ?? 'null'),
        );
    }

    private static function mins(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

}
