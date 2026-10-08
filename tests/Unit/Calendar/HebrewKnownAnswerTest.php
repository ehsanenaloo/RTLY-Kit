<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Known-answer tests for the Hebrew calendar: boundaries of the supported range,
 * the Adar rule of addYears(), month lengths of every year type, the postponement
 * rules that move Rosh Hashanah, clock-hour tokens and diff semantics.
 *
 * Reference points: 1970-01-01 = 23 Tevet 5730, 2026-03-21 (a Saturday) = 3 Nisan
 * 5786 (a regular year, so Nisan is month 7). Rosh Hashanah dates and month
 * lengths come from published Hebrew calendar tables (day counts 353/354/355 and
 * 383/384/385, month sequence 30,29|30,29|30,29,30,[30],29,30,29,30,29,30,29).
 */
final class HebrewKnownAnswerTest extends TestCase
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

    /** @return list<int> [year, month, day] */
    private function ymd(Hebrew $h): array
    {
        return [$h->getYear(), $h->getMonth(), $h->getDay()];
    }

    /* ---------------- factories ---------------- */

    public function test_make_from_timestamp_keeps_the_given_zone(): void
    {
        $h = Hebrew::make(0, $this->utc);
        self::assertSame([5730, 4, 23], $this->ymd($h));
        self::assertSame(0, $h->getTimestamp());
        self::assertSame('UTC', $h->getTimezone()->getName());

        // 1970-01-01 00:00:00 UTC is 03:30 in Tehran (UTC+03:30 in 1970), same Hebrew day.
        $tehran = Hebrew::make(0, new DateTimeZone('Asia/Tehran'));
        self::assertSame('Asia/Tehran', $tehran->getTimezone()->getName());
        self::assertSame(3, $tehran->getHour());
        self::assertSame(30, $tehran->getMinute());
        self::assertSame(0, $tehran->getTimestamp());

        $nisan = Hebrew::make(1774051200, $this->utc); // 2026-03-21 00:00:00 UTC
        self::assertSame([5786, 7, 3], $this->ymd($nisan));
        self::assertSame(1774051200, $nisan->getTimestamp());
    }

    public function test_make_from_timestamp_without_zone_uses_the_default_zone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tehran');
        try {
            $h = Hebrew::make(0);
        } finally {
            date_default_timezone_set($previous);
        }

        self::assertSame('Asia/Tehran', $h->getTimezone()->getName());
        self::assertSame(3, $h->getHour());
        self::assertSame(30, $h->getMinute());
    }

    public function test_own_format_string_carries_every_time_component(): void
    {
        $h = Hebrew::make('5786/07/03 13:45:27', $this->utc);
        self::assertSame('2026-03-21 13:45:27', $h->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame([13, 45, 27], [$h->getHour(), $h->getMinute(), $h->getSecond()]);

        // Without a time part everything is midnight.
        $midnight = Hebrew::make('5786-07-03', $this->utc);
        self::assertSame('2026-03-21 00:00:00', $midnight->toGregorian()->format('Y-m-d H:i:s'));
    }

    public function test_own_format_threshold_is_year_3000(): void
    {
        // 3762/01/01 is the first supported Hebrew day: 1 Tishrei 3762 = 0001-09-06.
        self::assertSame('0001-09-06', Hebrew::make('3762/01/01', $this->utc)->toGregorian()->format('Y-m-d'));

        // 3000 is already read as a Hebrew year (and lies below the supported range).
        $this->assertDateException(
            fn (): Hebrew => Hebrew::make('3000/01/01', $this->utc),
            ErrorCode::DateOutOfRange,
            '/^Invalid Hebrew date: 3000\/1\/1$/',
        );

        // 2999 is a Gregorian year.
        $greg = Hebrew::make('2999/12/31', $this->utc);
        self::assertSame('2999-12-31', $greg->toGregorian()->format('Y-m-d'));
    }

    public function test_create_defaults_to_midnight(): void
    {
        $h = Hebrew::create(5786, 7, 3, timezone: $this->utc);
        self::assertSame('00:00:00', $h->format('H:i:s'));
        self::assertSame([0, 0, 0], [$h->getHour(), $h->getMinute(), $h->getSecond()]);
    }

    public function test_create_rejects_times_outside_a_clock_day(): void
    {
        foreach ([[24, 0, 0], [0, 60, 0], [0, 0, 60], [-1, 0, 0]] as [$hour, $minute, $second]) {
            $this->assertDateException(
                fn (): Hebrew => Hebrew::create(5786, 7, 3, $hour, $minute, $second, $this->utc),
                ErrorCode::InvalidDate,
                '/^Invalid time: /',
            );
        }
        // The last second of the day is fine.
        self::assertSame('23:59:59', Hebrew::create(5786, 7, 3, 23, 59, 59, $this->utc)->format('H:i:s'));
    }

    public function test_create_error_code_tells_out_of_range_from_impossible(): void
    {
        $outOfRange = ErrorCode::DateOutOfRange;
        $invalid = ErrorCode::InvalidDate;

        // 3762 and 13759 are both leap years (13 months): month 14 is impossible, not out of range.
        $this->assertDateException(static fn (): Hebrew => Hebrew::create(3762, 14, 1), $invalid, '/^Invalid Hebrew date: 3762\/14\/1$/');
        $this->assertDateException(static fn (): Hebrew => Hebrew::create(13759, 14, 1), $invalid, '/^Invalid Hebrew date: 13759\/14\/1$/');
        $this->assertDateException(static fn (): Hebrew => Hebrew::create(5785, 13, 1), $invalid, '/^Invalid Hebrew date: 5785\/13\/1$/');
        $this->assertDateException(static fn (): Hebrew => Hebrew::create(5786, 2, 30), $invalid, '/^Invalid Hebrew date: 5786\/2\/30$/');

        // One year outside either end of the range.
        $this->assertDateException(static fn (): Hebrew => Hebrew::create(3761, 1, 1), $outOfRange, '/^Invalid Hebrew date: 3761\/1\/1$/');
        $this->assertDateException(static fn (): Hebrew => Hebrew::create(13760, 1, 1), $outOfRange, '/^Invalid Hebrew date: 13760\/1\/1$/');

        // The first and last supported days exist.
        self::assertSame('0001-09-06', Hebrew::create(3762, 1, 1, timezone: $this->utc)->toGregorian()->format('Y-m-d'));
        self::assertSame('9999-11-03', Hebrew::create(13759, 13, 29, timezone: $this->utc)->toGregorian()->format('Y-m-d'));
    }

    /* ---------------- format tokens ---------------- */

    /** @return array<string, array{int, string, string, string, string, string}> */
    public static function clockHours(): array
    {
        // hour => [a, A, g, h, G]
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
        $date = Hebrew::create(5786, 7, 3, $hour, 5, 9, $this->utc);
        self::assertSame($a, $date->format('a'));
        self::assertSame($upperA, $date->format('A'));
        self::assertSame($g, $date->format('g'));
        self::assertSame($h, $date->format('h'));
        self::assertSame($G, $date->format('G'));
    }

    public function test_calendar_tokens_of_3_nisan_5786(): void
    {
        $d = Hebrew::create(5786, 7, 3, 13, 45, 27, $this->utc);

        self::assertSame('7', $d->format('n'));
        self::assertSame('3', $d->format('j'));
        self::assertSame('179', $d->format('z')); // 30+29+30+29+30+29 days before Nisan, plus 2
        self::assertSame('30', $d->format('t'));
        self::assertSame('6', $d->format('w'));   // Saturday, Sunday = 0
        self::assertSame('6', $d->format('N'));   // ISO Saturday
        self::assertSame('0', $d->format('L'));
        self::assertSame('1', Hebrew::create(5787, 8, 3, 0, 0, 0, $this->utc)->format('L'));
    }

    public function test_weekday_names_by_locale(): void
    {
        $saturday = Hebrew::create(5786, 7, 3, 0, 0, 0, $this->utc);

        self::assertSame('Saturday', $saturday->format('l'));
        self::assertSame('Saturday', $saturday->format('l', 'en'));
        self::assertSame('שבת', $saturday->format('l', 'he'));
        self::assertSame('شنبه', $saturday->format('l', 'fa'));
        self::assertSame('Saturday', $saturday->format('l', 'xx'));

        $sunday = $saturday->addDays(1);
        self::assertSame('ראשון', $sunday->format('l', 'he'));
        self::assertSame('یکشنبه', $sunday->format('l', 'fa'));
        self::assertSame('0', $sunday->format('w'));
    }

    public function test_month_names_regular_and_leap_years(): void
    {
        self::assertSame('Shevat', Hebrew::monthName(5785, 5));
        self::assertSame('Adar', Hebrew::monthName(5785, 6));
        self::assertSame('Nisan', Hebrew::monthName(5785, 7));
        self::assertSame('Elul', Hebrew::monthName(5785, 12));

        self::assertSame('Shevat', Hebrew::monthName(5784, 5));
        self::assertSame('Adar I', Hebrew::monthName(5784, 6));
        self::assertSame('Adar II', Hebrew::monthName(5784, 7));
        self::assertSame('Nisan', Hebrew::monthName(5784, 8));
        self::assertSame('Elul', Hebrew::monthName(5784, 13));
    }

    /* ---------------- addMonths / addYears bounds ---------------- */

    public function test_add_months_delta_bound_is_13_months_per_supported_year(): void
    {
        $date = Hebrew::create(5786, 7, 3, 0, 0, 0, $this->utc);
        $bound = 13 * (Hebrew::MAX_YEAR - Hebrew::MIN_YEAR + 2); // 129987

        self::assertSame(129987, $bound);

        // At the bound the delta guard passes and the year check reports the range.
        $this->assertDateException(
            static fn (): Hebrew => $date->addMonths($bound),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: \d+$/',
        );
        $this->assertDateException(
            static fn (): Hebrew => $date->addMonths(-$bound),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range\.$/',
        );

        // One past the bound the delta guard itself rejects the value.
        $this->assertDateException(
            static fn (): Hebrew => $date->addMonths($bound + 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by 129988 months: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Hebrew => $date->addMonths(-$bound - 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by -129988 months: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Hebrew => $date->subMonths(PHP_INT_MIN),
            ErrorCode::DateOutOfRange,
            '/./',
        );
    }

    public function test_add_months_across_most_of_the_supported_range(): void
    {
        // 120000 months is more than 12 per supported year, so it needs the 13-month bound.
        $forward = Hebrew::create(3762, 1, 1, 0, 0, 0, $this->utc)->addMonths(120000);
        self::assertSame([13464, 2, 1], $this->ymd($forward));

        $backward = Hebrew::create(13759, 13, 29, 0, 0, 0, $this->utc)->subMonths(120000);
        self::assertSame([4057, 11, 29], $this->ymd($backward));
    }

    public function test_add_months_range_edges(): void
    {
        // 3762 is a leap year (13 months); 13758 is a regular year (12 months).
        $first = Hebrew::create(3763, 1, 1, 0, 0, 0, $this->utc)->subMonths(13);
        self::assertSame([3762, 1, 1], $this->ymd($first));

        $last = Hebrew::create(13758, 1, 1, 0, 0, 0, $this->utc)->addMonths(12);
        self::assertSame([13759, 1, 1], $this->ymd($last));

        $this->assertDateException(
            fn (): Hebrew => Hebrew::create(3762, 1, 1, 0, 0, 0, $this->utc)->subMonths(1),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: 3761$/',
        );
        $this->assertDateException(
            fn (): Hebrew => Hebrew::create(13759, 13, 29, 0, 0, 0, $this->utc)->addMonths(1),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: 13760$/',
        );
    }

    public function test_add_months_before_the_first_month_of_year_one(): void
    {
        // 1 Tishrei 3762 is month 46517 counted from 1 Tishrei of year 1 (month 0).
        $start = Hebrew::create(3762, 1, 1, 0, 0, 0, $this->utc);

        // The very first month of year 1 is reached and reported as the year it is.
        $this->assertDateException(
            static fn (): Hebrew => $start->subMonths(46517),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: 1$/',
        );
        // One month earlier does not exist at all.
        $this->assertDateException(
            static fn (): Hebrew => $start->subMonths(46518),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range\.$/',
        );
    }

    public function test_add_months_clamps_the_day_to_the_target_month(): void
    {
        // 5785 is a complete year (Cheshvan 30); 5786 a regular one (Cheshvan 29).
        $kislev30 = Hebrew::create(5785, 3, 30, 8, 9, 10, $this->utc); // Kislev has 30 days in 5785
        $tevet = $kislev30->addMonths(1);
        self::assertSame([5785, 4, 29], $this->ymd($tevet)); // Tevet has 29 days
        self::assertSame([8, 9, 10], [$tevet->getHour(), $tevet->getMinute(), $tevet->getSecond()]);
    }

    public function test_add_years_delta_bound_is_one_per_supported_year_plus_two(): void
    {
        $date = Hebrew::create(5786, 7, 3, 0, 0, 0, $this->utc);
        $bound = Hebrew::MAX_YEAR - Hebrew::MIN_YEAR + 2; // 9999

        self::assertSame(9999, $bound);

        $this->assertDateException(
            static fn (): Hebrew => $date->addYears($bound),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: 15785$/',
        );
        $this->assertDateException(
            static fn (): Hebrew => $date->addYears(-$bound),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: -4213$/',
        );
        $this->assertDateException(
            static fn (): Hebrew => $date->addYears($bound + 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by 10000 years: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Hebrew => $date->subYears($bound + 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by -10000 years: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Hebrew => $date->addYears(PHP_INT_MAX),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by /',
        );
    }

    public function test_add_years_range_edges(): void
    {
        self::assertSame([3762, 1, 1], $this->ymd(Hebrew::create(3763, 1, 1, 0, 0, 0, $this->utc)->addYears(-1)));
        self::assertSame([13759, 1, 1], $this->ymd(Hebrew::create(13758, 1, 1, 0, 0, 0, $this->utc)->addYears(1)));

        $this->assertDateException(
            fn (): Hebrew => Hebrew::create(3762, 1, 1, 0, 0, 0, $this->utc)->addYears(-1),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: 3761$/',
        );
        $this->assertDateException(
            fn (): Hebrew => Hebrew::create(13759, 1, 1, 0, 0, 0, $this->utc)->addYears(1),
            ErrorCode::DateOutOfRange,
            '/^Hebrew year out of the supported range: 13760$/',
        );
    }

    /* ---------------- addYears: the Adar rule ---------------- */

    /**
     * 5783, 5785, 5786 are regular years; 5784, 5787 are leap years.
     *
     * @return array<string, array{int, int, int, int, int, int}>
     */
    public static function yearShifts(): array
    {
        return [
            // leap -> regular: Adar I and Adar II both become Adar; later months move down by one
            'leap to regular: Tishrei' => [5784, 1, 1, -1, 1, 1],
            'leap to regular: Shevat' => [5784, 5, 15, -1, 5, 15],
            'leap to regular: Adar I' => [5784, 6, 10, -1, 6, 10],
            'leap to regular: Adar I day 30 clamps' => [5784, 6, 30, -1, 6, 29],
            'leap to regular: Adar II' => [5784, 7, 10, -1, 6, 10],
            'leap to regular: Nisan' => [5784, 8, 15, -1, 7, 15],
            'leap to regular: Elul' => [5784, 13, 29, -1, 12, 29],
            // regular -> leap: Adar becomes Adar II; later months move up by one
            'regular to leap: Tevet' => [5785, 4, 29, 2, 4, 29],
            'regular to leap: Shevat' => [5785, 5, 15, 2, 5, 15],
            'regular to leap: Adar' => [5785, 6, 10, 2, 7, 10],
            'regular to leap: Nisan' => [5785, 7, 15, 2, 8, 15],
            'regular to leap: Elul' => [5785, 12, 29, 2, 13, 29],
            // leap -> leap keeps every ordinal
            'leap to leap: Shevat' => [5784, 5, 15, 3, 5, 15],
            'leap to leap: Adar I' => [5784, 6, 10, 3, 6, 10],
            'leap to leap: Adar II' => [5784, 7, 10, 3, 7, 10],
            'leap to leap: Nisan' => [5784, 8, 15, 3, 8, 15],
            'leap to leap: Elul' => [5784, 13, 29, 3, 13, 29],
            // regular -> regular keeps every ordinal
            'regular to regular: Adar' => [5785, 6, 10, 1, 6, 10],
            'regular to regular: Nisan' => [5785, 7, 15, 1, 7, 15],
            'regular to regular: Elul' => [5785, 12, 29, 1, 12, 29],
        ];
    }

    #[DataProvider('yearShifts')]
    public function test_add_years_month_mapping(int $year, int $month, int $day, int $years, int $expectMonth, int $expectDay): void
    {
        $shifted = Hebrew::create($year, $month, $day, 6, 7, 8, $this->utc)->addYears($years);

        self::assertSame([$year + $years, $expectMonth, $expectDay], $this->ymd($shifted));
        self::assertSame([6, 7, 8], [$shifted->getHour(), $shifted->getMinute(), $shifted->getSecond()]);
    }

    /* ---------------- diff ---------------- */

    public function test_diff_in_months_signs_and_absolute_flag(): void
    {
        $earlier = Hebrew::create(5786, 7, 3, 0, 0, 0, $this->utc);
        $later = Hebrew::create(5786, 9, 3, 0, 0, 0, $this->utc);

        self::assertSame(2, $earlier->diffInMonths($later));
        self::assertSame(2, $later->diffInMonths($earlier));
        self::assertSame(-2, $earlier->diffInMonths($later, false));
        self::assertSame(2, $later->diffInMonths($earlier, false));

        // One day short of two months.
        self::assertSame(1, $earlier->diffInMonths($later->subDays(1)));
        // Same instant.
        self::assertSame(0, $earlier->diffInMonths($earlier));
        self::assertSame(0, $earlier->diffInMonths($earlier, false));
    }

    public function test_diff_in_years_counts_anniversaries_and_has_signs(): void
    {
        $a = Hebrew::create(5784, 8, 15, 0, 0, 0, $this->utc); // 15 Nisan 5784 (leap year)
        $b = Hebrew::create(5786, 7, 15, 0, 0, 0, $this->utc); // 15 Nisan 5786

        self::assertSame(2, $a->diffInYears($b));
        self::assertSame(2, $b->diffInYears($a));
        self::assertSame(-2, $a->diffInYears($b, false));
        self::assertSame(2, $b->diffInYears($a, false));
        self::assertSame(1, $a->diffInYears($b->subDays(1)));
    }

    /* ---------------- month and year lengths ---------------- */

    /**
     * @return array<string, array{int, int, list<int>}>
     */
    public static function yearTypes(): array
    {
        return [
            'deficient regular 353' => [5710, 353, [30, 29, 29, 29, 30, 29, 30, 29, 30, 29, 30, 29]],
            'regular 354' => [5701, 354, [30, 29, 30, 29, 30, 29, 30, 29, 30, 29, 30, 29]],
            'complete regular 355' => [5702, 355, [30, 30, 30, 29, 30, 29, 30, 29, 30, 29, 30, 29]],
            'deficient leap 383' => [5703, 383, [30, 29, 29, 29, 30, 30, 29, 30, 29, 30, 29, 30, 29]],
            'regular leap 384' => [5711, 384, [30, 29, 30, 29, 30, 30, 29, 30, 29, 30, 29, 30, 29]],
            'complete leap 385' => [5700, 385, [30, 30, 30, 29, 30, 30, 29, 30, 29, 30, 29, 30, 29]],
        ];
    }

    /**
     * @param list<int> $months
     */
    #[DataProvider('yearTypes')]
    public function test_month_lengths_of_each_year_type(int $year, int $days, array $months): void
    {
        self::assertSame($days, Hebrew::daysInYear($year));
        self::assertSame(count($months), Hebrew::monthsInYear($year));

        foreach ($months as $i => $length) {
            self::assertSame($length, Hebrew::daysInMonth($year, $i + 1), "{$year} month ".($i + 1));
        }
    }

    public function test_year_and_month_guards(): void
    {
        self::assertSame(355, Hebrew::daysInYear(1));
        self::assertSame(385, Hebrew::daysInYear(Hebrew::MAX_YEAR));
        self::assertSame(30, Hebrew::daysInMonth(1, 1));

        foreach ([0, -5, Hebrew::MAX_YEAR + 1] as $year) {
            $this->assertDateException(static fn (): int => Hebrew::daysInYear($year), ErrorCode::DateOutOfRange, '/^Hebrew year out of the supported range: /');
            // The year is checked before the month, even for an impossible month.
            $this->assertDateException(static fn (): int => Hebrew::daysInMonth($year, 99), ErrorCode::DateOutOfRange, '/^Hebrew year out of the supported range: /');
        }

        $this->assertDateException(static fn (): int => Hebrew::daysInMonth(5785, 13), ErrorCode::InvalidDate, '/^Invalid Hebrew month: 13$/');
        $this->assertDateException(static fn (): int => Hebrew::daysInMonth(5785, 0), ErrorCode::InvalidDate, '/^Invalid Hebrew month: 0$/');
    }

    public function test_is_leap_year_follows_the_19_year_cycle(): void
    {
        // Years 3, 6, 8, 11, 14, 17 and 19 of each 19-year cycle (5776 = 19 * 304).
        $leapResidues = [3, 6, 8, 11, 14, 17, 0];
        for ($y = 5776; $y < 5776 + 19 * 3; $y++) {
            self::assertSame(in_array($y % 19, $leapResidues, true), Hebrew::isLeapYear($y), (string) $y);
        }
        self::assertTrue(Hebrew::isLeapYear(5776)); // year 19 of the cycle
        self::assertFalse(Hebrew::isLeapYear(0 - 19 + 1)); // -18: residue 1 of the cycle
        self::assertTrue(Hebrew::isLeapYear(-16));  // residue 3 of the cycle, negative year
        self::assertTrue(Hebrew::isLeapYear(-19));  // residue 0
    }

    /* ---------------- Rosh Hashanah postponements ---------------- */

    /**
     * Years whose new year is moved by the second and third postponement rules
     * (the year lengths would otherwise be impossible).
     *
     * @return array<string, array{int, string}>
     */
    public static function postponedNewYears(): array
    {
        return [
            // moved by two days because the previous molad rule would give a 356-day year
            '5718' => [5718, '1957-09-26'],
            '5745' => [5745, '1984-09-27'],
            '5789' => [5789, '2028-09-21'],
            '5796' => [5796, '2035-10-04'],
            // moved by one day because the previous year would be only 382 days long
            '5766' => [5766, '2005-10-04'],
            // unmoved neighbours
            '5717' => [5717, '1956-09-06'],
            '5719' => [5719, '1958-09-15'],
        ];
    }

    #[DataProvider('postponedNewYears')]
    public function test_postponed_new_years(int $year, string $gregorian): void
    {
        self::assertSame($gregorian, Hebrew::create($year, 1, 1, 0, 0, 0, $this->utc)->toGregorian()->format('Y-m-d'));
        self::assertSame([$year, 1, 1], $this->ymd(Hebrew::make(new DateTimeImmutable($gregorian, $this->utc))));

        [$gy, $gm, $gd] = array_map('intval', explode('-', $gregorian));
        self::assertSame([$year, 1, 1], Hebrew::gregorianToHebrew($gy, $gm, $gd));
    }

    public function test_conversion_at_both_ends_of_the_gregorian_range(): void
    {
        self::assertSame([3762, 1, 1], Hebrew::gregorianToHebrew(1, 9, 6));
        self::assertSame([3761, 12, 29], Hebrew::gregorianToHebrew(1, 9, 5)); // 3761 is a regular year
        self::assertSame([3761, 4, 18], Hebrew::gregorianToHebrew(1, 1, 1));
        self::assertSame([13759, 13, 29], Hebrew::gregorianToHebrew(9999, 11, 3));
    }
}
