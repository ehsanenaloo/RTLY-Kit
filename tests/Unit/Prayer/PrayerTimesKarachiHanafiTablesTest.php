<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Prayer\PrayerTimes;

/**
 * Known-answer tests against the prayer-time page of Jamia Uloom-e-Islamia
 * Allama Banuri Town, Karachi (a Hanafi seminary), read on 2026-10-08:
 * https://www.banuri.edu.pk/namaz-times
 * (country Pakistan, city Karachi, month October; method selector "Jamia Uloom
 * Islamia Banuri Town" and juristic selector "Hanafi", the two values the page
 * had selected by default; the HTTP form parameters were method=1,
 * time_method=1).
 *
 * What this does and does not establish (see
 * the data verification notes (October 2026), Round 2):
 *  - The page states no angles. Fajr and Isha of this table agree with the
 *    Karachi method (18 / 18 degrees, University of Islamic Sciences, Karachi)
 *    to the minute on every day compared, so the table is consistent with those
 *    angles; it is not a document that names them.
 *  - Asr of this table is the Hanafi (shadow factor 2) Asr; the page offers the
 *    Hanafi / Shafi'i choice and the default shown is Hanafi. Our
 *    ASR_HANAFI output agrees with it to the minute. The same page with the
 *    Shafi'i choice (time_method=0) gives the standard (factor 1) Asr, which
 *    our ASR_STANDARD output also matches.
 *  - The coordinates of the page's "Karachi" are not published; 24.8607 N
 *    67.0011 E (a public city-centre value) is used.
 *  - The other methods in the page's selector (options 2, 3, 4, 5) were read as
 *    well. The page itself is an automatic calculator, so those comparisons
 *    show that an independent implementation gives the same numbers; they are
 *    NOT tables issued by ISNA, the Muslim World League, Umm al-Qura University
 *    or the Egyptian General Authority of Survey. Option 2 is labelled
 *    "Islamic University North America" on the page (the wording is the page's
 *    own); its numbers are the 15 / 15 degree pair.
 *
 * Row order in banuriKarachiOctober: [day, fajr, sunrise, zawal, asr, ghurub, isha].
 */
final class PrayerTimesKarachiHanafiTablesTest extends TestCase
{
    private const LAT = 24.8607;
    private const LNG = 67.0011;

    /**
     * Banuri Town, Karachi, October (method 1, Hanafi). The page's "zawal"
     * column is the time of solar noon; it is compared with our dhuhr.
     *
     * @return array<string, array{string, string, string, string, string, string, string}>
     */
    public static function banuriKarachiOctober(): array
    {
        $rows = [
            ['01', '05:08', '06:24', '12:22', '16:39', '18:19', '19:35'],
            ['02', '05:08', '06:24', '12:21', '16:38', '18:18', '19:34'],
            ['03', '05:09', '06:25', '12:21', '16:38', '18:17', '19:33'],
            ['04', '05:09', '06:25', '12:21', '16:37', '18:16', '19:32'],
            ['05', '05:10', '06:25', '12:20', '16:36', '18:15', '19:31'],
            ['06', '05:10', '06:26', '12:20', '16:35', '18:14', '19:30'],
            ['07', '05:10', '06:26', '12:20', '16:34', '18:13', '19:29'],
            ['08', '05:11', '06:27', '12:19', '16:33', '18:12', '19:28'],
            ['09', '05:11', '06:27', '12:19', '16:32', '18:11', '19:27'],
            ['10', '05:12', '06:27', '12:19', '16:32', '18:10', '19:26'],
            ['11', '05:12', '06:28', '12:19', '16:31', '18:09', '19:25'],
            ['12', '05:12', '06:28', '12:18', '16:30', '18:08', '19:24'],
            ['13', '05:13', '06:29', '12:18', '16:29', '18:07', '19:23'],
            ['14', '05:13', '06:29', '12:18', '16:28', '18:06', '19:22'],
            ['15', '05:14', '06:30', '12:18', '16:28', '18:05', '19:21'],
            ['16', '05:14', '06:30', '12:17', '16:27', '18:04', '19:20'],
            ['17', '05:15', '06:31', '12:17', '16:26', '18:03', '19:19'],
            ['18', '05:15', '06:31', '12:17', '16:25', '18:02', '19:19'],
            ['19', '05:15', '06:32', '12:17', '16:24', '18:02', '19:18'],
            ['20', '05:16', '06:32', '12:17', '16:24', '18:01', '19:17'],
            ['21', '05:16', '06:33', '12:16', '16:23', '18:00', '19:16'],
            ['22', '05:17', '06:33', '12:16', '16:22', '17:59', '19:15'],
            ['23', '05:17', '06:34', '12:16', '16:21', '17:58', '19:15'],
            ['24', '05:18', '06:34', '12:16', '16:21', '17:57', '19:14'],
            ['25', '05:18', '06:35', '12:16', '16:20', '17:57', '19:13'],
            ['26', '05:19', '06:35', '12:16', '16:19', '17:56', '19:13'],
            ['27', '05:19', '06:36', '12:16', '16:19', '17:55', '19:12'],
            ['28', '05:20', '06:37', '12:16', '16:18', '17:54', '19:11'],
            ['29', '05:20', '06:37', '12:16', '16:17', '17:54', '19:11'],
            ['30', '05:21', '06:38', '12:15', '16:17', '17:53', '19:10'],
            ['31', '05:21', '06:38', '12:15', '16:16', '17:52', '19:10'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out['2026-10-'.$row[0]] = $row;
        }

        return $out;
    }

    #[DataProvider('banuriKarachiOctober')]
    public function test_karachi_method_with_hanafi_asr_matches_banuri_town_table(
        string $day,
        string $fajr,
        string $sunrise,
        string $zawal,
        string $asr,
        string $ghurub,
        string $isha,
    ): void {
        $t = $this->times(PrayerTimes::METHOD_KARACHI, PrayerTimes::ASR_HANAFI, '2026-10-'.$day);

        $this->assertWithin($fajr, $t['fajr'], "{$day} fajr (18 deg)");
        $this->assertWithin($sunrise, $t['sunrise'], "{$day} sunrise");
        $this->assertWithin($zawal, $t['dhuhr'], "{$day} dhuhr");
        $this->assertWithin($asr, $t['asr'], "{$day} asr (Hanafi, factor 2)");
        $this->assertWithin($ghurub, $t['maghrib'], "{$day} maghrib");
        $this->assertWithin($isha, $t['isha'], "{$day} isha (18 deg)");
    }

    /**
     * The same page with the Shafi'i / Hanbali choice (time_method=0), method 1:
     * Asr is the standard (shadow factor 1) Asr. Row: [day, asr].
     *
     * @return array<string, array{string, string}>
     */
    public static function banuriStandardAsr(): array
    {
        return [
            '2026-10-01' => ['2026-10-01', '15:46'],
            '2026-10-08' => ['2026-10-08', '15:41'],
            '2026-10-15' => ['2026-10-15', '15:37'],
            '2026-10-24' => ['2026-10-24', '15:32'],
            '2026-10-31' => ['2026-10-31', '15:28'],
        ];
    }

    #[DataProvider('banuriStandardAsr')]
    public function test_standard_asr_matches_banuri_town_shafii_choice(string $day, string $asr): void
    {
        $t = $this->times(PrayerTimes::METHOD_KARACHI, PrayerTimes::ASR_STANDARD, $day);

        $this->assertWithin($asr, $t['asr'], "{$day} asr (standard, factor 1)");
    }

    /**
     * Hanafi Asr is later than standard Asr by the observed 50-55 minutes at
     * this latitude and season; the Banuri Town pages differ by the same amount.
     */
    public function test_hanafi_minus_standard_asr_gap_matches_the_published_pages(): void
    {
        $standard = $this->times(PrayerTimes::METHOD_KARACHI, PrayerTimes::ASR_STANDARD, '2026-10-08');
        $hanafi   = $this->times(PrayerTimes::METHOD_KARACHI, PrayerTimes::ASR_HANAFI, '2026-10-08');

        // Published: 16:33 (Hanafi) - 15:41 (Shafi'i) = 52 minutes.
        $this->assertEqualsWithDelta(52, self::mins($hanafi['asr'] ?? '') - self::mins($standard['asr'] ?? ''), 1);
    }

    /**
     * Other methods of the same page (an automatic calculator, not an issuing
     * authority). Row: [method, day, fajr, isha]. Asr is standard (time_method=0)
     * and identical for every method, so only Fajr and Isha are compared.
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function banuriOtherMethods(): array
    {
        $rows = [
            // option 2, the 15 / 15 degree pair
            [PrayerTimes::METHOD_ISNA, '2026-10-01', '05:21', '19:21'],
            [PrayerTimes::METHOD_ISNA, '2026-10-08', '05:24', '19:14'],
            [PrayerTimes::METHOD_ISNA, '2026-10-15', '05:27', '19:08'],
            [PrayerTimes::METHOD_ISNA, '2026-10-24', '05:31', '19:01'],
            [PrayerTimes::METHOD_ISNA, '2026-10-31', '05:34', '18:56'],
            // option 3, Muslim World League 18 / 17
            [PrayerTimes::METHOD_MWL, '2026-10-01', '05:08', '19:30'],
            [PrayerTimes::METHOD_MWL, '2026-10-08', '05:11', '19:23'],
            [PrayerTimes::METHOD_MWL, '2026-10-15', '05:14', '19:17'],
            [PrayerTimes::METHOD_MWL, '2026-10-24', '05:18', '19:10'],
            [PrayerTimes::METHOD_MWL, '2026-10-31', '05:21', '19:05'],
            // option 5, Egyptian General Authority of Survey 19.5 / 17.5
            [PrayerTimes::METHOD_EGYPT, '2026-10-01', '05:01', '19:32'],
            [PrayerTimes::METHOD_EGYPT, '2026-10-08', '05:04', '19:25'],
            [PrayerTimes::METHOD_EGYPT, '2026-10-15', '05:07', '19:19'],
            [PrayerTimes::METHOD_EGYPT, '2026-10-24', '05:11', '19:12'],
            [PrayerTimes::METHOD_EGYPT, '2026-10-31', '05:14', '19:07'],
            // option 4, Umm al-Qura 18.5 degrees / 90 minutes after sunset
            [PrayerTimes::METHOD_MAKKAH, '2026-10-01', '05:06', '19:49'],
            [PrayerTimes::METHOD_MAKKAH, '2026-10-08', '05:09', '19:42'],
            [PrayerTimes::METHOD_MAKKAH, '2026-10-15', '05:11', '19:35'],
            [PrayerTimes::METHOD_MAKKAH, '2026-10-24', '05:15', '19:27'],
            [PrayerTimes::METHOD_MAKKAH, '2026-10-31', '05:19', '19:22'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out["{$row[0]} {$row[1]}"] = $row;
        }

        return $out;
    }

    #[DataProvider('banuriOtherMethods')]
    public function test_other_methods_agree_with_the_banuri_town_calculator(
        string $method,
        string $day,
        string $fajr,
        string $isha,
    ): void {
        $t = $this->times($method, PrayerTimes::ASR_STANDARD, $day);

        $this->assertWithin($fajr, $t['fajr'], "{$method} {$day} fajr");
        $this->assertWithin($isha, $t['isha'], "{$method} {$day} isha");
    }

    /**
     * @return array{fajr: ?string, sunrise: ?string, dhuhr: string, asr: ?string, maghrib: ?string, isha: ?string}
     */
    private function times(string $method, int $asr, string $day): array
    {
        $zone = new DateTimeZone('Asia/Karachi');

        return (new PrayerTimes(self::LAT, self::LNG, $method, $asr, $zone))
            ->getTimes(new DateTimeImmutable($day.' 10:00', $zone));
    }

    private static function mins(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    /** Both sides are minute-rounded, so a one-minute difference is rounding. */
    private function assertWithin(string $published, ?string $actual, string $what): void
    {
        $this->assertNotNull($actual, $what);
        $this->assertLessThanOrEqual(
            1,
            abs(self::mins($published) - self::mins($actual)),
            "{$what}: published {$published}, computed {$actual}",
        );
    }
}
