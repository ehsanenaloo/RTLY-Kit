<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Contracts\CalendarDate;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Number\Digits;

/**
 * make() with a string: text that starts like a date of the calendar itself
 * is read exactly as Y/m/d[ H:i[:s[.u]]] or rejected; it never silently turns
 * into a Gregorian value.
 */
final class MakeStringParsingTest extends TestCase
{
    /** @return iterable<string, array{class-string<CalendarDate>, string}> class and a year that belongs to the calendar */
    public static function calendars(): iterable
    {
        yield 'Jalali' => [Jalali::class, '1403'];
        yield 'Hijri' => [Hijri::class, '1446'];
        yield 'Hebrew' => [Hebrew::class, '5785'];
    }

    /** @return iterable<string, array{class-string<CalendarDate>, string}> */
    public static function inexactOwnStrings(): iterable
    {
        $shapes = [
            'zone name Asia/Tehran' => '%s-01-01T10:00:00 Asia/Tehran',
            'zone name glued to the time' => '%s-01-01T10:00:00UTC',
            'designator after a space' => '%s-01-01T10:00:00 Z',
            'one-digit offset' => '%s-01-01T10:00:00+3',
            'three-digit offset' => '%s-01-01T10:00:00+030',
            'designator without a time' => '%s-01-01Z',
            'lowercase offset sign word' => '%s-01-01T10:00:00GMT+3',
            'time with colon only' => '%s-01-01T10:',
            'zone name after the time' => '%s/01/01 10:00:00 UTC',
            'one-digit minutes' => '%s/01/01 10:0',
            'one-digit seconds' => '%s/1/1 9:05:3',
            'am/pm marker' => '%s/01/01 10:00 PM',
            'year and month only' => '%s-01',
            'year and month with slash' => '%s/01',
            'three-digit day' => '%s/01/011',
            'trailing words' => '%s/01/01 later',
            'seven fraction digits' => '%s/01/01 10:00:00.1234567',
        ];
        foreach (self::calendars() as $name => [$class, $year]) {
            foreach ($shapes as $label => $shape) {
                yield "{$name}: {$label}" => [$class, sprintf($shape, $year)];
            }
        }
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('inexactOwnStrings')]
    public function test_a_string_that_starts_like_an_own_date_but_is_not_exact_throws(string $class, string $text): void
    {
        try {
            $class::make($text, new DateTimeZone('UTC'));
            self::fail("{$class}::make('{$text}') should throw");
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
            self::assertStringContainsString('DateTimeImmutable', $e->getMessage());
        }
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_a_bare_digit_run_is_not_read_as_a_clock_time(string $class, string $year): void
    {
        foreach ([$year, $year.'0101', '123', '1234567'] as $text) {
            try {
                $class::make($text, new DateTimeZone('UTC'));
                self::fail("{$class}::make('{$text}') should throw");
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode(), $text);
            }
        }
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_eight_digits_of_another_calendar_stay_a_compact_gregorian_date(string $class): void
    {
        $date = $class::make('20240101', new DateTimeZone('UTC'));

        self::assertSame('2024-01-01', $date->toGregorian()->format('Y-m-d'));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_exact_shapes_are_read_in_the_own_calendar(string $class, string $year): void
    {
        $utc = new DateTimeZone('UTC');
        $expected = $class::create((int) $year, 1, 1, 9, 5, 0, $utc);

        foreach (["{$year}/1/1 9:05", "{$year}-01-01 09:05:00", "{$year}/01/01T9:05", "{$year}/01/01  9:05"] as $text) {
            self::assertSame($expected->getTimestamp(), $class::make($text, $utc)->getTimestamp(), $text);
        }

        $persian = Digits::toPersian($year);
        self::assertSame($expected->getTimestamp(), $class::make("{$persian}/۰۱/۰۱ ۰۹:۰۵", $utc)->getTimestamp());
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_no_break_space_zwnj_and_direction_marks_count_as_spaces(string $class, string $year): void
    {
        $utc = new DateTimeZone('UTC');
        $expected = $class::create((int) $year, 1, 1, 0, 0, 0, $utc)->getTimestamp();

        foreach (["{$year}/01/01\u{00A0}", "{$year}/01/01\u{200C}", "{$year}/01/01\u{200F}", "\u{200E}{$year}/01/01", "\u{00A0}{$year}/01/01\u{200F}\u{200C}"] as $text) {
            self::assertSame($expected, $class::make($text, $utc)->getTimestamp(), json_encode($text) ?: '');
        }

        $withTime = $class::make("{$year}/01/01\u{00A0}10:00", $utc);
        self::assertSame($class::create((int) $year, 1, 1, 10, 0, 0, $utc)->getTimestamp(), $withTime->getTimestamp());
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_fractional_seconds_are_accepted(string $class, string $year): void
    {
        $date = $class::make("{$year}/01/01 10:00:00.5", new DateTimeZone('UTC'));
        self::assertSame('500000', $date->format('u'));
        self::assertSame('10:00:00', $date->format('H:i:s'));

        self::assertSame('000123', $class::make("{$year}-01-01T10:00:00.000123", new DateTimeZone('UTC'))->format('u'));
        self::assertSame('999999', $class::make("{$year}/01/01 23:59:59.999999", new DateTimeZone('UTC'))->format('u'));
    }

    public function test_five_digit_years_are_out_of_range_for_jalali_and_hijri(): void
    {
        foreach ([Jalali::class, Hijri::class] as $class) {
            foreach (['12345/01/01', '10000-01-01', '12345/01/01 10:00', '12345/01/01 junk'] as $text) {
                try {
                    $class::make($text);
                    self::fail("{$class}::make('{$text}') should throw");
                } catch (InvalidDateException $e) {
                    self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode(), $text);
                }
            }
        }
    }

    public function test_hebrew_reads_five_digit_years_and_round_trips_them(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame(13759, Hebrew::make('13759/01/01', $utc)->getYear());
        self::assertSame('13759/01/01', Hebrew::make('13759/01/01', $utc)->toDateString());

        foreach ([10000, 10001, 12345, 13000, 13758, 13759] as $year) {
            $date = Hebrew::create($year, 1, 1, 0, 0, 0, $utc);
            $back = Hebrew::make($date->toDateString(), $utc);

            self::assertSame($date->getTimestamp(), $back->getTimestamp(), (string) $year);
            self::assertSame($year, $back->getYear());
        }

        $this->expectException(InvalidDateException::class);
        Hebrew::make('13760/01/01', $utc);
    }

    public function test_hebrew_five_digit_year_beyond_the_range_reports_out_of_range(): void
    {
        try {
            Hebrew::make('99999/01/01');
            self::fail('expected an exception');
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
        }
    }

    public function test_other_calendar_years_and_gregorian_text_still_go_to_the_gregorian_parser(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('2024-03-20T10:00:00+00:00', Jalali::make('2024-03-20T10:00:00Z', $utc)->toGregorian()->format('c'));
        self::assertSame('1403/01/01', Jalali::make('2024-03-20 10:00:00', $utc)->toDateString());
        self::assertSame('1800-01-01', Jalali::make('1800/01/01', $utc)->toGregorian()->format('Y-m-d'));
        self::assertSame('2024-03-20', Hebrew::make('2024-03-20', $utc)->toGregorian()->format('Y-m-d'));
        self::assertSame('2024-03-20', Hijri::make('March 20, 2024', $utc)->toGregorian()->format('Y-m-d'));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_a_nul_byte_in_the_text_is_an_invalid_date(string $class, string $year): void
    {
        foreach (["{$year}/01/01\0", "\0", "2024-03-20\0", "now\0"] as $text) {
            try {
                $class::make($text);
                self::fail('expected an exception');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
            }
        }
    }
}
