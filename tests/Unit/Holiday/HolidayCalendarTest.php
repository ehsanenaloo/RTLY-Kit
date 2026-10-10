<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Holiday;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Holiday\HolidayEntry;
use RtlyKit\Holiday\HolidayOrigin;
use RtlyKit\Holiday\HolidaySource;
use RtlyKit\Holiday\IranHolidays;

/**
 * Known-answer tests for HolidayCalendar. Official dates come from resources/data/iran-official-holidays.php
 * (University of Tehran official yearly calendar); estimated dates are the Umm al-Qura table, e.g.
 * 1 Shawwal 1447 = 2026-03-20, 1 Rabi I 1446 = 2024-09-04.
 */
final class HolidayCalendarTest extends TestCase
{
    private const EID = 'عید فطر';
    private const EID_SECOND = 'تعطیل عید فطر';
    private const REZA = 'شهادت امام رضا';
    private const ASKARI = 'شهادت امام حسن عسکری';
    private const SADIQ = 'شهادت امام جعفر صادق';
    private const NATURE = 'روز طبیعت';
    private const NOWRUZ = 'جشن نوروز';
    private const NOWRUZ_NEXT = 'عید نوروز';

    private function calendar(): HolidayCalendar
    {
        return HolidayCalendar::default();
    }

    /**
     * @param  list<HolidayEntry>  $entries
     * @return list<array{string, HolidayOrigin}>
     */
    private static function pairs(array $entries): array
    {
        return array_map(static fn (HolidayEntry $e): array => [$e->title, $e->origin], $entries);
    }

    /* ---------------- immutability ---------------- */

    public function test_every_with_method_returns_a_new_instance_and_leaves_the_original_alone(): void
    {
        $base = $this->calendar();
        $before = $base->getTitles(1405, 1, 1);

        $variants = [
            $base->withIslamicOffset(2),
            $base->withHijriMonthStart(1447, 10, '2026-03-21'),
            $base->withHoliday('1405/02/03', 'X'),
            $base->withoutHoliday('1405/01/01'),
            $base->withOfficialData(false),
        ];

        foreach ($variants as $variant) {
            self::assertNotSame($base, $variant);
        }
        self::assertSame($before, $base->getTitles(1405, 1, 1));
        self::assertSame([], $base->getTitles(1405, 2, 3));
        self::assertSame(HolidaySource::Official, $base->sourceOf(1405));
    }

    /* ---------------- official data and source ---------------- */

    public function test_default_calendar_uses_official_dates(): void
    {
        $c = $this->calendar();

        self::assertSame([self::NOWRUZ, self::EID], $c->getTitles(1405, 1, 1));
        self::assertSame([self::NOWRUZ_NEXT, self::EID_SECOND], $c->getTitles(1405, 1, 2));
        self::assertSame([self::EID], $c->getTitles(1404, 1, 11));
        self::assertSame([], $c->getTitles(1404, 1, 10));
        self::assertSame([self::REZA], $c->getTitles(1403, 6, 14));
        self::assertSame([], $c->getTitles(1403, 6, 13));
        // 13 Farvardin 1403: Nature Day is fixed, the official table adds Imam Ali's martyrdom
        self::assertSame([self::NATURE, 'شهادت امام علی'], $c->getTitles(1403, 1, 13));
    }

    public function test_without_official_data_the_umm_al_qura_estimate_is_used(): void
    {
        $c = $this->calendar()->withOfficialData(false);

        self::assertSame(['ملی شدن صنعت نفت', self::EID], $c->getTitles(1404, 12, 29));
        self::assertSame([self::NOWRUZ, self::EID_SECOND], $c->getTitles(1405, 1, 1));
        // 1404/01/10 is Eid al-Fitr in the estimate, the official table has it a day later
        self::assertSame([self::EID_SECOND], $c->getTitles(1404, 1, 11));
        self::assertSame([self::EID], $c->getTitles(1404, 1, 10));
        self::assertTrue($c->isHoliday(1403, 6, 13));
    }

    public function test_source_of_each_year(): void
    {
        $c = $this->calendar();

        self::assertSame(HolidaySource::Estimated, $c->sourceOf(1379));
        self::assertSame(HolidaySource::Reported, $c->sourceOf(1380));
        self::assertSame(HolidaySource::Reported, $c->sourceOf(1393));
        self::assertSame(HolidaySource::Official, $c->sourceOf(1394));
        self::assertSame(HolidaySource::Reported, $c->sourceOf(1395));
        self::assertSame(HolidaySource::Official, $c->sourceOf(1396));
        self::assertSame(HolidaySource::Official, $c->sourceOf(1405));
        self::assertSame(HolidaySource::Estimated, $c->sourceOf(1406));
        self::assertSame(HolidaySource::Estimated, $c->sourceOf(1500));

        self::assertSame(HolidaySource::Estimated, $c->withOfficialData(false)->sourceOf(1405));
        self::assertSame(HolidaySource::Official, IranHolidays::sourceOf(1405));
        self::assertSame('reported', HolidaySource::Reported->value);
    }

    public function test_source_of_rejects_years_outside_the_supported_range(): void
    {
        foreach ([Jalali::MIN_YEAR - 1, Jalali::MAX_YEAR + 1, 99999] as $year) {
            try {
                $this->calendar()->sourceOf($year);
                self::fail('expected exception');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
            }
        }
    }

    public function test_status_of_reports_the_origin_of_every_title(): void
    {
        $c = $this->calendar();

        self::assertSame([[self::EID, HolidayOrigin::Official]], self::pairs($c->statusOf(1404, 1, 11)));
        // 1395 is reported (single source), 1406 is estimated
        self::assertSame([[self::EID, HolidayOrigin::Reported]], self::pairs($c->statusOf(1395, 4, 16)));
        self::assertSame([[self::NATURE, HolidayOrigin::Fixed], [self::SADIQ, HolidayOrigin::Estimated]], self::pairs($c->statusOf(1406, 1, 13)));
        self::assertSame([['تاسوعای حسینی', HolidayOrigin::Estimated]], self::pairs($c->statusOf(1406, 3, 24)));
        self::assertSame([], $c->statusOf(1406, 2, 2));
        self::assertSame(self::pairs($c->statusOf(1404, 1, 11)), self::pairs($c->statusOf(Jalali::create(1404, 1, 11))));
    }

    public function test_the_static_facade_matches_the_default_calendar(): void
    {
        $default = $this->calendar();

        foreach ([1380, 1395, 1403, 1405, 1406, 1450] as $year) {
            self::assertSame($default->allTitles($year), IranHolidays::allTitles($year), "year $year");
            self::assertSame($default->all($year), IranHolidays::all($year), "year $year");
            self::assertSame($default->allFixed($year), IranHolidays::allFixed($year), "year $year");
        }
        self::assertEquals($default, IranHolidays::calendar());
    }

    /* ---------------- islamic offset ---------------- */

    public function test_offset_shifts_estimated_islamic_holidays_only(): void
    {
        $c = $this->calendar();

        // 1406 has no official data: Tasua/Ashura 1449 estimate = 1406/03/24 and 03/25
        self::assertSame(['تاسوعای حسینی'], $c->getTitles(1406, 3, 24));

        $later = $c->withIslamicOffset(1);
        self::assertSame([], $later->getTitles(1406, 3, 24));
        self::assertSame(['تاسوعای حسینی'], $later->getTitles(1406, 3, 25));
        self::assertSame(['عاشورای حسینی'], $later->getTitles(1406, 3, 26));

        $earlier = $c->withIslamicOffset(-1);
        self::assertSame(['تاسوعای حسینی'], $earlier->getTitles(1406, 3, 23));
        self::assertSame(['عاشورای حسینی'], $earlier->getTitles(1406, 3, 24));

        $max = $c->withIslamicOffset(3);
        self::assertSame(['تاسوعای حسینی'], $max->getTitles(1406, 3, 27));
    }

    public function test_offset_never_moves_fixed_or_official_holidays(): void
    {
        $shifted = $this->calendar()->withIslamicOffset(3);

        // official year unchanged
        self::assertSame([self::EID], $shifted->getTitles(1404, 1, 11));
        self::assertSame([], $shifted->getTitles(1404, 1, 14));
        // fixed holidays unchanged
        self::assertSame(['روز جمهوری اسلامی'], $shifted->getTitles(1406, 1, 12));
        self::assertSame('پیروزی انقلاب اسلامی', $shifted->getTitle(1406, 11, 22));
    }

    public function test_offset_moves_the_estimate_off_a_fixed_holiday(): void
    {
        // 25 Shawwal 1448 estimate is 1406/01/13 (Nature Day); +1 moves it to the 14th, Nature Day stays
        $shifted = $this->calendar()->withIslamicOffset(1);

        self::assertSame([self::NATURE], $shifted->getTitles(1406, 1, 13));
        self::assertSame([self::SADIQ], $shifted->getTitles(1406, 1, 14));
    }

    public function test_offset_range_is_enforced(): void
    {
        foreach ([-4, 4, 100] as $bad) {
            try {
                $this->calendar()->withIslamicOffset($bad);
                self::fail('expected exception');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
                self::assertSame(3, $e->getContext()['max']);
            }
        }
        self::assertNotNull($this->calendar()->withIslamicOffset(-3));
    }

    /* ---------------- hijri month start ---------------- */

    public function test_month_start_moves_eid_al_fitr_in_an_estimated_calendar(): void
    {
        $c = $this->calendar()->withOfficialData(false)->withHijriMonthStart(1447, 10, '2026-03-21');

        self::assertSame(['ملی شدن صنعت نفت'], $c->getTitles(1404, 12, 29));
        self::assertSame([[self::NOWRUZ, HolidayOrigin::Fixed], [self::EID, HolidayOrigin::User]], self::pairs($c->statusOf(1405, 1, 1)));
        self::assertSame([[self::NOWRUZ_NEXT, HolidayOrigin::Fixed], [self::EID_SECOND, HolidayOrigin::User]], self::pairs($c->statusOf(1405, 1, 2)));
        // 25 Shawwal = 2026-04-14 = 1405/01/25
        self::assertSame([self::SADIQ], $c->getTitles(1405, 1, 25));
    }

    public function test_month_start_overrides_official_data_for_that_month_only(): void
    {
        // The real month began on 2026-03-22, a day after the official table says
        $c = $this->calendar()->withHijriMonthStart(1447, 10, '2026-03-22');

        self::assertSame([self::NOWRUZ], $c->getTitles(1405, 1, 1));
        self::assertSame([[self::NOWRUZ_NEXT, HolidayOrigin::Fixed], [self::EID, HolidayOrigin::User]], self::pairs($c->statusOf(1405, 1, 2)));
        self::assertSame([[self::NOWRUZ_NEXT, HolidayOrigin::Fixed], [self::EID_SECOND, HolidayOrigin::User]], self::pairs($c->statusOf(1405, 1, 3)));
        self::assertSame([], $c->getTitles(1405, 1, 25));
        self::assertSame([self::SADIQ], $c->getTitles(1405, 1, 26));
        // other months keep their official dates, and Eid al-Fitr 1448 is another Hijri year
        self::assertSame([self::EID], $c->getTitles(1405, 12, 19));
        self::assertSame(['اربعین حسینی'], $c->getTitles(1405, 5, 13));
    }

    public function test_month_start_equal_to_the_official_date_keeps_the_dates_but_marks_them_as_user_supplied(): void
    {
        $c = $this->calendar()->withHijriMonthStart(1447, 10, '2026-03-21');

        self::assertSame([[self::NOWRUZ, HolidayOrigin::Fixed], [self::EID, HolidayOrigin::User]], self::pairs($c->statusOf(1405, 1, 1)));
        self::assertSame($this->calendar()->allTitles(1405), $c->allTitles(1405));
    }

    public function test_month_start_moves_imam_reza_and_imam_askari_with_rabi_i(): void
    {
        // Official 1403: 30 Safar = 1403/06/14 (2024-09-04), 8 Rabi I = 1403/06/22. A real start of Rabi I on 2024-09-06 moves both.
        $c = $this->calendar()->withHijriMonthStart(1446, 3, '2024-09-06');

        self::assertSame([], $c->getTitles(1403, 6, 14));
        self::assertSame([[self::REZA, HolidayOrigin::User]], self::pairs($c->statusOf(1403, 6, 15)));
        self::assertSame([], $c->getTitles(1403, 6, 22));
        self::assertSame([[self::ASKARI, HolidayOrigin::User]], self::pairs($c->statusOf(1403, 6, 23)));
        // 17 Rabi I (Prophet's birth) follows the same start: 2024-09-22 = 1403/07/01
        self::assertSame(['میلاد پیامبر و امام صادق'], $c->getTitles(1403, 7, 1));
        self::assertSame([], $c->getTitles(1403, 6, 31));
    }

    public function test_safar_start_alone_derives_imam_reza_from_the_table_length_of_safar(): void
    {
        $c = $this->calendar()->withOfficialData(false)->withHijriMonthStart(1446, 2, '2024-08-06');

        // Arbaeen = 20 Safar = 2024-08-25 = 1403/06/04; the plain estimate is a day earlier
        self::assertSame([[ 'اربعین حسینی', HolidayOrigin::User]], self::pairs($c->statusOf(1403, 6, 4)));
        self::assertSame([], $c->getTitles(1403, 6, 3));
        // Safar 1446 has 30 days in the table: last day = 2024-08-06 + 29 = 2024-09-04 = 1403/06/14
        self::assertSame([[self::REZA, HolidayOrigin::User]], self::pairs($c->statusOf(1403, 6, 14)));
        self::assertSame([], $c->getTitles(1403, 6, 13));
    }

    public function test_month_start_accepts_date_objects_and_persian_digits(): void
    {
        $string = $this->calendar()->withOfficialData(false)->withHijriMonthStart(1447, 10, '2026-03-21');
        $object = $this->calendar()->withOfficialData(false)->withHijriMonthStart(1447, 10, new DateTimeImmutable('2026-03-21 18:30', new DateTimeZone('Asia/Tehran')));
        $persian = $this->calendar()->withOfficialData(false)->withHijriMonthStart(1447, 10, '۲۰۲۶-۰۳-۲۱');

        self::assertEquals($string, $object);
        self::assertEquals($string, $persian);
    }

    /**
     * @return array<string, array{int, int, string, ErrorCode}>
     */
    public static function invalidMonthStarts(): array
    {
        return [
            'four days late' => [1447, 10, '2026-03-24', ErrorCode::InvalidDate],
            'four days early' => [1447, 10, '2026-03-16', ErrorCode::InvalidDate],
            'a whole month away' => [1447, 10, '2026-04-20', ErrorCode::InvalidDate],
            'month 13' => [1447, 13, '2026-03-20', ErrorCode::InvalidDate],
            'month 0' => [1447, 0, '2026-03-20', ErrorCode::InvalidDate],
            'year 0' => [0, 1, '0622-07-16', ErrorCode::DateOutOfRange],
            'year past the range' => [9666, 1, '9999-12-31', ErrorCode::DateOutOfRange],
            'not a date' => [1447, 10, 'tomorrow', ErrorCode::InvalidDate],
            'impossible date' => [1447, 10, '2026-02-30', ErrorCode::InvalidDate],
            'jalali-looking string' => [1447, 10, '1405/01/01', ErrorCode::InvalidDate],
            'empty' => [1447, 10, '', ErrorCode::InvalidDate],
            'too long' => [1447, 10, '2026-03-20'.str_repeat(' ', 100), ErrorCode::InputTooLong],
        ];
    }

    #[DataProvider('invalidMonthStarts')]
    public function test_invalid_month_starts_throw_a_library_exception(int $year, int $month, string $date, ErrorCode $code): void
    {
        try {
            $this->calendar()->withHijriMonthStart($year, $month, $date);
            self::fail('expected exception');
        } catch (InvalidDateException $e) {
            self::assertSame($code, $e->getErrorCode());
        }
    }

    public function test_month_start_deviation_boundary_is_three_days(): void
    {
        // computed start of Shawwal 1447 is 2026-03-20
        self::assertNotNull($this->calendar()->withHijriMonthStart(1447, 10, '2026-03-17'));
        self::assertNotNull($this->calendar()->withHijriMonthStart(1447, 10, '2026-03-23'));

        try {
            $this->calendar()->withHijriMonthStart(1447, 10, '2026-03-24');
            self::fail('expected exception');
        } catch (InvalidDateException $e) {
            self::assertSame('2026-03-20', $e->getContext()['computed']);
            self::assertStringContainsString('4 days from the computed start 2026-03-20', $e->getMessage());
        }
    }

    public function test_neighbouring_month_starts_must_be_29_or_30_days_apart(): void
    {
        $c = $this->calendar()->withHijriMonthStart(1447, 10, '2026-03-22');

        self::assertNotNull($c->withHijriMonthStart(1447, 11, '2026-04-20'));  // 29 days
        self::assertNotNull($c->withHijriMonthStart(1447, 11, '2026-04-21'));  // 30 days

        try {
            $c->withHijriMonthStart(1447, 11, '2026-04-19'); // 28 days
            self::fail('expected exception');
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
            self::assertSame(28, $e->getContext()['gap']);
        }

        // the check works in both directions and across the year boundary
        $muharram = $this->calendar()->withHijriMonthStart(1447, 1, '2025-06-26');
        try {
            $muharram->withHijriMonthStart(1446, 12, '2025-05-29'); // 28 days before 1 Muharram
            self::fail('expected exception');
        } catch (InvalidDateException $e) {
            self::assertSame(28, $e->getContext()['gap']);
        }
        self::assertNotNull($muharram->withHijriMonthStart(1446, 12, '2025-05-28'));
    }

    /* ---------------- extra and removed holidays ---------------- */

    public function test_with_holiday_adds_a_title_after_the_existing_ones(): void
    {
        $c = $this->calendar()->withHoliday('1405/01/01', 'روز آزمون');

        self::assertSame([self::NOWRUZ, self::EID, 'روز آزمون'], $c->getTitles(1405, 1, 1));
        self::assertSame(HolidayOrigin::User, $c->statusOf(1405, 1, 1)[2]->origin);
        self::assertSame(self::NOWRUZ, $c->getTitle(1405, 1, 1));
        self::assertSame(self::NOWRUZ.' / '.self::EID.' / روز آزمون', $c->all(1405)['1405/01/01']);

        $plain = $this->calendar()->withHoliday(Jalali::create(1405, 2, 3), 'مناسبت');
        self::assertTrue($plain->isHoliday(1405, 2, 3));
        self::assertFalse($plain->isBusinessDay(Jalali::create(1405, 2, 3)));
        self::assertSame(['مناسبت'], $plain->getTitles(1405, 2, 3));
    }

    public function test_with_holiday_accepts_persian_digits_and_ignores_duplicates(): void
    {
        $c = $this->calendar()->withHoliday('۱۴۰۵/۰۲/۰۳', 'مناسبت')->withHoliday('1405/02/03', '  مناسبت  ')->withHoliday('1405-02-03', 'دوم');

        self::assertSame(['مناسبت', 'دوم'], $c->getTitles(1405, 2, 3));
    }

    public function test_with_holiday_that_duplicates_an_existing_title_is_not_listed_twice(): void
    {
        self::assertSame([self::NOWRUZ], $this->calendar()->withHoliday('1405/01/01', self::NOWRUZ)->withoutHoliday('1405/01/01', self::EID)->getTitles(1405, 1, 1));
    }

    public function test_without_holiday_removes_one_title_or_the_whole_day(): void
    {
        $one = $this->calendar()->withoutHoliday('1405/01/01', self::EID);
        self::assertSame([self::NOWRUZ], $one->getTitles(1405, 1, 1));

        $fixed = $this->calendar()->withoutHoliday(Jalali::create(1405, 1, 1), self::NOWRUZ);
        self::assertSame([self::EID], $fixed->getTitles(1405, 1, 1));

        $day = $this->calendar()->withoutHoliday('1405/01/01');
        self::assertSame([], $day->getTitles(1405, 1, 1));
        self::assertFalse($day->isHoliday(1405, 1, 1));
        self::assertNull($day->getTitle(1405, 1, 1));
        self::assertTrue($day->isBusinessDay(Jalali::create(1405, 1, 1))); // 2026-03-21 is a Saturday
        self::assertArrayNotHasKey('1405/01/01', $day->allTitles(1405));
        self::assertArrayHasKey('1405/01/02', $day->allTitles(1405));
        // the statutory fixed list is not edited
        self::assertArrayHasKey('1405/01/01', $day->allFixed(1405));
    }

    public function test_without_holiday_and_with_holiday_override_each_other_in_call_order(): void
    {
        $c = $this->calendar()->withHoliday('1405/01/01', 'الف')->withoutHoliday('1405/01/01');
        self::assertSame([], $c->getTitles(1405, 1, 1));

        $c = $c->withHoliday('1405/01/01', 'ب');
        self::assertSame(['ب'], $c->getTitles(1405, 1, 1));

        $c = $this->calendar()->withHoliday('1405/02/03', 'الف')->withHoliday('1405/02/03', 'ب')->withoutHoliday('1405/02/03', 'الف');
        self::assertSame(['ب'], $c->getTitles(1405, 2, 3));
        self::assertSame([], $c->withoutHoliday('1405/02/03', 'ب')->getTitles(1405, 2, 3));
        // removing a title twice or after a whole-day removal is harmless
        self::assertSame([], $c->withoutHoliday('1405/02/03')->withoutHoliday('1405/02/03', 'ب')->getTitles(1405, 2, 3));
        self::assertSame([self::NOWRUZ], $this->calendar()->withoutHoliday('1405/01/01', self::EID)->withoutHoliday('1405/01/01', self::EID)->getTitles(1405, 1, 1));
    }

    public function test_user_edits_win_over_official_and_estimated_data(): void
    {
        $c = $this->calendar()
            ->withoutHoliday('1404/01/11')           // official Eid al-Fitr
            ->withoutHoliday('1406/03/24')           // estimated Tasua
            ->withHoliday('1406/03/26', 'جایگزین');

        self::assertFalse($c->isHoliday(1404, 1, 11));
        self::assertFalse($c->isHoliday(1406, 3, 24));
        self::assertSame(['جایگزین'], $c->getTitles(1406, 3, 26));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTitles(): array
    {
        return [
            'empty' => [''],
            'blank' => ["  \t "],
            'newline' => ["a\nb"],
            'nul' => ["a\0b"],
            'invalid utf-8' => ["\xC3\x28"],
            'too long' => [str_repeat('ا', HolidayCalendar::MAX_TITLE_LENGTH + 1)],
        ];
    }

    #[DataProvider('invalidTitles')]
    public function test_invalid_titles_are_rejected(string $title): void
    {
        foreach ([
            fn () => $this->calendar()->withHoliday('1405/02/03', $title),
            fn () => $this->calendar()->withoutHoliday('1405/02/03', $title),
        ] as $call) {
            try {
                $call();
                self::fail('expected exception');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
            }
        }
    }

    public function test_the_longest_title_is_accepted(): void
    {
        $title = str_repeat('ا', HolidayCalendar::MAX_TITLE_LENGTH);

        self::assertSame([$title], $this->calendar()->withHoliday('1405/02/03', $title)->getTitles(1405, 2, 3));
    }

    /**
     * @return array<string, array{string, ErrorCode}>
     */
    public static function invalidDates(): array
    {
        return [
            'garbage' => ['garbage', ErrorCode::InvalidDate],
            'empty' => ['', ErrorCode::InvalidDate],
            'month 13' => ['1405/13/40', ErrorCode::InvalidDate],
            'nul byte' => ["\0", ErrorCode::InvalidDate],
            'too long' => [str_repeat('1', 65), ErrorCode::InputTooLong],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_date_strings_throw_only_library_exceptions(string $date, ErrorCode $code): void
    {
        foreach ([
            fn () => $this->calendar()->withHoliday($date, 'x'),
            fn () => $this->calendar()->withoutHoliday($date),
        ] as $call) {
            try {
                $call();
                self::fail('expected exception');
            } catch (InvalidDateException $e) {
                self::assertSame($code, $e->getErrorCode());
            }
        }
    }

    /* ---------------- reading API ---------------- */

    public function test_reading_api_matches_the_static_one_and_validates_input(): void
    {
        $c = $this->calendar();

        self::assertSame(IranHolidays::getTitles(1403, 1, 13), $c->getTitles(Jalali::create(1403, 1, 13)));
        self::assertTrue($c->isWeekend(Jalali::create(1403, 1, 10)));
        self::assertFalse($c->isBusinessDay(Jalali::create(1403, 1, 10)));
        self::assertFalse($c->isHoliday(1403, 12, 30));

        $this->expectException(InvalidDateException::class);
        $c->getTitles(1403);
    }

    public function test_calendar_range_errors_are_library_exceptions(): void
    {
        $c = $this->calendar()->withIslamicOffset(3)->withHijriMonthStart(1447, 10, '2026-03-20');

        foreach ([
            fn () => $c->all(99999),
            fn () => $c->allTitles(Jalali::MIN_YEAR - 1),
            fn () => $c->allFixed(Jalali::MAX_YEAR + 1),
            fn () => $c->getTitles(99999, 1, 1),
            fn () => $c->isHoliday(1404, 12, 30),
        ] as $call) {
            try {
                $call();
                self::fail('expected exception');
            } catch (InvalidDateException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_extreme_supported_years_work_with_offsets_and_month_starts(): void
    {
        $c = $this->calendar()->withIslamicOffset(-3)->withHijriMonthStart(1447, 10, '2026-03-20')->withHoliday(Jalali::create(Jalali::MAX_YEAR, 1, 2), 'x');

        self::assertCount(10, $c->allTitles(Jalali::MIN_YEAR));
        self::assertSame([self::NOWRUZ_NEXT, 'x'], $c->getTitles(Jalali::MAX_YEAR, 1, 2));
        self::assertCount(10, $c->withIslamicOffset(3)->allTitles(Jalali::MAX_YEAR));
    }

    public function test_business_day_logic_follows_the_calendar(): void
    {
        $sunday = Jalali::create(1404, 1, 10); // 2025-03-30

        self::assertTrue($this->calendar()->isBusinessDay($sunday));
        self::assertFalse($this->calendar()->withOfficialData(false)->isBusinessDay($sunday));
        // 1405/01/04 is Tuesday; 05 Wednesday is the next business day
        self::assertSame('1405/01/05', $this->calendar()->nextBusinessDay(Jalali::create(1404, 12, 28))->format('Y/m/d'));
    }

    public function test_next_business_day_at_the_end_of_the_supported_range_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        $this->calendar()->nextBusinessDay(Jalali::create(9377, 12, 30));
    }

    /* ---------------- fromArray ---------------- */

    public function test_from_array_casts_an_int_like_string_offset(): void
    {
        foreach (['1' => 1, '-2' => -2, '+3' => 3, '0' => 0] as $text => $number) {
            self::assertEquals(
                HolidayCalendar::fromArray(['islamic_offset' => $number]),
                HolidayCalendar::fromArray(['islamic_offset' => (string) $text]),
                "offset {$text}",
            );
        }
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonIntegerOffsets(): array
    {
        return ['float' => [1.0], 'decimal string' => ['1.5'], 'text' => ['one'], 'empty' => [''], 'spaces' => [' 1'], 'exponent' => ['1e0'], 'array' => [[]]];
    }

    #[DataProvider('nonIntegerOffsets')]
    public function test_from_array_still_rejects_other_offset_types(mixed $offset): void
    {
        $this->expectException(InvalidDateException::class);
        HolidayCalendar::fromArray(['islamic_offset' => $offset]);
    }

    public function test_from_array_rejects_an_out_of_range_string_offset(): void
    {
        $this->expectException(InvalidDateException::class);
        HolidayCalendar::fromArray(['islamic_offset' => '4']);
    }

    public function test_from_array_defaults_equal_the_default_calendar(): void
    {
        self::assertEquals($this->calendar(), HolidayCalendar::fromArray([]));
        self::assertEquals($this->calendar(), HolidayCalendar::fromArray([
            'islamic_offset' => 0,
            'hijri_month_starts' => [],
            'extra' => [],
            'removed' => [],
            'use_official_data' => true,
        ]));
    }

    public function test_from_array_applies_every_option(): void
    {
        $c = HolidayCalendar::fromArray([
            'islamic_offset' => 1,
            'use_official_data' => false,
            'hijri_month_starts' => ['1447-10' => '2026-03-21', '1447/11' => '2026-04-19'],
            'extra' => ['1405/02/03' => 'یک', '1405/02/04' => ['دو', 'سه']],
            'removed' => ['1405/01/02', '1405/01/03' => 'عید نوروز', '1405/01/04' => ['عید نوروز', 'x'], '1405/01/12' => null],
        ]);

        self::assertSame(HolidaySource::Estimated, $c->sourceOf(1405));
        self::assertSame([self::NOWRUZ, self::EID], $c->getTitles(1405, 1, 1));
        self::assertSame([], $c->getTitles(1405, 1, 2));
        self::assertSame([], $c->getTitles(1405, 1, 3));
        self::assertSame([], $c->getTitles(1405, 1, 4));
        self::assertSame([], $c->getTitles(1405, 1, 12));
        self::assertSame(['یک'], $c->getTitles(1405, 2, 3));
        self::assertSame(['دو', 'سه'], $c->getTitles(1405, 2, 4));
        self::assertSame(['تاسوعای حسینی'], $c->getTitles(1406, 3, 25));
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function invalidConfigs(): array
    {
        return [
            'unknown key' => [['islamic_ofset' => 1]],
            'offset as decimal string' => [['islamic_offset' => '1.5']],
            'offset too large' => [['islamic_offset' => 4]],
            'official flag as int' => [['use_official_data' => 1]],
            'month starts not an array' => [['hijri_month_starts' => 'x']],
            'month start key malformed' => [['hijri_month_starts' => ['abc' => '2026-03-20']]],
            'month start value not a date' => [['hijri_month_starts' => ['1447-10' => 5]]],
            'month start too far' => [['hijri_month_starts' => ['1447-10' => '2026-05-01']]],
            'extra title not a string' => [['extra' => ['1405/02/03' => 5]]],
            'extra with a list index' => [['extra' => [0 => 'x']]],
            'extra bad date' => [['extra' => ['nope' => 'x']]],
            'removed list item not a string' => [['removed' => [5]]],
            'removed title not a string' => [['removed' => ['1405/02/03' => [5]]]],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    #[DataProvider('invalidConfigs')]
    public function test_from_array_rejects_bad_config_with_a_library_exception(array $config): void
    {
        $this->expectException(InvalidDateException::class);

        HolidayCalendar::fromArray($config);
    }
}
