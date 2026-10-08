<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Known-answer tests for the Jalali calendar: range edges and error codes, the
 * month wrap and clamping of addMonths(), delta bounds, createFromFormat() parsing
 * rules and the zone tokens of format().
 *
 * Reference points: 2026-03-21 = 1 Farvardin 1405, 1970-01-01 = 11 Dey 1348,
 * 1 Farvardin -620 = 0001-03-21, 30 Esfand 9377 = 9999-03-20 (9377 is a leap year).
 *
 * Range errors raised by addMonths()/addYears()/jalaliToGregorian() are checked by
 * exception type and message; the error code of those paths is not pinned here.
 */
final class JalaliKnownAnswerTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    private function assertDateException(callable $call, ?ErrorCode $code, string $messagePattern): void
    {
        try {
            $call();
            self::fail('InvalidDateException expected');
        } catch (InvalidDateException $e) {
            if ($code !== null) {
                self::assertSame($code, $e->getErrorCode(), $e->getMessage());
            }
            self::assertMatchesRegularExpression($messagePattern, $e->getMessage());
        }
    }

    /** @return list<int> */
    private function ymd(Jalali $j): array
    {
        return [$j->getYear(), $j->getMonth(), $j->getDay()];
    }

    /* ---------------- factories ---------------- */

    public function test_make_from_timestamp_keeps_the_given_zone(): void
    {
        $j = Jalali::make(0, $this->utc);
        self::assertSame([1348, 10, 11], $this->ymd($j));
        self::assertSame(0, $j->getTimestamp());
        self::assertSame('UTC', $j->getTimezone()->getName());

        $tehran = Jalali::make(0, new DateTimeZone('Asia/Tehran'));
        self::assertSame('Asia/Tehran', $tehran->getTimezone()->getName());
        self::assertSame([3, 30], [$tehran->getHour(), $tehran->getMinute()]);

        self::assertSame([1405, 1, 1], $this->ymd(Jalali::make(1774051200, $this->utc))); // 2026-03-21 00:00:00 UTC
    }

    public function test_make_from_timestamp_without_zone_uses_the_default_zone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tehran');
        try {
            $j = Jalali::make(0);
        } finally {
            date_default_timezone_set($previous);
        }

        self::assertSame('Asia/Tehran', $j->getTimezone()->getName());
        self::assertSame([3, 30], [$j->getHour(), $j->getMinute()]);
    }

    public function test_own_format_string_threshold_is_year_1700(): void
    {
        $j = Jalali::make('1405/01/01 13:45:27', $this->utc);
        self::assertSame('2026-03-21 13:45:27', $j->toGregorian()->format('Y-m-d H:i:s'));

        self::assertSame(1699, Jalali::make('1699/01/01', $this->utc)->getYear());

        // 1700 and later are Gregorian text.
        self::assertSame('1700-01-01', Jalali::make('1700/01/01', $this->utc)->toGregorian()->format('Y-m-d'));
    }

    public function test_create_defaults_to_midnight(): void
    {
        $j = Jalali::create(1405, 1, 1, timezone: $this->utc);

        self::assertSame([0, 0, 0], [$j->getHour(), $j->getMinute(), $j->getSecond()]);
        self::assertSame('00:00:00', $j->format('H:i:s'));
    }

    public function test_create_error_code_tells_out_of_range_from_impossible(): void
    {
        $this->assertDateException(static fn (): Jalali => Jalali::create(-620, 13, 1), ErrorCode::InvalidDate, '/^Invalid Jalali date: -620\/13\/1$/');
        $this->assertDateException(static fn (): Jalali => Jalali::create(9377, 13, 1), ErrorCode::InvalidDate, '/^Invalid Jalali date: 9377\/13\/1$/');
        $this->assertDateException(static fn (): Jalali => Jalali::create(1404, 12, 30), ErrorCode::InvalidDate, '/^Invalid Jalali date: 1404\/12\/30$/');

        $this->assertDateException(static fn (): Jalali => Jalali::create(-621, 1, 1), ErrorCode::DateOutOfRange, '/^Invalid Jalali date: -621\/1\/1$/');
        $this->assertDateException(static fn (): Jalali => Jalali::create(9378, 1, 1), ErrorCode::DateOutOfRange, '/^Invalid Jalali date: 9378\/1\/1$/');

        self::assertSame('0001-03-21', Jalali::create(-620, 1, 1, timezone: $this->utc)->toGregorian()->format('Y-m-d'));
        self::assertSame('9999-03-20', Jalali::create(9377, 12, 30, timezone: $this->utc)->toGregorian()->format('Y-m-d'));
    }

    /* ---------------- createFromFormat ---------------- */

    public function test_create_from_format_reads_every_time_component(): void
    {
        $j = Jalali::createFromFormat('Y/m/d H:i:s', '1404/01/15 10:20:30', $this->utc);
        self::assertSame([1404, 1, 15, 10, 20, 30], [$j->getYear(), $j->getMonth(), $j->getDay(), $j->getHour(), $j->getMinute(), $j->getSecond()]);

        $persian = Jalali::createFromFormat('Y/m/d H:i:s', '۱۴۰۴/۰۱/۱۵ ۱۰:۲۰:۳۰', $this->utc);
        self::assertSame('1404/01/15 10:20:30', $persian->format('Y/m/d H:i:s'));
    }

    public function test_create_from_format_without_a_time_part_is_midnight(): void
    {
        $j = Jalali::createFromFormat('Y/m/d', '1404/01/15', $this->utc);

        self::assertSame([0, 0, 0], [$j->getHour(), $j->getMinute(), $j->getSecond()]);
        self::assertSame('1404/01/15 07:00:00', Jalali::createFromFormat('Y/m/d H', '1404/01/15 07', $this->utc)->format('Y/m/d H:i:s'));
    }

    public function test_create_from_format_accepts_days_that_exist_only_in_jalali(): void
    {
        // 31 Ordibehesht and 31 Shahrivar exist; February-style "invalid date" warnings must not block them.
        self::assertSame([1404, 2, 31], $this->ymd(Jalali::createFromFormat('Y/m/d', '1404/02/31', $this->utc)));
        self::assertSame([1404, 6, 31], $this->ymd(Jalali::createFromFormat('Y/m/d', '1404/06/31', $this->utc)));
    }

    /** @return array<string, array{string, string}> */
    public static function unparsable(): array
    {
        return [
            'year missing' => ['m/d', '01/15'],
            'month missing' => ['Y/d', '1404/15'],
            'day missing' => ['Y/m', '1404/01'],
            'trailing data' => ['Y/m/d', '1404/01/15 extra'],
            'time out of range' => ['Y/m/d H:i:s', '1404/01/15 25:00:00'],
            'minutes out of range' => ['Y/m/d H:i:s', '1404/01/15 10:61:00'],
            'text instead of a date' => ['Y/m/d', 'not a date'],
        ];
    }

    #[DataProvider('unparsable')]
    public function test_create_from_format_rejects_unparsable_input_with_one_message(string $format, string $time): void
    {
        $this->assertDateException(
            fn (): Jalali => Jalali::createFromFormat($format, $time, $this->utc),
            ErrorCode::InvalidDate,
            '/^Unable to parse \'.*\' with format \'.*\'$/',
        );
    }

    public function test_create_from_format_still_validates_the_jalali_date(): void
    {
        $this->assertDateException(
            fn (): Jalali => Jalali::createFromFormat('Y/m/d', '1404/12/30', $this->utc),
            ErrorCode::InvalidDate,
            '/^Invalid Jalali date: 1404\/12\/30$/',
        );
    }

    /* ---------------- format tokens ---------------- */

    /** @return array<string, array{int, string, string, string, string, string}> */
    public static function clockHours(): array
    {
        return [
            'midnight' => [0, 'ق.ظ', 'قبل از ظهر', '12', '12', '0'],
            'one am' => [1, 'ق.ظ', 'قبل از ظهر', '1', '01', '1'],
            'eleven am' => [11, 'ق.ظ', 'قبل از ظهر', '11', '11', '11'],
            'noon' => [12, 'ب.ظ', 'بعد از ظهر', '12', '12', '12'],
            'one pm' => [13, 'ب.ظ', 'بعد از ظهر', '1', '01', '13'],
            'eleven pm' => [23, 'ب.ظ', 'بعد از ظهر', '11', '11', '23'],
        ];
    }

    #[DataProvider('clockHours')]
    public function test_twelve_hour_tokens(int $hour, string $a, string $upperA, string $g, string $h, string $G): void
    {
        $date = Jalali::create(1405, 1, 1, $hour, 5, 9, $this->utc);

        self::assertSame($a, $date->format('a'));
        self::assertSame($upperA, $date->format('A'));
        self::assertSame($g, $date->format('g'));
        self::assertSame($h, $date->format('h'));
        self::assertSame($G, $date->format('G'));
    }

    public function test_weekday_numbering_is_saturday_first(): void
    {
        // 2026-03-21 is a Saturday, 2026-03-27 a Friday.
        $saturday = Jalali::create(1405, 1, 1, 0, 0, 0, $this->utc);
        $friday = $saturday->addDays(6);

        self::assertSame(0, $saturday->getDayOfWeek());
        self::assertSame('0', $saturday->format('w'));
        self::assertSame('1', $saturday->format('N'));
        self::assertSame('شنبه', $saturday->format('l'));
        self::assertSame(6, $friday->getDayOfWeek());
        self::assertSame('6', $friday->format('w'));
        self::assertSame('7', $friday->format('N'));
        self::assertSame('جمعه', $friday->format('l'));
    }

    public function test_zone_tokens_are_delegated_to_the_instant(): void
    {
        $utc = Jalali::create(1405, 4, 10, 13, 45, 27, $this->utc); // 2026-07-01
        self::assertSame(
            '1782913527 UTC UTC +00:00 Z +0000 0 0 000000 000',
            $utc->format('U e T P p O Z I u v'),
        );

        $berlin = Jalali::create(1405, 4, 10, 13, 45, 27, new DateTimeZone('Europe/Berlin'));
        self::assertSame(
            '1782906327 Europe/Berlin CEST +02:00 +02:00 +0200 7200 1 000000 000',
            $berlin->format('U e T P p O Z I u v'),
        );
        self::assertSame('1405-04-10T13:45:27+02:00', $berlin->format('c'));
        self::assertSame('Wed, 01 Jul 2026 13:45:27 +0200', $berlin->format('r'));
    }

    /* ---------------- addMonths / addYears ---------------- */

    public function test_add_months_wraps_over_the_year_boundary(): void
    {
        $date = Jalali::create(1404, 1, 15, 6, 7, 8, $this->utc);

        self::assertSame([1403, 12, 15], $this->ymd($date->subMonths(1)));
        self::assertSame([1403, 11, 15], $this->ymd($date->subMonths(2)));
        self::assertSame([1402, 12, 15], $this->ymd($date->subMonths(13)));
        self::assertSame([1404, 12, 15], $this->ymd($date->addMonths(11)));
        self::assertSame([1405, 1, 15], $this->ymd($date->addMonths(12)));
        self::assertSame([1404, 1, 15], $this->ymd($date->addMonths(0)));
        self::assertSame('06:07:08', $date->subMonths(1)->format('H:i:s'));
    }

    public function test_add_months_and_years_clamp_the_day(): void
    {
        $zone = $this->utc;

        self::assertSame([1404, 12, 29], $this->ymd(Jalali::create(1404, 7, 30, 0, 0, 0, $zone)->addMonths(5)));
        self::assertSame([1404, 7, 30], $this->ymd(Jalali::create(1404, 1, 31, 0, 0, 0, $zone)->addMonths(6)));
        // 1403 is a leap year (30 Esfand), 1404 is not.
        self::assertSame([1404, 12, 29], $this->ymd(Jalali::create(1403, 12, 30, 0, 0, 0, $zone)->addYears(1)));
    }

    public function test_add_years_is_twelve_months_per_year(): void
    {
        $date = Jalali::create(1404, 1, 15, 6, 7, 8, $this->utc);

        self::assertSame([1406, 1, 15], $this->ymd($date->addYears(2)));
        self::assertSame([1403, 1, 15], $this->ymd($date->subYears(1)));
        self::assertSame([1405, 1, 15], $this->ymd($date->addYears(1)));
        self::assertSame([1404, 1, 15], $this->ymd($date->addYears(0)));
    }

    public function test_add_months_delta_bound(): void
    {
        $date = Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc);
        $bound = (Jalali::MAX_YEAR - Jalali::MIN_YEAR + 2) * 12; // 119988

        self::assertSame(119988, $bound);

        $this->assertDateException(
            static fn (): Jalali => $date->addMonths($bound),
            null,
            '/^Jalali year out of the supported range: 11403$/',
        );
        $this->assertDateException(
            static fn (): Jalali => $date->addMonths(-$bound),
            null,
            '/^Jalali year out of the supported range: -8595$/',
        );
        $this->assertDateException(
            static fn (): Jalali => $date->addMonths($bound + 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by 119989 months: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Jalali => $date->addMonths(-$bound - 1),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by -119989 months: out of the supported range\.$/',
        );
    }

    public function test_add_years_delta_bound(): void
    {
        $date = Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc);

        $this->assertDateException(
            static fn (): Jalali => $date->addYears(9999),
            null,
            '/^Jalali year out of the supported range: 11403$/',
        );
        $this->assertDateException(
            static fn (): Jalali => $date->subYears(9999),
            null,
            '/^Jalali year out of the supported range: -8595$/',
        );
        $this->assertDateException(
            static fn (): Jalali => $date->addYears(10000),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by 10000 years: out of the supported range\.$/',
        );
        $this->assertDateException(
            static fn (): Jalali => $date->subYears(10000),
            ErrorCode::DateOutOfRange,
            '/^Cannot shift a date by -10000 years: out of the supported range\.$/',
        );
    }

    public function test_range_edges_of_add_months_and_years(): void
    {
        self::assertSame([-620, 1, 1], $this->ymd(Jalali::create(-619, 1, 1, 0, 0, 0, $this->utc)->subYears(1)));
        self::assertSame([-620, 12, 1], $this->ymd(Jalali::create(-619, 1, 1, 0, 0, 0, $this->utc)->subMonths(1)->subMonths(0)->addMonths(0)));
        self::assertSame([9377, 1, 1], $this->ymd(Jalali::create(9376, 1, 1, 0, 0, 0, $this->utc)->addYears(1)));
        self::assertSame([9377, 12, 1], $this->ymd(Jalali::create(9377, 11, 1, 0, 0, 0, $this->utc)->addMonths(1)));

        $this->assertDateException(
            fn (): Jalali => Jalali::create(-620, 1, 1, 0, 0, 0, $this->utc)->subMonths(1),
            null,
            '/^Jalali year out of the supported range: -621$/',
        );
        $this->assertDateException(
            fn (): Jalali => Jalali::create(9377, 12, 29, 0, 0, 0, $this->utc)->addMonths(1),
            null,
            '/^Jalali year out of the supported range: 9378$/',
        );
        $this->assertDateException(
            fn (): Jalali => Jalali::create(9377, 12, 29, 0, 0, 0, $this->utc)->addYears(1),
            null,
            '/^Jalali year out of the supported range: 9378$/',
        );
    }

    /* ---------------- calendar arithmetic ---------------- */

    /** @return array<string, array{int, int, int}> */
    public static function impossibleArguments(): array
    {
        return [
            'month zero' => [1404, 0, 1],
            'month thirteen' => [1404, 13, 1],
            'day zero' => [1404, 1, 0],
            'day thirty-two' => [1404, 1, 32],
            'year below min' => [-621, 1, 1],
            'year above max' => [9378, 1, 1],
        ];
    }

    #[DataProvider('impossibleArguments')]
    public function test_jalali_to_gregorian_rejects_each_impossible_argument(int $y, int $m, int $d): void
    {
        $this->assertDateException(
            static fn (): array => Jalali::jalaliToGregorian($y, $m, $d),
            null,
            '/^Jalali date out of the supported range: /',
        );
    }

    public function test_jalali_to_gregorian_lets_the_day_roll_forward(): void
    {
        self::assertSame([2025, 5, 21], Jalali::jalaliToGregorian(1404, 2, 31));
        self::assertSame([2026, 3, 22], Jalali::jalaliToGregorian(1404, 12, 31)); // 1404 has 29 days in Esfand
    }

    public function test_is_valid_for_end_of_year(): void
    {
        self::assertTrue(Jalali::isValid(1403, 12, 30));
        self::assertFalse(Jalali::isValid(1404, 12, 30));
        self::assertTrue(Jalali::isValid(-620, 1, 1));
        self::assertFalse(Jalali::isValid(-621, 12, 29));
        self::assertTrue(Jalali::isValid(9377, 12, 30));
        self::assertFalse(Jalali::isValid(9378, 1, 1));
        self::assertFalse(Jalali::isValid(1404, 0, 1));
        self::assertFalse(Jalali::isValid(1404, 13, 1));
        self::assertFalse(Jalali::isValid(1404, 1, 0));
        self::assertFalse(Jalali::isValid(1404, 1, 32));
    }
}
