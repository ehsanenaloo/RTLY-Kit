<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Holiday;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Holiday\HolidaySource;

/**
 * Text input of the holiday calendar: Jalali date strings, Gregorian month starts and titles.
 */
final class HolidayCalendarInputTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function notJalaliDates(): array
    {
        return [
            'Gregorian year' => ['2027/05/05'],
            'Gregorian year with dashes' => ['2027-05-05'],
            'Gregorian year in Persian digits' => ['۲۰۲۷/۰۵/۰۵'],
            'first year read as Gregorian' => ['1700/01/01'],
            'with a time' => ['1405/02/03 10:00'],
            'relative text' => ['tomorrow'],
            'dots' => ['1405.02.03'],
            'spaces' => ['1405 02 03'],
            'two-digit year' => ['12/02/03'],
            'negative year' => ['-100/01/01'],
            'eight digits' => ['14050203'],
            'NUL at the end' => ["1405/02/03\0"],
            'NUL inside' => ["1405/02\0/03"],
            'invalid UTF-8' => ["1405/02/\xFF3"],
        ];
    }

    #[DataProvider('notJalaliDates')]
    public function test_a_holiday_date_string_must_be_a_jalali_date(string $date): void
    {
        foreach ([
            fn () => HolidayCalendar::default()->withHoliday($date, 'x'),
            fn () => HolidayCalendar::default()->withoutHoliday($date),
            fn () => HolidayCalendar::default()->withoutHoliday($date, 'x'),
        ] as $call) {
            try {
                $call();
                self::fail('expected InvalidDateException');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
            }
        }
    }

    public function test_a_gregorian_looking_year_is_not_silently_added_as_another_day(): void
    {
        // 2027-05-05 is 1406/02/15 in the Jalali calendar: it used to be accepted and added to that day.
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('not a Jalali year');

        HolidayCalendar::default()->withHoliday('2027/05/05', 'x');
    }

    public function test_jalali_date_strings_with_other_digits_and_the_last_jalali_year_before_1700_work(): void
    {
        $c = HolidayCalendar::default()
            ->withHoliday('۱۴۰۵/۰۲/۰۳', 'فارسی')
            ->withHoliday('١٤٠٥-٠٢-٠٤', 'عربی')
            ->withHoliday('1405/2/5', 'short')
            ->withHoliday(' 1699/10/05 ', 'old')
            ->withHoliday('0622/01/01', 'padded');

        self::assertSame(['فارسی'], $c->getTitles(1405, 2, 3));
        self::assertSame(['عربی'], $c->getTitles(1405, 2, 4));
        self::assertSame(['short'], $c->getTitles(1405, 2, 5));
        self::assertSame(['old'], $c->getTitles(1699, 10, 5));
        self::assertSame(['old'], $c->withoutHoliday('1699/10/04')->getTitles(1699, 10, 5));
        self::assertContains('padded', $c->getTitles(622, 1, 1));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badMonthStarts(): array
    {
        return [
            'slashes' => ['2026/03/20'],
            'dots' => ['2026.03.20'],
            'en dash' => ["2026\u{2013}03\u{2013}20"],
            'eight digits' => ['20260320'],
            'short month' => ['2026-3-20'],
            'NUL at the end' => ["2026-03-20\0"],
            'NUL at the start' => ["\0 2026-03-20"],
            'NUL inside' => ["2026-03\0-20"],
            'Persian digits with slashes' => ['۲۰۲۶/۰۳/۲۰'],
            'invalid UTF-8' => ["2026-\xFF-20"],
            'year zero' => ['0000-01-01'],
            'year 10000' => ['10000-01-01'],
            'impossible day' => ['2026-02-30'],
            'empty' => [''],
            'only spaces' => ['   '],
        ];
    }

    #[DataProvider('badMonthStarts')]
    public function test_a_hijri_month_start_string_that_is_not_a_gregorian_date_throws_a_library_exception(string $date): void
    {
        try {
            HolidayCalendar::default()->withHijriMonthStart(1447, 10, $date);
            self::fail('expected InvalidDateException');
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
        }
    }

    public function test_a_hijri_month_start_accepts_persian_and_arabic_digits_and_outer_spaces(): void
    {
        $plain = HolidayCalendar::default()->withHijriMonthStart(1447, 10, '2026-03-20');

        foreach (['۲۰۲۶-۰۳-۲۰', '٢٠٢٦-٠٣-٢٠', " 2026-03-20\n"] as $date) {
            self::assertEquals($plain, HolidayCalendar::default()->withHijriMonthStart(1447, 10, $date));
        }
    }

    public function test_config_month_start_keys_may_use_persian_digits(): void
    {
        self::assertEquals(
            HolidayCalendar::fromArray(['hijri_month_starts' => ['1447-10' => '2026-03-20']]),
            HolidayCalendar::fromArray(['hijri_month_starts' => ['۱۴۴۷-۱۰' => '2026-03-20']]),
        );

        $this->expectException(InvalidDateException::class);
        HolidayCalendar::fromArray(['hijri_month_starts' => ["1447-10\n" => '2026-03-20']]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badTitles(): array
    {
        return [
            'NUL' => ["a\0b"],
            'unit separator' => ["a\x1Fb"],
            'DEL' => ["a\x7Fb"],
            'C1 control' => ["a\u{0085}b"],
            'tab inside' => ["a\tb"],
            'line separator' => ["a\u{2028}b"],
            'paragraph separator' => ["a\u{2029}b"],
        ];
    }

    #[DataProvider('badTitles')]
    public function test_titles_with_control_or_line_break_characters_are_rejected(string $title): void
    {
        foreach ([
            fn () => HolidayCalendar::default()->withHoliday('1405/02/03', $title),
            fn () => HolidayCalendar::default()->withoutHoliday('1405/02/03', $title),
        ] as $call) {
            try {
                $call();
                self::fail('expected InvalidDateException');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
            }
        }
    }

    public function test_a_title_with_a_zero_width_non_joiner_is_still_accepted(): void
    {
        $title = "می\u{200C}خواهم"; // ZWNJ is part of normal Persian spelling

        self::assertSame([$title], HolidayCalendar::default()->withHoliday('1405/02/03', $title)->getTitles(1405, 2, 3));
    }

    /* ---------------- the year range ---------------- */

    public function test_the_first_and_last_years_work_with_every_offset_and_both_sources(): void
    {
        foreach (range(-3, 3) as $offset) {
            foreach ([true, false] as $official) {
                $c = HolidayCalendar::default()->withIslamicOffset($offset)->withOfficialData($official);

                foreach ([Jalali::MIN_YEAR, Jalali::MIN_YEAR + 1, Jalali::MAX_YEAR] as $year) {
                    self::assertCount(10, $c->allTitles($year), "offset {$offset} year {$year}");
                    self::assertTrue($c->isHoliday($year, 1, 1));
                }
            }
        }
    }

    public function test_hijri_month_starts_at_both_ends_of_the_hijri_range_work(): void
    {
        foreach ([[Hijri::MIN_YEAR, 1, Jalali::MIN_YEAR], [Hijri::MAX_YEAR, 12, Jalali::MAX_YEAR]] as [$hijriYear, $hijriMonth, $jalaliYear]) {
            [$y, $m, $d] = Hijri::hijriToGregorian($hijriYear, $hijriMonth, 1);
            $c           = HolidayCalendar::default()->withHijriMonthStart($hijriYear, $hijriMonth, sprintf('%04d-%02d-%02d', $y, $m, $d));

            self::assertCount(10, $c->allTitles($jalaliYear));
        }
    }

    public function test_a_year_outside_the_range_throws_only_library_exceptions_everywhere(): void
    {
        $c = HolidayCalendar::default();

        foreach ([
            fn () => $c->isHoliday(Jalali::MAX_YEAR + 1, 1, 1),
            fn () => $c->getTitle(Jalali::MIN_YEAR - 1, 1, 1),
            fn () => $c->statusOf(99999, 1, 1),
            fn () => $c->sourceOf(Jalali::MAX_YEAR + 1),
            fn () => $c->sourceOf(Jalali::MIN_YEAR - 1),
            fn () => $c->withHoliday('9378/01/01', 'x'),
            fn () => $c->withoutHoliday('-621/01/01'),
            fn () => $c->withHijriMonthStart(Hijri::MAX_YEAR + 1, 1, '2026-03-20'),
            fn () => $c->withHijriMonthStart(0, 1, '2026-03-20'),
        ] as $call) {
            try {
                $call();
                self::fail('expected InvalidDateException');
            } catch (InvalidDateException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /* ---------------- reported years ---------------- */

    public function test_reported_years_are_marked_so_callers_can_see_the_set_may_be_incomplete(): void
    {
        $c = HolidayCalendar::default();

        foreach ([...range(1380, 1393), 1395] as $year) {
            self::assertSame(HolidaySource::Reported, $c->sourceOf($year), (string) $year);
        }
        foreach ([1394, ...range(1396, 1405)] as $year) {
            self::assertSame(HolidaySource::Official, $c->sourceOf($year), (string) $year);
        }
    }
}
