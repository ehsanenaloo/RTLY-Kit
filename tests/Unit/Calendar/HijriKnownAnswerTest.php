<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Known-answer tests for the Hijri calendar: range edges and their error codes,
 * the month wrap of addMonths(), delta bounds, the Umm al-Qura table edges and the
 * tabular (30-year cycle) rules checked against a year-by-year day count.
 *
 * Reference points: 2026-03-21 = 2 Shawwal 1447 (Umm al-Qura), 1970-01-01 =
 * 22 Shawwal 1389, 1 Muharram 1 AH (civil) = 0622-07-19 (proleptic Gregorian).
 * Tabular leap years are years 2, 5, 7, 10, 13, 16, 18, 21, 24, 26 and 29 of each
 * 30-year cycle (355 days); the others have 354 days.
 */
final class HijriKnownAnswerTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    private function assertDateException(callable $call, ErrorCode $code, string $messagePattern): void
    {
        try {
            $call();
            self::fail('InvalidDateException expected');
        } catch (InvalidDateException $e) {
            self::assertSame($code, $e->getErrorCode(), $e->getMessage());
            self::assertMatchesRegularExpression($messagePattern, $e->getMessage());
        }
    }

    /** @return list<int> */
    private function ymd(Hijri $h): array
    {
        return [$h->getYear(), $h->getMonth(), $h->getDay()];
    }

    private function tabular(int $y, int $m, int $d, int $hour = 0, int $minute = 0, int $second = 0): Hijri
    {
        return Hijri::create($y, $m, $d, $hour, $minute, $second, $this->utc, HijriVariant::Tabular);
    }

    /* ---------------- factories ---------------- */

    public function test_make_from_timestamp_keeps_the_given_zone(): void
    {
        $h = Hijri::make(0, $this->utc);
        self::assertSame([1389, 10, 22], $this->ymd($h));
        self::assertSame(0, $h->getTimestamp());
        self::assertSame('UTC', $h->getTimezone()->getName());

        $tehran = Hijri::make(0, new DateTimeZone('Asia/Tehran'));
        self::assertSame('Asia/Tehran', $tehran->getTimezone()->getName());
        self::assertSame([3, 30], [$tehran->getHour(), $tehran->getMinute()]);

        $shawwal = Hijri::make(1774051200, $this->utc); // 2026-03-21 00:00:00 UTC
        self::assertSame([1447, 10, 2], $this->ymd($shawwal));
    }

    public function test_make_from_timestamp_without_zone_uses_the_default_zone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tehran');
        try {
            $h = Hijri::make(0);
        } finally {
            date_default_timezone_set($previous);
        }

        self::assertSame('Asia/Tehran', $h->getTimezone()->getName());
        self::assertSame([3, 30], [$h->getHour(), $h->getMinute()]);
    }

    public function test_own_format_string_carries_every_time_component(): void
    {
        $h = Hijri::make('1447/10/02 13:45:27', $this->utc);

        self::assertSame('2026-03-21 13:45:27', $h->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame([13, 45, 27], [$h->getHour(), $h->getMinute(), $h->getSecond()]);
        self::assertSame('2026-03-21 00:00:00', Hijri::make('1447-10-02', $this->utc)->toGregorian()->format('Y-m-d H:i:s'));
    }

    public function test_own_format_threshold_is_year_1700(): void
    {
        self::assertSame(1699, Hijri::make('1699/01/01', $this->utc)->getYear());

        // 1700 and later are Gregorian text.
        $greg = Hijri::make('1700/01/01', $this->utc);
        self::assertSame('1700-01-01', $greg->toGregorian()->format('Y-m-d'));
    }

    public function test_create_defaults_to_midnight(): void
    {
        $h = Hijri::create(1447, 10, 2, timezone: $this->utc);

        self::assertSame([0, 0, 0], [$h->getHour(), $h->getMinute(), $h->getSecond()]);
        self::assertSame('00:00:00', $h->format('H:i:s'));
    }

    public function test_create_error_code_tells_out_of_range_from_impossible(): void
    {
        $this->assertDateException(static fn (): Hijri => Hijri::create(1, 13, 1), ErrorCode::InvalidDate, '/^Invalid Hijri date: 1\/13\/1$/');
        $this->assertDateException(static fn (): Hijri => Hijri::create(Hijri::MAX_YEAR, 13, 1), ErrorCode::InvalidDate, '/^Invalid Hijri date: 9665\/13\/1$/');
        $this->assertDateException(static fn (): Hijri => Hijri::create(1447, 2, 30), ErrorCode::InvalidDate, '/^Invalid Hijri date: 1447\/2\/30$/');

        $this->assertDateException(static fn (): Hijri => Hijri::create(0, 1, 1), ErrorCode::DateOutOfRange, '/^Invalid Hijri date: 0\/1\/1$/');
        $this->assertDateException(static fn (): Hijri => Hijri::create(9666, 1, 1), ErrorCode::DateOutOfRange, '/^Invalid Hijri date: 9666\/1\/1$/');

        self::assertSame('0622-07-19', Hijri::create(1, 1, 1, timezone: $this->utc)->toGregorian()->format('Y-m-d'));
        self::assertSame('9999-10-01', Hijri::create(9665, 12, 30, timezone: $this->utc)->toGregorian()->format('Y-m-d'));
    }

    /* ---------------- format tokens ---------------- */

    /** @return array<string, array{int, string, string, string, string, string}> */
    public static function clockHours(): array
    {
        // hour => [a, A, g, h, G] in English
        return [
            'midnight' => [0, 'am', 'AM', '12', '12', '0'],
            'one am' => [1, 'am', 'AM', '1', '01', '1'],
            'eleven am' => [11, 'am', 'AM', '11', '11', '11'],
            'noon' => [12, 'pm', 'PM', '12', '12', '12'],
            'one pm' => [13, 'pm', 'PM', '1', '01', '13'],
            'eleven pm' => [23, 'pm', 'PM', '11', '11', '23'],
        ];
    }

    #[DataProvider('clockHours')]
    public function test_twelve_hour_tokens(int $hour, string $a, string $upperA, string $g, string $h, string $G): void
    {
        $date = Hijri::create(1447, 10, 2, $hour, 5, 9, $this->utc);

        self::assertSame($a, $date->format('a', 'en'));
        self::assertSame($upperA, $date->format('A', 'en'));
        self::assertSame($g, $date->format('g'));
        self::assertSame($h, $date->format('h'));
        self::assertSame($G, $date->format('G'));
    }

    public function test_meridiem_by_locale_switches_at_noon(): void
    {
        $am = Hijri::create(1447, 10, 2, 11, 59, 59, $this->utc);
        $pm = Hijri::create(1447, 10, 2, 12, 0, 0, $this->utc);

        self::assertSame('ص', $am->format('a', 'ar'));
        self::assertSame('م', $pm->format('a', 'ar'));
        self::assertSame('ق.ظ', $am->format('a', 'fa'));
        self::assertSame('ب.ظ', $pm->format('A', 'fa'));
        self::assertSame('am', $am->format('a', 'xx'));
        self::assertSame('PM', $pm->format('A', 'xx'));
    }

    public function test_calendar_tokens_of_2_shawwal_1447(): void
    {
        $d = Hijri::create(1447, 10, 2, 13, 45, 27, $this->utc);

        self::assertSame('10', $d->format('n'));
        self::assertSame('2', $d->format('j'));
        self::assertSame('6', $d->format('w'));      // Saturday, Sunday = 0
        self::assertSame('6', $d->format('N'));
        self::assertSame('Saturday', $d->format('l', 'en'));
        self::assertSame('السبت', $d->format('l'));
        self::assertSame('Shawwal', $d->format('F', 'en'));
    }

    public function test_month_names(): void
    {
        self::assertSame('Muharram', Hijri::monthName(1, 'en'));
        self::assertSame('محرم', Hijri::monthName(1));
        self::assertSame('Dhu al-Hijjah', Hijri::monthName(12, 'en'));
        self::assertSame('ذو الحجة', Hijri::monthName(12, 'ar'));
        self::assertSame('Muharram', Hijri::monthName(1, 'xx'));

        foreach ([0, 13, -1] as $month) {
            $this->assertDateException(static fn (): string => Hijri::monthName($month), ErrorCode::InvalidDate, '/^Invalid Hijri month: /');
        }
    }

    /* ---------------- addMonths / addYears ---------------- */

    public function test_add_months_wraps_over_the_year_boundary(): void
    {
        $date = Hijri::create(1447, 1, 15, 6, 7, 8, $this->utc);

        self::assertSame([1446, 12, 15], $this->ymd($date->subMonths(1)));
        self::assertSame([1446, 11, 15], $this->ymd($date->subMonths(2)));
        self::assertSame([1445, 12, 15], $this->ymd($date->subMonths(13)));
        self::assertSame([1447, 12, 15], $this->ymd($date->addMonths(11)));
        self::assertSame([1448, 1, 15], $this->ymd($date->addMonths(12)));
        self::assertSame([1447, 1, 15], $this->ymd($date->addMonths(0)));
        self::assertSame([6, 7, 8], [$date->subMonths(1)->getHour(), $date->subMonths(1)->getMinute(), $date->subMonths(1)->getSecond()]);
    }

    public function test_add_months_clamps_the_day_to_the_target_month(): void
    {
        // Tabular: odd months have 30 days, even months 29.
        self::assertSame([1447, 2, 29], $this->ymd($this->tabular(1447, 1, 30)->addMonths(1)));
        self::assertSame([1447, 1, 29], $this->ymd($this->tabular(1447, 2, 29)->subMonths(1)));
    }

    public function test_add_months_delta_bound(): void
    {
        $date = Hijri::create(1447, 1, 1, 0, 0, 0, $this->utc);
        $bound = (Hijri::MAX_YEAR - Hijri::MIN_YEAR + 2) * 12; // 115992

        self::assertSame(115992, $bound);

        $this->assertDateException(
            static fn (): Hijri => $date->addMonths($bound),
            ErrorCode::DateOutOfRange,
            '/^Hijri year out of the supported range: 11113$/',
        );
        $this->assertDateException(
            static fn (): Hijri => $date->addMonths(-$bound),
            ErrorCode::DateOutOfRange,
            '/^Hijri year out of the supported range: -8219$/',
        );
        $this->assertDateException(
            static fn (): Hijri => $date->addMonths($bound + 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by 115993 months: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Hijri => $date->addMonths(-$bound - 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by -115993 months: out of the supported range\.$/',
        );
    }

    public function test_add_years_delta_bound(): void
    {
        $date = Hijri::create(1447, 1, 1, 0, 0, 0, $this->utc);

        // 9666 years = 115992 months: the year bound and the month bound agree.
        $this->assertDateException(
            static fn (): Hijri => $date->addYears(9666),
            ErrorCode::DateOutOfRange,
            '/^Hijri year out of the supported range: 11113$/',
        );
        $this->assertDateException(
            static fn (): Hijri => $date->subYears(9666),
            ErrorCode::DateOutOfRange,
            '/^Hijri year out of the supported range: -8219$/',
        );
        $this->assertDateException(
            static fn (): Hijri => $date->addYears(9667),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by 9667 years: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Hijri => $date->subYears(9667),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by -9667 years: out of the supported range\.$/',
        );
    }

    public function test_range_edges_of_add_months_and_years(): void
    {
        self::assertSame([1, 1, 1], $this->ymd(Hijri::create(2, 1, 1, 0, 0, 0, $this->utc)->subYears(1)));
        self::assertSame([1, 12, 1], $this->ymd(Hijri::create(2, 1, 1, 0, 0, 0, $this->utc)->subMonths(1)));
        self::assertSame([9665, 1, 1], $this->ymd(Hijri::create(9664, 1, 1, 0, 0, 0, $this->utc)->addYears(1)));
        self::assertSame([9665, 1, 1], $this->ymd(Hijri::create(9664, 12, 1, 0, 0, 0, $this->utc)->addMonths(1)));

        $this->assertDateException(
            fn (): Hijri => Hijri::create(1, 1, 1, 0, 0, 0, $this->utc)->subMonths(1),
            ErrorCode::DateOutOfRange,
            '/^Hijri year out of the supported range: 0$/',
        );
        $this->assertDateException(
            fn (): Hijri => Hijri::create(1, 1, 1, 0, 0, 0, $this->utc)->subYears(1),
            ErrorCode::DateOutOfRange,
            '/^Hijri year out of the supported range: 0$/',
        );
        $this->assertDateException(
            fn (): Hijri => Hijri::create(9665, 12, 29, 0, 0, 0, $this->utc)->addMonths(1),
            ErrorCode::DateOutOfRange,
            '/^Hijri year out of the supported range: 9666$/',
        );
    }

    public function test_end_of_year_is_the_last_day_of_dhu_al_hijjah(): void
    {
        // 1447 is a tabular leap year: Dhu al-Hijjah has 30 days (Dhu al-Qi'dah only 29).
        $end = $this->tabular(1447, 1, 1)->endOfYear();
        self::assertSame([1447, 12, 30], $this->ymd($end));
        self::assertSame('23:59:59', $end->format('H:i:s'));

        // 1446 is a regular tabular year: 29 days.
        self::assertSame([1446, 12, 29], $this->ymd($this->tabular(1446, 5, 5)->endOfYear()));
    }

    /* ---------------- leap years and the Umm al-Qura table edges ---------------- */

    public function test_leap_status_depends_on_the_variant_inside_the_table(): void
    {
        // AH 1445: Umm al-Qura year has 354 days, tabular year 355.
        self::assertFalse(Hijri::isLeapYear(1445));
        self::assertFalse(Hijri::isLeapYear(1445, HijriVariant::UmmAlQura));
        self::assertTrue(Hijri::isLeapYear(1445, HijriVariant::Tabular));
        self::assertSame(354, Hijri::daysInYear(1445, HijriVariant::UmmAlQura));
        self::assertSame(355, Hijri::daysInYear(1445, HijriVariant::Tabular));

        // AH 1448: the other way round.
        self::assertTrue(Hijri::isLeapYear(1448, HijriVariant::UmmAlQura));
        self::assertFalse(Hijri::isLeapYear(1448, HijriVariant::Tabular));
    }

    public function test_table_edges(): void
    {
        self::assertSame([1300, 1, 1], Hijri::gregorianToHijri(1882, 11, 12));
        self::assertSame([1299, 12, 29], Hijri::gregorianToHijri(1882, 11, 11)); // tabular, before the table
        self::assertSame([1500, 12, 30], Hijri::gregorianToHijri(2077, 11, 16));
        self::assertSame([1501, 1, 1], Hijri::gregorianToHijri(2077, 11, 17));    // tabular, after the table
        self::assertSame([2077, 11, 16], Hijri::hijriToGregorian(1500, 12, 30));
        self::assertSame([2077, 11, 17], Hijri::hijriToGregorian(1501, 1, 1));
    }

    /** @return array<string, array{int, int, int}> */
    public static function impossibleArguments(): array
    {
        return [
            'year zero' => [0, 1, 1],
            'year above max' => [9666, 1, 1],
            'month zero' => [1447, 0, 1],
            'month thirteen' => [1447, 13, 1],
            'day zero' => [1447, 1, 0],
            'day thirty-one' => [1447, 1, 31],
        ];
    }

    #[DataProvider('impossibleArguments')]
    public function test_hijri_to_gregorian_rejects_each_impossible_argument(int $y, int $m, int $d): void
    {
        $this->assertDateException(
            static fn (): array => Hijri::hijriToGregorian($y, $m, $d),
            ErrorCode::DateOutOfRange,
            '/^Hijri date out of the supported range: /',
        );
    }

    public function test_is_valid_rejects_the_same_arguments(): void
    {
        foreach (self::impossibleArguments() as [$y, $m, $d]) {
            self::assertFalse(Hijri::isValid($y, $m, $d), "{$y}/{$m}/{$d}");
        }
        self::assertTrue(Hijri::isValid(1, 1, 1));
        self::assertTrue(Hijri::isValid(9665, 12, 29));
        self::assertFalse(Hijri::isValid(1447, 2, 30, HijriVariant::Tabular));
        self::assertTrue(Hijri::isValid(1447, 1, 30, HijriVariant::Tabular));
    }

    /* ---------------- tabular rules ---------------- */

    /**
     * Year => [first day, last day, 355-day year?]. Counted year by year from
     * 1 Muharram 1 = 0622-07-19 with 354 days, 355 in leap years.
     *
     * @return array<string, array{int, string, string, bool}>
     */
    public static function tabularYears(): array
    {
        $rows = [
            1 => ['0622-07-19', '0623-07-07', false],
            2 => ['0623-07-08', '0624-06-26', true],
            3 => ['0624-06-27', '0625-06-15', false],
            4 => ['0625-06-16', '0626-06-04', false],
            5 => ['0626-06-05', '0627-05-25', true],
            6 => ['0627-05-26', '0628-05-13', false],
            7 => ['0628-05-14', '0629-05-03', true],
            8 => ['0629-05-04', '0630-04-22', false],
            9 => ['0630-04-23', '0631-04-11', false],
            10 => ['0631-04-12', '0632-03-31', true],
            11 => ['0632-04-01', '0633-03-20', false],
            12 => ['0633-03-21', '0634-03-09', false],
            13 => ['0634-03-10', '0635-02-27', true],
            14 => ['0635-02-28', '0636-02-16', false],
            15 => ['0636-02-17', '0637-02-04', false],
            16 => ['0637-02-05', '0638-01-25', true],
            17 => ['0638-01-26', '0639-01-14', false],
            18 => ['0639-01-15', '0640-01-04', true],
            19 => ['0640-01-05', '0640-12-23', false],
            20 => ['0640-12-24', '0641-12-12', false],
            21 => ['0641-12-13', '0642-12-02', true],
            22 => ['0642-12-03', '0643-11-21', false],
            23 => ['0643-11-22', '0644-11-09', false],
            24 => ['0644-11-10', '0645-10-30', true],
            25 => ['0645-10-31', '0646-10-19', false],
            26 => ['0646-10-20', '0647-10-09', true],
            27 => ['0647-10-10', '0648-09-27', false],
            28 => ['0648-09-28', '0649-09-16', false],
            29 => ['0649-09-17', '0650-09-06', true],
            30 => ['0650-09-07', '0651-08-26', false],
        ];

        $out = [];
        foreach ($rows as $year => [$first, $last, $leap]) {
            $out["AH {$year}"] = [$year, $first, $last, $leap];
        }

        return $out;
    }

    #[DataProvider('tabularYears')]
    public function test_tabular_year_boundaries(int $year, string $first, string $last, bool $leap): void
    {
        $variant = HijriVariant::Tabular;

        self::assertSame($leap, Hijri::isLeapYear($year, $variant));
        self::assertSame($leap ? 355 : 354, Hijri::daysInYear($year, $variant));
        self::assertSame($leap ? 30 : 29, Hijri::daysInMonth($year, 12, $variant));

        self::assertSame($first, Hijri::create($year, 1, 1, 0, 0, 0, $this->utc, $variant)->toGregorian()->format('Y-m-d'));
        self::assertSame($last, Hijri::create($year, 12, $leap ? 30 : 29, 0, 0, 0, $this->utc, $variant)->toGregorian()->format('Y-m-d'));

        [$fy, $fm, $fd] = array_map('intval', explode('-', $first));
        [$ly, $lm, $ld] = array_map('intval', explode('-', $last));
        self::assertSame([$year, 1, 1], Hijri::gregorianToHijri($fy, $fm, $fd, $variant));
        self::assertSame([$year, 12, $leap ? 30 : 29], Hijri::gregorianToHijri($ly, $lm, $ld, $variant));
    }

    public function test_tabular_month_lengths_alternate(): void
    {
        for ($month = 1; $month <= 11; $month++) {
            self::assertSame($month % 2 === 1 ? 30 : 29, Hijri::daysInMonth(2, $month, HijriVariant::Tabular), "month {$month}");
        }
    }

    /* ---------------- delegated format tokens ---------------- */

    public function test_zone_tokens_are_delegated_to_the_instant(): void
    {
        $utc = Hijri::create(1448, 1, 16, 13, 45, 27, $this->utc); // 2026-07-01
        self::assertSame(
            '1782913527 UTC UTC +00:00 Z +0000 0 0 000000 000',
            $utc->format('U e T P p O Z I u v'),
        );

        $berlin = Hijri::create(1448, 1, 16, 13, 45, 27, new DateTimeZone('Europe/Berlin'));
        self::assertSame(
            '1782906327 Europe/Berlin CEST +02:00 +02:00 +0200 7200 1 000000 000',
            $berlin->format('U e T P p O Z I u v'),
        );
        self::assertSame('1448-01-16T13:45:27+02:00', $berlin->format('c'));
        self::assertSame('Wed, 01 Jul 2026 13:45:27 +0200', $berlin->format('r'));
    }
}
