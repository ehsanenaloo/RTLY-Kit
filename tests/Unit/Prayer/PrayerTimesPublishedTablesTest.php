<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Prayer\PrayerTimes;

/**
 * Known-answer tests against prayer-time tables published by the authorities
 * that define each method (see docs/en/prayer-times.html; sources
 * accessed 2026-10-08). Published tables are minute-rounded and ours are
 * HH:MM, so every comparison allows +-2 minutes (observed maximum: 1 minute).
 *
 * Only combinations that were actually compared against an official table are
 * here. Karachi, MWL and ISNA have no reachable official city timetable and
 * are therefore deliberately absent. The Tehran table publishes no Asr and no
 * Isha, so the 14 degree Tehran Isha angle is not covered by these tests.
 */
final class PrayerTimesPublishedTablesTest extends TestCase
{
    private const TOLERANCE_MINUTES = 2;

    /**
     * Institute of Geophysics, University of Tehran (Calendar Center), "Awqat-e
     * Shar'i of provincial centres, Solar Hijri year 1405" (official PDFs, one
     * per city, https://calendar.ut.ac.ir/ , columns: Fajr, sunrise, noon,
     * sunset, Maghrib, legal midnight; no DST in Iran). Row format:
     * [city, date, fajr, sunrise, dhuhr, sunset, maghrib].
     *
     * @return array<string, array{string, string, string, string, string, string, string}>
     */
    public static function tehranGeophysicsTables(): array
    {
        return [
            'tehran 2026-03-21'  => ['tehran', '2026-03-21', '04:43', '06:07', '12:12', '18:17', '18:35'],
            'tehran 2026-06-21'  => ['tehran', '2026-06-21', '03:02', '04:49', '12:06', '19:23', '19:45'],
            'tehran 2026-09-23'  => ['tehran', '2026-09-23', '04:29', '05:53', '11:57', '18:00', '18:18'],
            'tehran 2026-12-21'  => ['tehran', '2026-12-21', '05:40', '07:10', '12:02', '16:55', '17:15'],
            'mashhad 2026-03-21' => ['mashhad', '2026-03-21', '04:10', '05:34', '11:39', '17:44', '18:02'],
            'mashhad 2026-06-21' => ['mashhad', '2026-06-21', '02:26', '04:14', '11:33', '18:52', '19:14'],
            'mashhad 2026-09-23' => ['mashhad', '2026-09-23', '03:55', '05:20', '11:24', '17:27', '17:46'],
            'mashhad 2026-12-21' => ['mashhad', '2026-12-21', '05:08', '06:39', '11:30', '16:20', '16:41'],
            'isfahan 2026-03-21' => ['isfahan', '2026-03-21', '04:45', '06:06', '12:11', '18:15', '18:33'],
            'isfahan 2026-06-21' => ['isfahan', '2026-06-21', '03:16', '04:56', '12:05', '19:14', '19:34'],
            'isfahan 2026-09-23' => ['isfahan', '2026-09-23', '04:31', '05:52', '11:56', '17:59', '18:16'],
            'isfahan 2026-12-21' => ['isfahan', '2026-12-21', '05:35', '07:01', '12:01', '17:01', '17:21'],
            'shiraz 2026-03-21'  => ['shiraz', '2026-03-21', '04:45', '06:03', '12:07', '18:12', '18:29'],
            'shiraz 2026-06-21'  => ['shiraz', '2026-06-21', '03:26', '05:00', '12:02', '19:03', '19:22'],
            'shiraz 2026-09-23'  => ['shiraz', '2026-09-23', '04:31', '05:49', '11:52', '17:56', '18:12'],
            'shiraz 2026-12-21'  => ['shiraz', '2026-12-21', '05:27', '06:51', '11:58', '17:05', '17:24'],
        ];
    }

    #[DataProvider('tehranGeophysicsTables')]
    public function test_tehran_method_matches_university_of_tehran_tables(
        string $city,
        string $day,
        string $fajr,
        string $sunrise,
        string $dhuhr,
        string $sunset,
        string $maghrib,
    ): void {
        $date = new DateTimeImmutable($day.' 10:00', new DateTimeZone('Asia/Tehran'));
        $t    = PrayerTimes::forCity($city, PrayerTimes::METHOD_TEHRAN)->getTimes($date);

        $this->assertWithinTolerance($fajr, $t['fajr'], "{$city} {$day} fajr (17.7 deg)");
        $this->assertWithinTolerance($sunrise, $t['sunrise'], "{$city} {$day} sunrise");
        $this->assertWithinTolerance($dhuhr, $t['dhuhr'], "{$city} {$day} dhuhr");
        $this->assertWithinTolerance($maghrib, $t['maghrib'], "{$city} {$day} maghrib (4.5 deg)");

        // The table's "sunset" column is the plain horizon event: MWL's Maghrib is exactly that.
        $mwl = PrayerTimes::forCity($city, PrayerTimes::METHOD_MWL)->getTimes($date);
        $this->assertWithinTolerance($sunset, $mwl['maghrib'], "{$city} {$day} sunset");
    }

    /**
     * Official Umm Al-Qura Calendar (KACST), yearly prayer-times view for Makkah,
     * Gregorian 2026: https://www.ummulqura.org.sa/en/prayer-times/makkah
     * Rows are outside Ramadan (Isha = Maghrib + 90 min). Row format:
     * [date, fajr, sunrise, dhuhr, asr, maghrib, isha].
     *
     * @return array<string, array{string, string, string, string, string, string, string}>
     */
    public static function ummAlQuraMakkahTable(): array
    {
        return [
            '2026-03-21' => ['2026-03-21', '05:08', '06:24', '12:28', '15:53', '18:33', '20:03'],
            '2026-06-21' => ['2026-06-21', '04:12', '05:39', '12:23', '15:43', '19:06', '20:36'],
            '2026-09-23' => ['2026-09-23', '04:54', '06:09', '12:14', '15:38', '18:17', '19:47'],
            '2026-10-08' => ['2026-10-08', '04:59', '06:13', '12:09', '15:31', '18:03', '19:33'],
            '2026-12-21' => ['2026-12-21', '05:33', '06:53', '12:19', '15:23', '17:44', '19:14'],
        ];
    }

    #[DataProvider('ummAlQuraMakkahTable')]
    public function test_makkah_method_matches_umm_al_qura_calendar(
        string $day,
        string $fajr,
        string $sunrise,
        string $dhuhr,
        string $asr,
        string $maghrib,
        string $isha,
    ): void {
        $date = new DateTimeImmutable($day.' 10:00', new DateTimeZone('Asia/Riyadh'));
        $t    = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)->getTimes($date);

        $this->assertWithinTolerance($fajr, $t['fajr'], "{$day} fajr (18.5 deg)");
        $this->assertWithinTolerance($sunrise, $t['sunrise'], "{$day} sunrise");
        $this->assertWithinTolerance($dhuhr, $t['dhuhr'], "{$day} dhuhr");
        $this->assertWithinTolerance($asr, $t['asr'], "{$day} asr");
        $this->assertWithinTolerance($maghrib, $t['maghrib'], "{$day} maghrib");
        $this->assertWithinTolerance($isha, $t['isha'], "{$day} isha (maghrib + 90 min)");
    }

    /**
     * Official Umm Al-Qura Calendar (KACST), same yearly view: during Ramadan
     * 1447 (about 2026-02-18 .. 2026-03-19) Isha is Maghrib + 120 minutes, on
     * either side of it Maghrib + 90. Row format: [date, maghrib, isha, gap].
     *
     * @return array<string, array{string, string, string, int}>
     */
    public static function ummAlQuraRamadanIsha(): array
    {
        return [
            'ramadan 2026-02-20'       => ['2026-02-20', '18:21', '20:21', 120],
            'ramadan 2026-03-01'       => ['2026-03-01', '18:25', '20:25', 120],
            'after ramadan 2026-03-21' => ['2026-03-21', '18:33', '20:03', 90],
        ];
    }

    #[DataProvider('ummAlQuraRamadanIsha')]
    public function test_makkah_isha_is_120_minutes_after_maghrib_in_ramadan_only(
        string $day,
        string $maghrib,
        string $isha,
        int $gap,
    ): void {
        $date = new DateTimeImmutable($day.' 10:00', new DateTimeZone('Asia/Riyadh'));
        $t    = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)->getTimes($date);

        $this->assertNotNull($t['maghrib']);
        $this->assertNotNull($t['isha']);
        $this->assertWithinTolerance($maghrib, $t['maghrib'], "{$day} maghrib");
        $this->assertWithinTolerance($isha, $t['isha'], "{$day} isha");
        $this->assertSame($gap, self::mins($t['isha']) - self::mins($t['maghrib']), "{$day} Maghrib to Isha gap");
    }

    public function test_makkah_ramadan_rule_follows_the_umm_al_qura_month_boundaries(): void
    {
        $tz     = new DateTimeZone('Asia/Riyadh');
        $makkah = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH);

        $first = Hijri::create(1447, 9, 1, 10, 0, 0, $tz);
        $last  = Hijri::create(1447, 9, Hijri::daysInMonth(1447, 9), 10, 0, 0, $tz);

        $expected = [
            [$first->subDays(1), 90],   // 29/30 Sha'ban
            [$first, 120],              // 1 Ramadan
            [$last, 120],               // last day of Ramadan
            [$last->addDays(1), 90],    // 1 Shawwal
        ];

        foreach ($expected as [$day, $gap]) {
            $t = $makkah->getTimes($day->toGregorian());
            $this->assertNotNull($t['maghrib']);
            $this->assertNotNull($t['isha']);
            $this->assertSame(
                $gap,
                self::mins($t['isha']) - self::mins($t['maghrib']),
                $day->toGregorian()->format('Y-m-d').' ('.$day->toDateString().')',
            );
        }
    }

    public function test_makkah_ramadan_rule_does_not_change_other_methods(): void
    {
        $date = new DateTimeImmutable('2026-02-20 10:00', new DateTimeZone('Asia/Riyadh'));
        $mwl  = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MWL)->getTimes($date);

        // MWL Isha is an angle (17 deg), not a fixed offset, so the gap is not 120.
        $this->assertNotNull($mwl['maghrib']);
        $this->assertNotNull($mwl['isha']);
        $this->assertNotSame(120, self::mins($mwl['isha']) - self::mins($mwl['maghrib']));
    }

    public function test_makkah_isha_uses_the_calculators_local_date_for_ramadan(): void
    {
        // 2026-03-19 23:30 UTC is already 2026-03-20 02:30 in Riyadh: 1 Shawwal, so +90.
        $date = new DateTimeImmutable('2026-03-19 23:30', new DateTimeZone('UTC'));
        $t    = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)->getTimes($date);

        $this->assertNotNull($t['maghrib']);
        $this->assertNotNull($t['isha']);
        $this->assertSame(90, self::mins($t['isha']) - self::mins($t['maghrib']));
    }

    /**
     * Egyptian Dar al-Ifta, monthly prayer-time table for Cairo (October 2026):
     * https://www.dar-alifta.org/ar/prayer . Cairo is on DST on 1 and 8 October
     * and back on standard time on 31 October, so this also checks the DST
     * handling. The page does not name the angle authority (Egyptian General
     * Authority of Survey is assumed, not confirmed). Standard (non-Hanafi) Asr.
     * Row format: [date, fajr, sunrise, dhuhr, asr, maghrib, isha].
     *
     * @return array<string, array{string, string, string, string, string, string, string}>
     */
    public static function darAlIftaCairoTable(): array
    {
        return [
            '2026-10-01' => ['2026-10-01', '05:22', '06:48', '12:45', '16:08', '18:41', '19:58'],
            '2026-10-08' => ['2026-10-08', '05:26', '06:52', '12:42', '16:02', '18:32', '19:49'],
            '2026-10-31' => ['2026-10-31', '04:40', '06:08', '11:39', '14:45', '17:09', '18:27'],
        ];
    }

    #[DataProvider('darAlIftaCairoTable')]
    public function test_egypt_method_matches_dar_al_ifta_cairo_table(
        string $day,
        string $fajr,
        string $sunrise,
        string $dhuhr,
        string $asr,
        string $maghrib,
        string $isha,
    ): void {
        $date = new DateTimeImmutable($day.' 10:00', new DateTimeZone('Africa/Cairo'));
        $t    = PrayerTimes::forCity('cairo', PrayerTimes::METHOD_EGYPT)->getTimes($date);

        $this->assertWithinTolerance($fajr, $t['fajr'], "{$day} fajr (19.5 deg)");
        $this->assertWithinTolerance($sunrise, $t['sunrise'], "{$day} sunrise");
        $this->assertWithinTolerance($dhuhr, $t['dhuhr'], "{$day} dhuhr");
        $this->assertWithinTolerance($asr, $t['asr'], "{$day} asr");
        $this->assertWithinTolerance($maghrib, $t['maghrib'], "{$day} maghrib");
        $this->assertWithinTolerance($isha, $t['isha'], "{$day} isha (17.5 deg)");
    }

    private static function mins(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function assertWithinTolerance(string $published, ?string $actual, string $what): void
    {
        $this->assertNotNull($actual, $what);
        $this->assertLessThanOrEqual(
            self::TOLERANCE_MINUTES,
            abs(self::mins($published) - self::mins($actual)),
            "{$what}: published {$published}, computed {$actual}",
        );
    }
}
