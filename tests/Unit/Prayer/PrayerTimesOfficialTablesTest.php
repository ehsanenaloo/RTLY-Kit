<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Prayer\PrayerTimes;

/**
 * Additional known-answer tests against official timetables, complementing
 * PrayerTimesPublishedTablesTest (all sources accessed 2026-10-08, see
 * the data verification notes (October 2026) for the full method, what is
 * and is not covered, and the data-extraction caveat).
 *
 * Scope, stated honestly:
 *  - Diyanet (Turkey) publishes imsak (Fajr) and yatsi (Isha) with the same
 *    18 / 17 degree twilight angles as the Muslim World League method. The
 *    comparison therefore checks the MWL angles, but it is NOT a table issued
 *    by the Muslim World League itself (none is reachable).
 *  - JAKIM (Malaysia) publishes Fajr and Isha at 18 / 18 degrees, the angles of
 *    the Karachi method. This checks the angle pair, NOT a table issued by the
 *    University of Islamic Sciences, Karachi (none is reachable). JAKIM adds its
 *    own small precautionary offsets and its zone reference point is not
 *    published on the page, so only Fajr and Isha are compared.
 *  - There is no reachable official ISNA (15 / 15) table and no official table
 *    with the Tehran 14 degree Isha, so those are not covered here.
 * Published tables are minute-rounded and ours are HH:MM, so every direct
 * comparison allows +-2 minutes.
 */
final class PrayerTimesOfficialTablesTest extends TestCase
{
    private const TOLERANCE_MINUTES = 2;

    /**
     * Presidency of Religious Affairs (Diyanet), monthly table 8 Oct - 7 Nov 2026:
     * https://namazvakitleri.diyanet.gov.tr/tr-TR/9541/istanbul-icin-namaz-vakti
     * (Istanbul), .../9206/ankara-icin-namaz-vakti (Ankara),
     * .../9560/izmir-icin-namaz-vakti (Izmir). Europe/Istanbul (UTC+3, no DST).
     * Coordinates used: Istanbul 41.0082 N 28.9784 E (the forCity value),
     * Ankara 39.9334 N 32.8597 E, Izmir 38.4237 N 27.1428 E (approximate public
     * city-centre values; Diyanet's own reference points are not published on
     * the page). Column order of the published rows:
     * [city, lat, lng, date, imsak, gunes, ogle, ikindi, aksam, yatsi].
     *
     * @return array<string, array{string, float, float, string, string, string, string, string, string, string}>
     */
    public static function diyanetTables(): array
    {
        $istanbul = ['istanbul', 41.0082, 28.9784];
        $ankara   = ['ankara', 39.9334, 32.8597];
        $izmir    = ['izmir', 38.4237, 27.1428];

        $rows = [
            [...$istanbul, '2026-10-08', '05:36', '07:01', '12:57', '16:08', '18:43', '20:02'],
            [...$istanbul, '2026-10-15', '05:44', '07:08', '12:55', '16:00', '18:32', '19:51'],
            [...$istanbul, '2026-10-24', '05:53', '07:18', '12:53', '15:49', '18:19', '19:38'],
            [...$istanbul, '2026-11-07', '06:08', '07:35', '12:53', '15:35', '18:01', '19:22'],
            [...$ankara, '2026-10-08', '05:22', '06:44', '12:41', '15:54', '18:28', '19:46'],
            [...$ankara, '2026-10-15', '05:29', '06:52', '12:39', '15:45', '18:17', '19:35'],
            [...$ankara, '2026-10-24', '05:38', '07:01', '12:38', '15:35', '18:05', '19:23'],
            [...$ankara, '2026-11-07', '05:52', '07:17', '12:37', '15:22', '17:48', '19:08'],
            [...$izmir, '2026-10-08', '05:46', '07:06', '13:04', '16:18', '18:52', '20:08'],
            [...$izmir, '2026-10-15', '05:52', '07:13', '13:02', '16:10', '18:42', '19:58'],
            [...$izmir, '2026-10-24', '06:00', '07:22', '13:01', '16:01', '18:29', '19:46'],
            [...$izmir, '2026-11-07', '06:14', '07:37', '13:00', '15:48', '18:13', '19:31'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out["{$row[0]} {$row[3]}"] = $row;
        }

        return $out;
    }

    #[DataProvider('diyanetTables')]
    public function test_mwl_angles_match_diyanet_imsak_and_yatsi(
        string $city,
        float $lat,
        float $lng,
        string $day,
        string $imsak,
        string $gunes,
        string $ogle,
        string $ikindi,
        string $aksam,
        string $yatsi,
    ): void {
        $t = $this->mwl($lat, $lng, $day);

        $this->assertWithinTolerance($imsak, $t['fajr'], "{$city} {$day} fajr (18 deg) vs imsak");
        $this->assertWithinTolerance($yatsi, $t['isha'], "{$city} {$day} isha (17 deg) vs yatsi");
    }

    /**
     * Diyanet's other columns (gunes, ogle, ikindi, aksam) are not raw astronomical
     * events: they differ from ours by an almost constant number of minutes
     * (sunrise -7..-8, noon +5, Asr +4..+5, sunset +7..+8 over all 12 rows). The
     * source page does not state these offsets and no source stating them was
     * read, so they are inferred from this data only. The check below
     * therefore asserts regularity (a fixed band per column), which still
     * validates solar noon, the Asr shadow factor (1) and the sunrise/sunset
     * geometry across three latitudes and five weeks, but it is NOT a direct
     * agreement with a published event time.
     */
    #[DataProvider('diyanetTables')]
    public function test_diyanet_other_columns_differ_by_a_near_constant_offset(
        string $city,
        float $lat,
        float $lng,
        string $day,
        string $imsak,
        string $gunes,
        string $ogle,
        string $ikindi,
        string $aksam,
        string $yatsi,
    ): void {
        $t = $this->mwl($lat, $lng, $day);

        $this->assertNotNull($t['sunrise']);
        $this->assertNotNull($t['asr']);
        $this->assertNotNull($t['maghrib']);

        $this->assertEqualsWithDelta(-7, self::mins($gunes) - self::mins($t['sunrise']), 1, "{$city} {$day} sunrise offset");
        $this->assertEqualsWithDelta(5, self::mins($ogle) - self::mins($t['dhuhr']), 1, "{$city} {$day} noon offset");
        $this->assertEqualsWithDelta(5, self::mins($ikindi) - self::mins($t['asr']), 1, "{$city} {$day} asr offset (shadow factor 1)");
        $this->assertEqualsWithDelta(8, self::mins($aksam) - self::mins($t['maghrib']), 1, "{$city} {$day} sunset offset");
    }

    /**
     * JAKIM (Malaysia), official e-solat API, zone SGR01, October 2026:
     * https://www.e-solat.gov.my/index.php?r=esolatApi/takwimsolat&period=month&zone=SGR01
     * Fajr 18 deg / Isha 18 deg are the Karachi-method angles. JAKIM's own
     * precautionary offsets are not documented on the pages read; observed
     * difference to ours is +1..+2 minutes (JAKIM later), inside the tolerance.
     * The zone reference coordinates are not published, so Kuala Lumpur
     * (3.1390 N 101.6869 E, Asia/Kuala_Lumpur) is used and only Fajr and Isha
     * are compared. Row format: [date, fajr, isha].
     *
     * @return array<string, array{string, string, string}>
     */
    public static function jakimSelangorTable(): array
    {
        return [
            '2026-10-01' => ['2026-10-01', '05:53', '20:16'],
            '2026-10-08' => ['2026-10-08', '05:52', '20:14'],
            '2026-10-15' => ['2026-10-15', '05:50', '20:11'],
            '2026-10-31' => ['2026-10-31', '05:48', '20:09'],
        ];
    }

    #[DataProvider('jakimSelangorTable')]
    public function test_karachi_angles_are_consistent_with_jakim_fajr_and_isha(
        string $day,
        string $fajr,
        string $isha,
    ): void {
        $zone = new DateTimeZone('Asia/Kuala_Lumpur');
        $t    = (new PrayerTimes(3.1390, 101.6869, PrayerTimes::METHOD_KARACHI, PrayerTimes::ASR_STANDARD, $zone))
            ->getTimes(new DateTimeImmutable($day.' 10:00', $zone));

        $this->assertWithinTolerance($fajr, $t['fajr'], "{$day} fajr (18 deg)");
        $this->assertWithinTolerance($isha, $t['isha'], "{$day} isha (18 deg)");
    }

    /**
     * Egyptian Dar al-Ifta, monthly Cairo table, further dates of the same
     * October 2026 table already used in PrayerTimesPublishedTablesTest:
     * https://www.dar-alifta.org/ar/prayer . Cairo is on DST on all three
     * dates. Same single-month caveat as there. Standard Asr.
     * Row format: [date, fajr, sunrise, dhuhr, asr, maghrib, isha].
     *
     * @return array<string, array{string, string, string, string, string, string, string}>
     */
    public static function darAlIftaCairoMoreDates(): array
    {
        return [
            '2026-10-15' => ['2026-10-15', '05:30', '06:57', '12:41', '15:57', '18:24', '19:42'],
            '2026-10-22' => ['2026-10-22', '05:34', '07:01', '12:39', '15:52', '18:17', '19:35'],
            '2026-10-28' => ['2026-10-28', '05:38', '07:06', '12:39', '15:47', '18:11', '19:30'],
        ];
    }

    #[DataProvider('darAlIftaCairoMoreDates')]
    public function test_egypt_method_matches_more_dar_al_ifta_cairo_dates(
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

    /**
     * @return array{fajr: ?string, sunrise: ?string, dhuhr: string, asr: ?string, maghrib: ?string, isha: ?string}
     */
    private function mwl(float $lat, float $lng, string $day): array
    {
        $zone = new DateTimeZone('Europe/Istanbul');

        return (new PrayerTimes($lat, $lng, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $zone))
            ->getTimes(new DateTimeImmutable($day.' 10:00', $zone));
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
