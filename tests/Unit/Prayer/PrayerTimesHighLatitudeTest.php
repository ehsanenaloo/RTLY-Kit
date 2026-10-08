<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Prayer\HighLatitudeRule;
use RtlyKit\Prayer\PrayerTimes;

/**
 * High-latitude rules (night = sunset -> next sunrise; Fajr/Isha limited to a
 * portion of it: middle of the night 1/2, one-seventh 1/7, angle-based
 * angle/60, https://praytimes.org/calculation).
 *
 * Expected values are NOT taken from the implementation. They come from a
 * separate calculation: the Astronomical Almanac low-precision Sun (mean
 * longitude, mean anomaly, ecliptic longitude, obliquity, right ascension for
 * the equation of time; a different series from the one the library uses),
 * hour angle from cos H = (sin a - sin phi sin delta) / (cos phi cos delta),
 * horizon -0.833 deg, MWL angles 18 / 17, evaluated per UTC day, then the rule
 * definitions applied by hand:
 *
 *   night = (next sunrise + 24h) - sunset
 *   fajr  = sunrise - portion(18) * night   (only if the raw Fajr is missing
 *   isha  = sunset  + portion(17) * night    or outside this bound)
 *
 * Reference results (local clock; CEST = UTC+2, CET = UTC+1, Iceland UTC+0):
 *
 *   Tromso 2024-08-05: sunrise 02:55, sunset 22:45, night 4.264 h, no 18/17 deg twilight.
 *       middle 00:47 / 00:53; one-seventh 02:18 / 23:22; angle 01:38 / 23:58 (fajr / isha).
 *   Tromso 2024-06-21: sun never sets; reference night 06:00..18:00 solar time (noon 12:46), 12 h.
 *       middle 00:46 / 00:46; one-seventh 05:03 / 20:29; angle 03:10 / 22:10.
 *   Tromso 2024-12-21 (CET): sun never rises; raw Fajr 06:29, raw Isha 16:44, noon 11:42.
 *   Reykjavik 2024-06-21: sunrise 02:55, sunset 00:04 (+1 day), night 2.865 h.
 *       middle 01:29 / 01:30; one-seventh 02:31 / 00:29; angle 02:04 / 00:53.
 *   Oslo 2024-06-21: sunrise 03:54, sunset 22:44, night 5.174 h.
 *       middle 01:19 / 01:19; one-seventh 03:10 / 23:28; angle 02:21 / 00:12.
 *   Stockholm 2024-06-21: sunrise 03:31, sunset 22:08, night 5.387 h.
 *       middle 00:49 / 00:50; one-seventh 02:45 / 22:54; angle 01:54 / 23:40.
 *   Munich 2024-06-21: sunrise 05:14, sunset 21:18, night 7.939 h; the angles ARE reached
 *       (raw Fajr 01:51, raw Isha 00:12) but far from the horizon events.
 *       middle bound 01:15 / 01:16; one-seventh 04:06 / 22:26; angle 02:51 / 23:33.
 *
 * The reference is accurate to about 1-2 minutes, so comparisons allow 3
 * minutes (circular, because several times fall around midnight).
 */
final class PrayerTimesHighLatitudeTest extends TestCase
{
    private const TOLERANCE = 3;

    /**
     * [label, lat, lon, zone, date, rule, fajr, isha] (local clock times).
     *
     * @return array<string, array{string, float, float, string, string, HighLatitudeRule, string, string}>
     */
    public static function nightPortionCases(): array
    {
        $tromso    = [69.6492, 18.9553, 'Europe/Oslo'];
        $reykjavik = [64.1466, -21.9426, 'Atlantic/Reykjavik'];
        $oslo      = [59.9139, 10.7522, 'Europe/Oslo'];
        $stockholm = [59.3293, 18.0686, 'Europe/Stockholm'];
        $munich    = [48.1351, 11.5820, 'Europe/Berlin'];

        $m = HighLatitudeRule::NightMiddle;
        $s = HighLatitudeRule::OneSeventh;
        $a = HighLatitudeRule::AngleBased;

        return [
            'tromso aug middle'     => ['tromso', ...$tromso, '2024-08-05', $m, '00:47', '00:53'],
            'tromso aug seventh'    => ['tromso', ...$tromso, '2024-08-05', $s, '02:18', '23:22'],
            'tromso aug angle'      => ['tromso', ...$tromso, '2024-08-05', $a, '01:38', '23:58'],
            'tromso june middle'    => ['tromso', ...$tromso, '2024-06-21', $m, '00:46', '00:46'],
            'tromso june seventh'   => ['tromso', ...$tromso, '2024-06-21', $s, '05:03', '20:29'],
            'tromso june angle'     => ['tromso', ...$tromso, '2024-06-21', $a, '03:10', '22:10'],
            'reykjavik middle'      => ['reykjavik', ...$reykjavik, '2024-06-21', $m, '01:29', '01:30'],
            'reykjavik seventh'     => ['reykjavik', ...$reykjavik, '2024-06-21', $s, '02:31', '00:29'],
            'reykjavik angle'       => ['reykjavik', ...$reykjavik, '2024-06-21', $a, '02:04', '00:53'],
            'oslo middle'           => ['oslo', ...$oslo, '2024-06-21', $m, '01:19', '01:19'],
            'oslo seventh'          => ['oslo', ...$oslo, '2024-06-21', $s, '03:10', '23:28'],
            'oslo angle'            => ['oslo', ...$oslo, '2024-06-21', $a, '02:21', '00:12'],
            'stockholm middle'      => ['stockholm', ...$stockholm, '2024-06-21', $m, '00:49', '00:50'],
            'stockholm seventh'     => ['stockholm', ...$stockholm, '2024-06-21', $s, '02:45', '22:54'],
            'stockholm angle'       => ['stockholm', ...$stockholm, '2024-06-21', $a, '01:54', '23:40'],
            // Angles reached but outside the bound: middle keeps the raw value (inside 1/2 of the
            // night: Fajr 01:51, Isha 00:12 local), one-seventh and angle-based pull them in.
            'munich middle keeps'   => ['munich', ...$munich, '2024-06-21', $m, '01:51', '00:12'],
            'munich seventh clamps' => ['munich', ...$munich, '2024-06-21', $s, '04:06', '22:26'],
            'munich angle clamps'   => ['munich', ...$munich, '2024-06-21', $a, '02:51', '23:33'],
        ];
    }

    #[DataProvider('nightPortionCases')]
    public function test_fajr_and_isha_follow_the_night_portion_rules(
        string $label,
        float $lat,
        float $lon,
        string $zone,
        string $day,
        HighLatitudeRule $rule,
        string $fajr,
        string $isha,
    ): void {
        $tz = new DateTimeZone($zone);
        $t  = (new PrayerTimes($lat, $lon, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))
            ->withHighLatitudeRule($rule)
            ->getTimes(new DateTimeImmutable($day.' 12:00', $tz));

        $this->assertNear($fajr, $t['fajr'], "{$label} fajr");
        $this->assertNear($isha, $t['isha'], "{$label} isha");
    }

    public function test_angle_based_is_the_default_rule(): void
    {
        $tz  = new DateTimeZone('Europe/Oslo');
        $day = new DateTimeImmutable('2024-06-21 12:00', $tz);
        $pt  = new PrayerTimes(59.9139, 10.7522, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz);

        $this->assertSame(
            $pt->withHighLatitudeRule(HighLatitudeRule::AngleBased)->getTimes($day),
            $pt->getTimes($day),
        );
        $this->assertNear('02:21', $pt->getTimes($day)['fajr'], 'oslo default fajr');
    }

    public function test_rule_none_keeps_the_null_for_an_unreachable_angle(): void
    {
        $tz = new DateTimeZone('Europe/Oslo');
        $t  = (new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))
            ->withHighLatitudeRule(HighLatitudeRule::None)
            ->getTimes(new DateTimeImmutable('2024-08-05 12:00', $tz));

        $this->assertNull($t['fajr']);
        $this->assertNull($t['isha']);
        $this->assertNear('02:55', $t['sunrise'], 'sunrise is a real horizon event');
        $this->assertNear('22:45', $t['maghrib'], 'sunset');
    }

    public function test_rule_none_does_not_clamp_a_reached_angle(): void
    {
        $tz = new DateTimeZone('Europe/Berlin');
        $t  = (new PrayerTimes(48.1351, 11.5820, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))
            ->withHighLatitudeRule(HighLatitudeRule::None)
            ->getTimes(new DateTimeImmutable('2024-06-21 12:00', $tz));

        $this->assertNear('01:51', $t['fajr'], 'munich raw fajr');
        $this->assertNear('00:12', $t['isha'], 'munich raw isha');
    }

    public function test_midnight_sun_has_no_sunrise_or_maghrib_but_dhuhr_and_rule_based_fajr_isha(): void
    {
        $tz = new DateTimeZone('Europe/Oslo');
        $t  = (new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))
            ->getTimes(new DateTimeImmutable('2024-06-21 12:00', $tz));

        $this->assertNull($t['sunrise']);
        $this->assertNull($t['maghrib']);
        $this->assertNear('12:46', $t['dhuhr'], 'dhuhr (10:46 UTC)');
        $this->assertNear('03:10', $t['fajr'], 'fajr');
        $this->assertNear('22:10', $t['isha'], 'isha');
    }

    public function test_polar_night_keeps_the_real_twilight_times(): void
    {
        // Sun never rises, but 18 / 17 degrees below the horizon are reached: there is no real
        // night to bound them, so the raw values stay (reference: Fajr 06:29, Isha 16:44 local).
        $tz = new DateTimeZone('Europe/Oslo');
        $pt = new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz);
        $d  = new DateTimeImmutable('2024-12-21 12:00', $tz);

        foreach (HighLatitudeRule::cases() as $rule) {
            $t = $pt->withHighLatitudeRule($rule)->getTimes($d);
            $this->assertNull($t['sunrise'], $rule->value);
            $this->assertNull($t['maghrib'], $rule->value);
            $this->assertNear('06:29', $t['fajr'], $rule->value.' fajr');
            $this->assertNear('16:44', $t['isha'], $rule->value.' isha');
            $this->assertNear('11:42', $t['dhuhr'], $rule->value.' dhuhr');
        }
    }

    public function test_pole_uses_the_twelve_hour_reference_night_around_dhuhr(): void
    {
        // From the definitions alone: reference sunrise/sunset = dhuhr -/+ 6 h, night 12 h,
        // MWL 18 / 17 deg => Fajr = dhuhr - 6 h - 0.3 x 12 h, Isha = dhuhr + 6 h + (17/60) x 12 h.
        $utc = new DateTimeZone('UTC');
        $t   = (new PrayerTimes(90.0, 0.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc))
            ->getTimes(new DateTimeImmutable('2024-06-21 10:00', $utc));
        $noon = self::mins($t['dhuhr']);

        $this->assertNull($t['sunrise']);
        $this->assertNull($t['maghrib']);
        $this->assertNull($t['asr']);
        $this->assertEqualsWithDelta(self::wrap($noon - 6 * 60 - 216), self::mins((string) $t['fajr']), 1);
        $this->assertEqualsWithDelta(self::wrap($noon + 6 * 60 + 204), self::mins((string) $t['isha']), 1);
    }

    public function test_makkah_isha_stays_a_fixed_offset_and_is_null_without_sunset(): void
    {
        $tz = new DateTimeZone('Europe/Oslo');
        $pt = new PrayerTimes(59.9139, 10.7522, PrayerTimes::METHOD_MAKKAH, PrayerTimes::ASR_STANDARD, $tz);

        $t = $pt->getTimes(new DateTimeImmutable('2024-06-21 12:00', $tz));
        $this->assertNotNull($t['maghrib']);
        $this->assertSame(90, self::wrap(self::mins((string) $t['isha']) - self::mins($t['maghrib'])));
        // Fajr 18.5 deg is not reached at 60N in June: the angle-based fallback applies (night 5.163 h).
        $this->assertNear('02:18', $t['fajr'], 'oslo makkah fajr (18.5/60 of the night before sunrise)');

        $pole = (new PrayerTimes(80.0, 0.0, PrayerTimes::METHOD_MAKKAH, PrayerTimes::ASR_STANDARD, new DateTimeZone('UTC')))
            ->getTimes(new DateTimeImmutable('2025-06-21 12:00', new DateTimeZone('UTC')));
        $this->assertNull($pole['maghrib']);
        $this->assertNull($pole['isha']);
        $this->assertNotNull($pole['fajr']);
    }

    public function test_tehran_angle_maghrib_is_limited_by_the_rule_but_unchanged_in_normal_latitudes(): void
    {
        $tz    = new DateTimeZone('Europe/Oslo');
        $day   = new DateTimeImmutable('2024-08-05 12:00', $tz);
        $tehran = new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, $tz);

        // Tromso 5 Aug: the sun sets (22:45 local) but does not reach 4.5 deg below the horizon.
        // Angle-based: sunset + (4.5/60) x 4.264 h = 22:45 + 19.2 min = 23:04 local.
        $this->assertNull($tehran->withHighLatitudeRule(HighLatitudeRule::None)->getTimes($day)['maghrib']);
        $this->assertNear('23:04', $tehran->getTimes($day)['maghrib'], 'tehran angle maghrib');

        $tehranCity = PrayerTimes::forCity('tehran');
        $d          = new DateTimeImmutable('2026-03-21 10:00', new DateTimeZone('Asia/Tehran'));
        foreach (HighLatitudeRule::cases() as $rule) {
            $this->assertSame($tehranCity->withHighLatitudeRule(HighLatitudeRule::None)->getTimes($d), $tehranCity->withHighLatitudeRule($rule)->getTimes($d), $rule->value);
        }
    }

    public function test_ordinary_latitudes_are_identical_without_a_rule_and_with_angle_or_middle(): void
    {
        foreach ([['istanbul', '2025-06-21'], ['cairo', '2025-12-21'], ['jakarta', '2025-03-21']] as [$city, $day]) {
            $base = PrayerTimes::forCity($city, PrayerTimes::METHOD_MWL);
            $date = new DateTimeImmutable($day.' 12:00', new DateTimeZone('UTC'));
            $none = $base->withHighLatitudeRule(HighLatitudeRule::None)->getTimes($date);

            $this->assertSame($none, $base->getTimes($date), "{$city} default");
            $this->assertSame($none, $base->withHighLatitudeRule(HighLatitudeRule::NightMiddle)->getTimes($date), "{$city} middle");
        }
    }

    public function test_one_seventh_pulls_in_a_twilight_that_is_longer_than_a_seventh_of_the_night(): void
    {
        // Istanbul 21 June: sunrise 05:32, sunset 20:40 -> night to the next sunrise 8.88 h, a seventh
        // is 1.27 h (1 h 16 min): Fajr 05:32 - 1:16 = 04:16, Isha 20:40 + 1:16 = 21:56. The raw 18 / 17
        // degree values (03:24 / 22:38) lie outside that bound.
        $pt = PrayerTimes::forCity('istanbul', PrayerTimes::METHOD_MWL);
        $t  = $pt->withHighLatitudeRule(HighLatitudeRule::OneSeventh)->getTimes(new DateTimeImmutable('2025-06-21 12:00', new DateTimeZone('Europe/Istanbul')));

        $this->assertNear('04:16', $t['fajr'], 'istanbul one-seventh fajr');
        $this->assertNear('21:56', $t['isha'], 'istanbul one-seventh isha');
    }

    public function test_with_high_latitude_rule_is_immutable(): void
    {
        $pt    = PrayerTimes::forCity('tehran');
        $other = $pt->withHighLatitudeRule(HighLatitudeRule::None);

        $this->assertNotSame($pt, $other);
    }

    public function test_next_prayer_handles_times_that_fall_after_midnight(): void
    {
        // Reykjavik 21 June (UTC+0): sunset 00:04 and angle-based Isha 00:53 fall on the next calendar day.
        $tz = new DateTimeZone('Atlantic/Reykjavik');
        $pt = new PrayerTimes(64.1466, -21.9426, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz);

        $next = $pt->nextPrayer(new DateTimeImmutable('2024-06-21 23:30', $tz));
        $this->assertNotNull($next);
        $this->assertSame('maghrib', $next['name']);
        $this->assertSame('2024-06-22', $next['date']);
        $this->assertNear('00:04', $next['time'], 'maghrib');

        // Yesterday's Isha is the next prayer at 00:10 on 22 June.
        $isha = $pt->nextPrayer(new DateTimeImmutable('2024-06-22 00:10', $tz));
        $this->assertNotNull($isha);
        $this->assertSame('isha', $isha['name']);
        $this->assertSame('2024-06-22', $isha['date']);
        $this->assertNear('00:53', $isha['time'], 'isha');

        $fajr = $pt->nextPrayer(new DateTimeImmutable('2024-06-22 01:00', $tz));
        $this->assertNotNull($fajr);
        $this->assertSame('fajr', $fajr['name']);
        $this->assertSame('2024-06-22', $fajr['date']);
    }
    public function test_enum_night_fractions(): void
    {
        $this->assertSame(0.5, HighLatitudeRule::NightMiddle->nightFraction(18.0));
        $this->assertEqualsWithDelta(1 / 7, HighLatitudeRule::OneSeventh->nightFraction(18.0), 1e-12);
        $this->assertEqualsWithDelta(0.3, HighLatitudeRule::AngleBased->nightFraction(18.0), 1e-12);
        $this->assertEqualsWithDelta(15 / 60, HighLatitudeRule::AngleBased->nightFraction(15.0), 1e-12);
        $this->assertSame(['none', 'night_middle', 'one_seventh', 'angle_based'], array_map(static fn (HighLatitudeRule $r): string => $r->value, HighLatitudeRule::cases()));
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

    private function assertNear(string $expected, ?string $actual, string $what): void
    {
        $this->assertNotNull($actual, $what);
        $diff = abs(self::mins($expected) - self::mins($actual));
        $diff = min($diff, 1440 - $diff);
        $this->assertLessThanOrEqual(self::TOLERANCE, $diff, "{$what}: expected {$expected}, got {$actual}");
    }
}
