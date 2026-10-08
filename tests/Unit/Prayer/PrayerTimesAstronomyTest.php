<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Prayer\HighLatitudeRule;
use RtlyKit\Prayer\PrayerTimes;

/**
 * Known-answer tests for the solar model at epochs and places the published
 * tables do not reach, plus configuration, ordering and polar edge cases.
 *
 * Three independent sources of expected values:
 *  - epochs 300..9990 (UTC): a separate port of the NOAA Solar Calculator
 *    formulas (gml.noaa.gov/grad/solcalc/calcdetails.html, Julian day by the
 *    Meeus chapter 7 algorithm, event times iterated to convergence instead of
 *    the library's two passes). All 144 values of the grid matched the
 *    library; the 72 below are pinned exactly (minute-rounded, MWL 18/17,
 *    standard Asr), so a wrong coefficient of the long-term terms (the t^2
 *    terms only matter far from 2000) changes the result;
 *  - 1962-1990 (UTC) and the city table: the Astronomical Almanac low-
 *    precision Sun (a different series, accurate to ~0.01 degree in 1950-2050),
 *    compared within 2 minutes;
 *  - the polar days: the definitions of sunrise (altitude -0.833 degrees) and
 *    of the 12 hour reference night documented on HighLatitudeRule.
 */
final class PrayerTimesAstronomyTest extends TestCase
{
    /**
     * [lat, lon, date, fajr, sunrise, dhuhr, asr, maghrib, isha] in UTC.
     *
     * @return array<string, array{float, float, string, string, string, string, string, string, string}>
     */
    public static function epochs(): array
    {
        return [
            'mecca 1500-06-21'   => [21.4225, 39.8262, '1500-06-21', '01:12', '02:38', '09:21', '12:41', '16:04', '17:25'],
            'newyork 1500-06-21' => [40.7128, -74.006, '1500-06-21', '07:17', '09:23', '16:57', '20:57', '00:30', '02:27'],
            'sydney 1500-06-21'  => [-33.8688, 151.2093, '1500-06-21', '19:29', '20:59', '01:56', '04:34', '06:52', '08:17'],
            'mecca 2400-03-21'   => [21.4225, 39.8262, '2400-03-21', '02:09', '03:23', '09:27', '12:52', '15:32', '16:42'],
            'newyork 2400-03-21' => [40.7128, -74.006, '2400-03-21', '09:25', '10:57', '17:03', '20:29', '23:09', '00:36'],
            'sydney 2400-03-21'  => [-33.8688, 151.2093, '2400-03-21', '18:35', '19:59', '02:02', '05:29', '08:05', '09:23'],
            'mecca 3000-12-21'   => [21.4225, 39.8262, '3000-12-21', '02:32', '03:51', '09:17', '12:21', '14:42', '15:57'],
            'newyork 3000-12-21' => [40.7128, -74.006, '3000-12-21', '10:35', '12:14', '16:52', '19:13', '21:30', '23:04'],
            'sydney 3000-12-21'  => [-33.8688, 151.2093, '3000-12-21', '16:55', '18:39', '01:51', '05:36', '09:03', '10:40'],
            'mecca 5000-09-23'   => [21.4225, 39.8262, '5000-09-23', '02:01', '03:15', '09:17', '12:41', '15:19', '16:28'],
            'newyork 5000-09-23' => [40.7128, -74.006, '5000-09-23', '09:19', '10:51', '16:52', '20:15', '22:52', '00:18'],
            'sydney 5000-09-23'  => [-33.8688, 151.2093, '5000-09-23', '18:22', '19:45', '01:51', '05:20', '07:58', '09:16'],
            'mecca 7500-06-21'   => [21.4225, 39.8262, '7500-06-21', '01:21', '02:46', '09:28', '12:46', '16:10', '17:29'],
            'newyork 7500-06-21' => [40.7128, -74.006, '7500-06-21', '07:29', '09:33', '17:03', '21:03', '00:33', '02:28'],
            'sydney 7500-06-21'  => [-33.8688, 151.2093, '7500-06-21', '19:34', '21:03', '02:02', '04:43', '07:01', '08:25'],
            'mecca 9000-12-21'   => [21.4225, 39.8262, '9000-12-21', '02:32', '03:51', '09:17', '12:22', '14:43', '15:58'],
            'newyork 9000-12-21' => [40.7128, -74.006, '9000-12-21', '10:34', '12:12', '16:52', '19:15', '21:33', '23:06'],
            'sydney 9000-12-21'  => [-33.8688, 151.2093, '9000-12-21', '16:59', '18:42', '01:51', '05:36', '09:01', '10:37'],
            'mecca 9900-06-21'   => [21.4225, 39.8262, '9900-06-21', '01:19', '02:44', '09:25', '12:43', '16:07', '17:26'],
            'newyork 9900-06-21' => [40.7128, -74.006, '9900-06-21', '07:28', '09:31', '17:01', '21:00', '00:30', '02:24'],
            'sydney 9900-06-21'  => [-33.8688, 151.2093, '9900-06-21', '19:31', '21:00', '02:00', '04:41', '06:59', '08:23'],
            'mecca 0300-03-21'   => [21.4225, 39.8262, '0300-03-21', '02:11', '03:25', '09:28', '12:53', '15:32', '16:42'],
            'newyork 0300-03-21' => [40.7128, -74.006, '0300-03-21', '09:28', '10:59', '17:04', '20:29', '23:09', '00:35'],
            'sydney 0300-03-21'  => [-33.8688, 151.2093, '0300-03-21', '18:35', '19:58', '02:03', '05:30', '08:07', '09:25'],
            'mecca 2025-11-03' => [21.4225, 39.8262, '2025-11-03', '02:09', '03:25', '09:04', '12:19', '14:43', '15:55'],
            'oslo 2025-11-03' => [59.9139, 10.7522, '2025-11-03', '04:21', '06:44', '11:01', '12:47', '15:16', '17:31'],
            'sydney 2025-11-03' => [-33.8688, 151.2093, '2025-11-03', '17:21', '18:53', '01:39', '05:21', '08:25', '09:51'],
            'mecca 2025-02-11' => [21.4225, 39.8262, '2025-02-11', '02:38', '03:54', '09:35', '12:51', '15:17', '16:27'],
            'oslo 2025-02-11' => [59.9139, 10.7522, '2025-02-11', '04:44', '07:05', '11:31', '13:26', '15:58', '18:12'],
            'sydney 2025-02-11' => [-33.8688, 151.2093, '2025-02-11', '17:55', '19:26', '02:09', '05:51', '08:52', '10:17'],
            'mecca 9900-04-05' => [21.4225, 39.8262, '9900-04-05', '01:43', '02:58', '09:12', '12:37', '15:25', '16:36'],
            'oslo 9900-04-05' => [59.9139, 10.7522, '9900-04-05', '01:33', '04:18', '11:08', '14:43', '18:00', '20:34'],
            'sydney 9900-04-05' => [-33.8688, 151.2093, '9900-04-05', '18:36', '19:59', '01:46', '05:04', '07:33', '08:51'],
            'mecca 9900-10-04' => [21.4225, 39.8262, '9900-10-04', '02:13', '03:27', '09:19', '12:41', '15:12', '16:22'],
            'oslo 9900-10-04' => [59.9139, 10.7522, '9900-10-04', '03:37', '05:56', '11:16', '13:49', '16:34', '18:44'],
            'sydney 9900-10-04' => [-33.8688, 151.2093, '9900-10-04', '18:07', '19:32', '01:54', '05:30', '08:16', '09:37'],
            'mecca 9500-11-03' => [21.4225, 39.8262, '9500-11-03', '02:19', '03:35', '09:12', '12:26', '14:50', '16:01'],
            'oslo 9500-11-03' => [59.9139, 10.7522, '9500-11-03', '04:36', '07:01', '11:09', '12:49', '15:15', '17:32'],
            'sydney 9500-11-03' => [-33.8688, 151.2093, '9500-11-03', '17:25', '18:58', '01:47', '05:30', '08:36', '10:04'],
            'mecca 9500-02-11' => [21.4225, 39.8262, '9500-02-11', '02:26', '03:41', '09:24', '12:40', '15:06', '16:17'],
            'oslo 9500-02-11' => [59.9139, 10.7522, '9500-02-11', '04:28', '06:49', '11:20', '13:18', '15:52', '18:05'],
            'sydney 9500-02-11' => [-33.8688, 151.2093, '9500-02-11', '17:46', '19:17', '01:58', '05:39', '08:39', '10:03'],
            'mecca 9990-01-02' => [21.4225, 39.8262, '9990-01-02', '02:35', '03:54', '09:22', '12:28', '14:49', '16:03'],
            'oslo 9990-01-02' => [59.9139, 10.7522, '9990-01-02', '05:24', '08:03', '11:18', '12:22', '14:33', '17:04'],
            'sydney 9990-01-02' => [-33.8688, 151.2093, '9990-01-02', '17:07', '18:49', '01:56', '05:41', '09:03', '10:38'],
            'tehran 2025-11-03' => [35.6892, 51.389, '2025-11-03', '01:32', '02:58', '08:18', '11:15', '13:37', '14:59'],
            'cairo 2025-11-03' => [30.0444, 31.2357, '2025-11-03', '02:49', '04:11', '09:39', '12:44', '15:06', '16:23'],
            'jakarta 2025-11-03' => [-6.2088, 106.8456, '2025-11-03', '21:14', '22:26', '04:36', '07:53', '10:46', '11:54'],
            'london 2025-11-03' => [51.5074, -0.1278, '2025-11-03', '05:04', '06:58', '11:44', '14:03', '16:30', '18:17'],
            'toronto 2025-11-03' => [43.6532, -79.3832, '2025-11-03', '10:19', '11:56', '17:01', '19:41', '22:05', '23:37'],
            'lagos 2025-11-03' => [6.5244, 3.3792, '2025-11-03', '04:22', '05:34', '11:30', '14:52', '17:26', '18:34'],
            'dhaka 2025-11-03' => [23.8103, 90.4125, '2025-11-03', '22:48', '00:05', '05:42', '08:54', '11:18', '12:31'],
            'lima 2025-11-03' => [-12.0464, -77.0428, '2025-11-03', '09:21', '10:35', '16:52', '20:03', '23:09', '00:18'],
            'tehran 2025-02-11' => [35.6892, 51.389, '2025-02-11', '01:59', '03:26', '08:49', '11:49', '14:12', '15:33'],
            'cairo 2025-02-11' => [30.0444, 31.2357, '2025-02-11', '03:18', '04:38', '10:09', '13:17', '15:41', '16:57'],
            'jakarta 2025-02-11' => [-6.2088, 106.8456, '2025-02-11', '21:45', '22:57', '05:07', '08:22', '11:16', '12:24'],
            'london 2025-02-11' => [51.5074, -0.1278, '2025-02-11', '05:29', '07:22', '12:15', '14:40', '17:09', '18:55'],
            'toronto 2025-02-11' => [43.6532, -79.3832, '2025-02-11', '10:45', '12:21', '17:32', '20:17', '22:43', '00:14'],
            'lagos 2025-02-11' => [6.5244, 3.3792, '2025-02-11', '04:53', '06:04', '12:01', '15:22', '17:58', '19:05'],
            'dhaka 2025-02-11' => [23.8103, 90.4125, '2025-02-11', '23:18', '00:34', '06:13', '09:27', '11:51', '13:04'],
            'lima 2025-02-11' => [-12.0464, -77.0428, '2025-02-11', '09:54', '11:07', '17:22', '20:31', '23:38', '00:47'],
        ];
    }

    #[DataProvider('epochs')]
    public function test_solar_model_matches_the_independent_port_at_distant_epochs(
        float $lat,
        float $lon,
        string $day,
        string $fajr,
        string $sunrise,
        string $dhuhr,
        string $asr,
        string $maghrib,
        string $isha,
    ): void {
        $utc = new DateTimeZone('UTC');
        $t   = (new PrayerTimes($lat, $lon, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc))
            ->getTimes(new DateTimeImmutable($day.' 12:00', $utc));

        $this->assertSame(
            ['fajr' => $fajr, 'sunrise' => $sunrise, 'dhuhr' => $dhuhr, 'asr' => $asr, 'maghrib' => $maghrib, 'isha' => $isha],
            $t,
        );
    }

    /**
     * Before 1999 the mean longitude and anomaly are negative before reduction.
     * [lat, lon, date, sunrise, dhuhr, sunset] in UTC (Almanac Sun).
     *
     * @return array<string, array{float, float, string, string, string, string}>
     */
    public static function historicalDays(): array
    {
        return [
            'mecca 1962-06-21'    => [21.4225, 39.8262, '1962-06-21', '02:39', '09:22', '16:05'],
            'mecca 1975-12-01'    => [21.4225, 39.8262, '1975-12-01', '03:42', '09:10', '14:37'],
            'mecca 1990-03-05'    => [21.4225, 39.8262, '1990-03-05', '03:38', '09:32', '15:26'],
            'oslo 1962-06-21'     => [59.9139, 10.7522, '1962-06-21', '01:53', '11:19', '20:44'],
            'oslo 1975-12-01'     => [59.9139, 10.7522, '1975-12-01', '07:50', '11:06', '14:21'],
            'oslo 1990-03-05'     => [59.9139, 10.7522, '1990-03-05', '06:04', '11:29', '16:53'],
            'jakarta 1962-06-21'  => [-6.2088, 106.8456, '1962-06-21', '23:01', '04:54', '10:47'],
            'jakarta 1975-12-01'  => [-6.2088, 106.8456, '1975-12-01', '22:28', '04:41', '10:55'],
            'jakarta 1990-03-05'  => [-6.2088, 106.8456, '1990-03-05', '22:58', '05:04', '11:10'],
        ];
    }

    #[DataProvider('historicalDays')]
    public function test_negative_angle_reduction_before_1999(float $lat, float $lon, string $day, string $sunrise, string $dhuhr, string $sunset): void
    {
        $utc = new DateTimeZone('UTC');
        $t   = (new PrayerTimes($lat, $lon, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc))
            ->getTimes(new DateTimeImmutable($day.' 12:00', $utc));

        $this->assertNear($sunrise, $t['sunrise'], 2, 'sunrise');
        $this->assertNear($dhuhr, $t['dhuhr'], 2, 'dhuhr');
        $this->assertNear($sunset, $t['maghrib'], 2, 'sunset');
    }

    /**
     * Every forCity() entry, 2025-03-21 (MWL): [sunrise, dhuhr, sunset] local,
     * Almanac Sun at the city coordinates (Cairo has no DST until 25 April).
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function cities(): array
    {
        return [
            'tehran'   => ['tehran', '06:06', '12:12', '18:17'],
            'mashhad'  => ['mashhad', '05:33', '11:39', '17:44'],
            'isfahan'  => ['isfahan', '06:05', '12:10', '18:16'],
            'shiraz'   => ['shiraz', '06:02', '12:07', '18:12'],
            'tabriz'   => ['tabriz', '06:26', '12:32', '18:38'],
            'qom'      => ['qom', '06:08', '12:14', '18:19'],
            'mecca'    => ['mecca', '06:24', '12:28', '18:32'],
            'medina'   => ['medina', '06:24', '12:29', '18:33'],
            'riyadh'   => ['riyadh', '05:56', '12:00', '18:05'],
            'istanbul' => ['istanbul', '07:05', '13:11', '19:17'],
            'cairo'    => ['cairo', '05:57', '12:02', '18:07'],
            'dubai'    => ['dubai', '06:21', '12:26', '18:31'],
            'baghdad'  => ['baghdad', '06:04', '12:10', '18:15'],
            'jakarta'  => ['jakarta', '05:57', '12:00', '18:03'],
        ];
    }

    #[DataProvider('cities')]
    public function test_every_built_in_city_has_its_own_coordinates_and_zone(string $city, string $sunrise, string $dhuhr, string $sunset): void
    {
        $t = PrayerTimes::forCity($city, PrayerTimes::METHOD_MWL)->getTimes(new DateTimeImmutable('2025-03-21 06:00', new DateTimeZone('UTC')));

        $this->assertNear($sunrise, $t['sunrise'], 2, "{$city} sunrise");
        $this->assertNear($dhuhr, $t['dhuhr'], 2, "{$city} dhuhr");
        $this->assertNear($sunset, $t['maghrib'], 2, "{$city} sunset");
    }

    public function test_for_city_ignores_case_and_surrounding_whitespace(): void
    {
        $day = new DateTimeImmutable('2025-03-21 10:00', new DateTimeZone('Asia/Tehran'));

        $this->assertSame(PrayerTimes::forCity('tehran')->getTimes($day), PrayerTimes::forCity("  TeHrAn \n")->getTimes($day));
    }

    /* ---------------- messages ---------------- */

    public function test_unknown_method_message_lists_the_available_methods(): void
    {
        try {
            new PrayerTimes(35.0, 51.0, 'Nope');
            $this->fail('expected exception');
        } catch (InvalidPrayerConfigException $e) {
            $this->assertSame('Unknown calculation method: Nope. Available: MWL, ISNA, Egypt, Makkah, Karachi, Tehran', $e->getMessage());
        }
    }

    public function test_invalid_tune_messages_name_the_key_and_the_limits(): void
    {
        $pt = PrayerTimes::forCity('tehran');

        try {
            $pt->withTune(['zuhr' => 1]);
            $this->fail('expected exception');
        } catch (InvalidPrayerConfigException $e) {
            $this->assertSame('Unknown tune key: zuhr. Available: fajr, sunrise, dhuhr, asr, maghrib, isha', $e->getMessage());
        }

        try {
            $pt->withTune(['fajr' => 31]);
            $this->fail('expected exception');
        } catch (InvalidPrayerConfigException $e) {
            $this->assertSame('Tune for fajr must be an integer from -30 to 30 minutes', $e->getMessage());
        }
    }

    /* ---------------- polar edge cases of the rule ---------------- */

    public function test_last_day_before_the_polar_night_keeps_the_raw_twilight_times(): void
    {
        // Tromso 2024-11-26 (CET): sunrise 11:07, sunset 11:56, but no sunrise the next day, so there
        // is no real night to bound Fajr/Isha by. Raw 18/17 deg times (Almanac Sun): Fajr 05:52,
        // Isha 17:00; Tehran-method Maghrib (4.5 deg) 14:00, Fajr 17.7 deg 05:55, Isha 14 deg 16:23.
        $tz  = new DateTimeZone('Europe/Oslo');
        $day = new DateTimeImmutable('2024-11-26 12:00', $tz);

        foreach (HighLatitudeRule::cases() as $rule) {
            $mwl = (new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))->withHighLatitudeRule($rule)->getTimes($day);
            $this->assertNear('11:07', $mwl['sunrise'], 3, $rule->value.' sunrise');
            $this->assertNear('11:56', $mwl['maghrib'], 3, $rule->value.' sunset');
            $this->assertNear('05:52', $mwl['fajr'], 3, $rule->value.' fajr');
            $this->assertNear('17:00', $mwl['isha'], 3, $rule->value.' isha');

            $teh = (new PrayerTimes(69.6492, 18.9553, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, $tz))->withHighLatitudeRule($rule)->getTimes($day);
            $this->assertNear('05:55', $teh['fajr'], 3, $rule->value.' tehran fajr');
            $this->assertNear('16:23', $teh['isha'], 3, $rule->value.' tehran isha');
            $this->assertNear('14:00', $teh['maghrib'], 3, $rule->value.' tehran maghrib');
        }
    }

    public function test_tehran_maghrib_beyond_the_night_portion_is_pulled_in(): void
    {
        // 62 N, 10 E, 2024-06-21 (CEST): sunset 23:14, the sun reaches 4.5 deg below the horizon only
        // at 01:05, 111 minutes later. Night to the next sunrise 4.26 h; angle-based bound
        // 4.5/60 x 4.26 h = 19.2 min => 23:33. Middle of the night (2.13 h) keeps the raw time.
        $tz = new DateTimeZone('Europe/Oslo');
        $pt = new PrayerTimes(62.0, 10.0, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, $tz);
        $d  = new DateTimeImmutable('2024-06-21 12:00', $tz);

        $this->assertNear('23:33', $pt->getTimes($d)['maghrib'], 3, 'angle-based');
        $this->assertNear('01:05', $pt->withHighLatitudeRule(HighLatitudeRule::None)->getTimes($d)['maghrib'], 3, 'none');
        $this->assertNear('01:05', $pt->withHighLatitudeRule(HighLatitudeRule::NightMiddle)->getTimes($d)['maghrib'], 3, 'middle');
    }

    /* ---------------- nextPrayer ordering ---------------- */

    public function test_next_prayer_is_strictly_after_the_current_minute(): void
    {
        $tz = new DateTimeZone('Asia/Jakarta');
        $pt = PrayerTimes::forCity('jakarta', PrayerTimes::METHOD_MWL);
        $t  = $pt->getTimes(new DateTimeImmutable('2025-03-21 10:00', $tz));

        $this->assertSame('12:00', $t['dhuhr']);
        $this->assertSame('dhuhr', $pt->nextPrayer(new DateTimeImmutable('2025-03-21 11:59:59', $tz))['name'] ?? null);
        $at = $pt->nextPrayer(new DateTimeImmutable('2025-03-21 12:00:30', $tz));
        $this->assertSame('asr', $at['name'] ?? null);
        $this->assertSame($t['asr'], $at['time'] ?? null);
    }

    public function test_next_prayer_prefers_the_earlier_name_on_a_tie(): void
    {
        // Jakarta ISNA 2025-03-21: Maghrib 18:03, Isha 19:00 (57 minutes apart); tuned to meet at 18:32.
        $tz = new DateTimeZone('Asia/Jakarta');
        $pt = PrayerTimes::forCity('jakarta', PrayerTimes::METHOD_ISNA)->withTune(['maghrib' => 29, 'isha' => -28]);
        $t  = $pt->getTimes(new DateTimeImmutable('2025-03-21 10:00', $tz));

        $this->assertSame('18:32', $t['maghrib']);
        $this->assertSame('18:32', $t['isha']);

        $next = $pt->nextPrayer(new DateTimeImmutable('2025-03-21 18:00', $tz));
        $this->assertNotNull($next);
        $this->assertSame('maghrib', $next['name']);
        $this->assertSame('18:32', $next['time']);
    }

    public function test_next_prayer_at_the_edges_of_the_supported_years(): void
    {
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(21.4225, 39.8262, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);

        $first = $pt->nextPrayer(new DateTimeImmutable('0001-01-01 00:30', $utc));
        $this->assertSame(['name' => 'fajr', 'time' => '02:45', 'date' => '0001-01-01'], $first);

        $this->assertNull($pt->nextPrayer(new DateTimeImmutable('9999-12-31 23:00', $utc)));
    }

    public function test_enum_none_allows_the_whole_night(): void
    {
        $this->assertSame(1.0, HighLatitudeRule::None->nightFraction(18.0));
    }

    private static function mins(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function assertNear(string $expected, ?string $actual, int $tolerance, string $what): void
    {
        $this->assertNotNull($actual, $what);
        $diff = abs(self::mins($expected) - self::mins($actual));
        $diff = min($diff, 1440 - $diff);
        $this->assertLessThanOrEqual($tolerance, $diff, "{$what}: expected {$expected}, got {$actual}");
    }
}
