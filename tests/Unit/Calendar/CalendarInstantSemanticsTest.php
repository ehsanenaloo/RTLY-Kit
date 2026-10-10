<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Contracts\CalendarDate;

/**
 * Instant-level behaviour shared by the three calendars: make() with an
 * instance of the same class, end-of-period precision, microsecond-aware
 * month/year differences, differences near the year-range edge, and DST
 * folds in addMonths()/addYears().
 */
final class CalendarInstantSemanticsTest extends TestCase
{
    /** @return iterable<string, array{class-string<CalendarDate>}> */
    public static function calendars(): iterable
    {
        yield 'Jalali' => [Jalali::class];
        yield 'Hijri' => [Hijri::class];
        yield 'Hebrew' => [Hebrew::class];
    }

    /** @param class-string<CalendarDate> $class */
    private static function at(string $class, string $time, string $zone = 'UTC'): CalendarDate
    {
        return $class::make(new DateTimeImmutable($time, new DateTimeZone($zone)));
    }

    /* ---------------- make() with an instance of the same class ---------------- */

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_make_with_own_instance_and_no_zone_returns_it(string $class): void
    {
        $date = self::at($class, '2025-03-01 10:00:00');

        self::assertSame($date, $class::make($date));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_make_with_own_instance_converts_the_zone(string $class): void
    {
        $date = self::at($class, '2025-03-01 22:30:00');
        $tehran = $class::make($date, new DateTimeZone('Asia/Tehran'));

        self::assertSame($date->getTimestamp(), $tehran->getTimestamp());
        self::assertSame('Asia/Tehran', $tehran->getTimezone()->getName());
        self::assertSame(2, $tehran->getHour());
        self::assertSame(0, $tehran->getMinute());
    }

    public function test_hijri_make_with_a_different_variant_converts_the_variant(): void
    {
        $uq = self::at(Hijri::class, '2025-03-01 10:00:00');
        self::assertInstanceOf(Hijri::class, $uq);
        self::assertSame(HijriVariant::UmmAlQura, $uq->getVariant());

        $tabular = Hijri::make($uq, null, HijriVariant::Tabular);
        self::assertSame(HijriVariant::Tabular, $tabular->getVariant());
        self::assertSame($uq->getTimestamp(), $tabular->getTimestamp());
        self::assertSame(Hijri::make(new DateTimeImmutable('2025-03-01 10:00:00 UTC'), null, HijriVariant::Tabular)->toDateString(), $tabular->toDateString());

        // A null variant (the default) keeps the instance's own variant.
        self::assertSame($tabular, Hijri::make($tabular));
        $zoned = Hijri::make($tabular, new DateTimeZone('Asia/Tehran'));
        self::assertSame(HijriVariant::Tabular, $zoned->getVariant());
        self::assertSame('Asia/Tehran', $zoned->getTimezone()->getName());

        // The same variant requested explicitly is not a conversion.
        self::assertSame($tabular, Hijri::make($tabular, null, HijriVariant::Tabular));
        self::assertSame($uq, Hijri::make($uq, null, HijriVariant::UmmAlQura));
    }

    /* ---------------- endOfDay / endOfMonth / endOfYear ---------------- */

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_end_of_period_is_the_last_microsecond(string $class): void
    {
        $date = self::at($class, '2025-03-01 10:15:20.123456');

        foreach (['endOfDay', 'endOfMonth', 'endOfYear'] as $method) {
            $end = $date->{$method}();
            self::assertSame('23:59:59', $end->format('H:i:s'), $method);
            self::assertSame('999999', $end->format('u'), $method);
            self::assertTrue($date->between($date->startOfDay(), $date->endOfDay()), $method);
        }

        // Any time of the day sits between startOfDay() and endOfDay(), even the last microsecond.
        $last = $date->endOfDay();
        self::assertTrue($last->between($date->startOfDay(), $date->endOfDay()));
        self::assertTrue($last->addSeconds(1)->startOfDay()->gt($last));
        self::assertFalse($last->addSeconds(1)->between($date->startOfDay(), $date->endOfDay()));
    }

    /* ---------------- diffInMonths / diffInYears with microseconds ---------------- */

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_diff_counts_the_microsecond_part(string $class): void
    {
        $from = self::at($class, '2025-03-01 10:00:00.500000');
        $anniversary = $from->addMonths(1);
        self::assertSame('500000', $anniversary->format('u'));

        self::assertSame(1, $from->diffInMonths($anniversary));
        self::assertSame(1, $anniversary->diffInMonths($from));

        $justBefore = $class::make($anniversary->toGregorian()->modify('-1 usec'));
        self::assertSame(0, $from->diffInMonths($justBefore));
        self::assertSame(0, $from->diffInMonths($justBefore, false));
        self::assertSame(0, $justBefore->diffInMonths($from));

        $yearLater = $from->addYears(1);
        self::assertSame(1, $from->diffInYears($yearLater));
        $yearJustBefore = $class::make($yearLater->toGregorian()->modify('-1 usec'));
        self::assertSame(0, $from->diffInYears($yearJustBefore));
        self::assertSame(0, $yearJustBefore->diffInYears($from));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_diff_sign_follows_this_minus_other(string $class): void
    {
        $early = self::at($class, '2025-03-01 10:00:00');
        $late = $early->addMonths(3);

        self::assertSame(3, $early->diffInMonths($late));
        self::assertSame(-3, $early->diffInMonths($late, false));
        self::assertSame(3, $late->diffInMonths($early, false));
        self::assertSame(0, $early->diffInMonths($early, false));
    }

    /* ---------------- diff near the year-range edge ---------------- */

    /**
     * @return iterable<string, array{class-string<CalendarDate>, array<int, int>, array<int, int>}>
     */
    public static function edges(): iterable
    {
        yield 'Jalali' => [Jalali::class, [Jalali::MIN_YEAR, 1, 1], [Jalali::MAX_YEAR, 12, 30]];
        yield 'Hijri' => [Hijri::class, [Hijri::MIN_YEAR, 1, 1], [Hijri::MAX_YEAR, 12, Hijri::daysInMonth(Hijri::MAX_YEAR, 12)]];
        yield 'Hebrew' => [
            Hebrew::class,
            [Hebrew::MIN_YEAR, 1, 1],
            [Hebrew::MAX_YEAR, Hebrew::monthsInYear(Hebrew::MAX_YEAR), Hebrew::daysInMonth(Hebrew::MAX_YEAR, Hebrew::monthsInYear(Hebrew::MAX_YEAR))],
        ];
    }

    /**
     * @param class-string<CalendarDate> $class
     * @param array<int, int> $first
     */
    #[DataProvider('edges')]
    public function test_diff_does_not_throw_for_valid_instants_that_fall_outside_the_range_in_the_other_zone(string $class, array $first, array $last): void
    {
        $utc = new DateTimeZone('UTC');
        // The first day, early in a zone ahead of UTC: in UTC it is already before the supported range.
        $ahead = $class::create($first[0], $first[1], $first[2], 5, 0, 0, new DateTimeZone('Asia/Tokyo'));
        $start = $class::create($first[0], $first[1], $first[2], 0, 0, 0, $utc);
        self::assertTrue($ahead->lt($start));

        foreach ([$start->diffInMonths($ahead), $ahead->diffInMonths($start), $start->diffInYears($ahead), $ahead->diffInYears($start)] as $value) {
            self::assertSame(0, $value);
        }
        self::assertSame(0, $start->diffInMonths($ahead, false));
        self::assertSame(0, $ahead->diffInYears($start, false));

        // The last day, late in a zone behind UTC: in UTC it is already after the supported range.
        $behind = $class::create($last[0], $last[1], $last[2], 23, 0, 0, new DateTimeZone('America/Los_Angeles'));
        $end = $class::create($last[0], $last[1], $last[2], 0, 0, 0, $utc);
        self::assertTrue($behind->gt($end));

        foreach ([$end->diffInMonths($behind), $behind->diffInMonths($end), $end->diffInYears($behind), $behind->diffInYears($end)] as $value) {
            self::assertSame(0, $value);
        }
        self::assertSame(0, $end->diffInMonths($behind, false));
        self::assertSame(0, $behind->diffInYears($end, false));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_diff_with_a_gregorian_instant_outside_the_range_still_throws_one_library_exception(string $class): void
    {
        $this->expectException(\RtlyKit\Exceptions\InvalidDateException::class);
        self::at($class, '2025-03-01 10:00:00')->diffInMonths(new DateTimeImmutable('0000-06-01 00:00:00 UTC'));
    }

    /* ---------------- DST folds ---------------- */

    private static function newYork(string $utcTime): DateTimeImmutable
    {
        return (new DateTimeImmutable($utcTime, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/New_York'));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_adding_zero_months_or_years_is_the_identity_in_a_dst_overlap(string $class): void
    {
        // 2024-11-03 01:30 EST (the second 01:30 of that morning).
        $second = $class::make(self::newYork('2024-11-03 06:30:00'));
        self::assertSame(-18000, $second->toGregorian()->getOffset());

        self::assertSame($second, $second->addMonths(0));
        self::assertSame($second, $second->addYears(0));
        self::assertSame($second, $second->subMonths(0));
        self::assertSame($second, $second->subYears(0));

        // The first 01:30 (EDT) keeps its offset too.
        $first = $class::make(self::newYork('2024-11-03 05:30:00'));
        self::assertSame(-14400, $first->toGregorian()->getOffset());
        self::assertSame($first->getTimestamp(), $first->addMonths(0)->getTimestamp());
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_months_landing_in_a_dst_overlap_keep_the_original_offset(string $class): void
    {
        // 2020-11-01 01:30 EST, the repeated hour of that morning.
        $start = $class::make(self::newYork('2020-11-01 06:30:00'));
        self::assertSame(-18000, $start->toGregorian()->getOffset());
        $ny = new DateTimeZone('America/New_York');

        $ambiguousHits = 0;
        for ($months = -1200; $months <= 1200; $months++) {
            $result = $start->addMonths($months);
            $g = $result->toGregorian();
            if ($g->format('H:i:s') !== '01:30:00') {
                continue;
            }
            // Is this wall-clock time one of two instants in that zone?
            $ts = $g->getTimestamp();
            $twin = [$ts - 3600, $ts + 3600];
            $ambiguous = false;
            foreach ($twin as $other) {
                $o = (new DateTimeImmutable('@'.$other))->setTimezone($ny);
                if ($o->format('Y-m-d H:i:s') === $g->format('Y-m-d H:i:s')) {
                    $ambiguous = true;
                }
            }
            if ($ambiguous) {
                $ambiguousHits++;
                self::assertSame(-18000, $g->getOffset(), "{$months} months: {$g->format('c')}");
            }
        }

        self::assertGreaterThanOrEqual(1, $ambiguousHits);
    }

    public function test_jalali_years_landing_on_the_next_overlap_keep_est(): void
    {
        $start = Jalali::make(self::newYork('2020-11-01 06:30:00'));
        self::assertSame('1399/08/11', $start->toDateString());

        $later = $start->addYears(5);

        self::assertSame('1404/08/11', $later->toDateString());
        self::assertSame('2025-11-02T01:30:00-05:00', $later->toGregorian()->format('c'));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_a_shift_out_of_the_overlap_is_not_adjusted(string $class): void
    {
        $start = $class::make(self::newYork('2024-11-03 06:30:00')); // 01:30 EST
        $summer = $start->subMonths(4);

        self::assertSame('01:30:00', $summer->toGregorian()->format('H:i:s'));
        self::assertSame(-14400, $summer->toGregorian()->getOffset());
    }

    public function test_tehran_overlap_when_the_clocks_went_back(): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');
        // 2021-09-21 23:30 happened twice: first at +04:30, then at +03:30.
        $second = Jalali::make(new DateTimeImmutable('2021-09-21 20:00:00 UTC'), $tehran);
        self::assertSame('1400/06/30 23:30:00', $second->toDateTimeString());
        self::assertSame(12600, $second->toGregorian()->getOffset());
        self::assertSame($second, $second->addMonths(0));
        self::assertSame($second, $second->addYears(0));

        $first = Jalali::make(new DateTimeImmutable('2021-09-21 19:00:00 UTC'), $tehran);
        self::assertSame(16200, $first->toGregorian()->getOffset());
        self::assertSame($first->getTimestamp(), $first->addMonths(0)->getTimestamp());

        // Shifting to a date that is not in an overlap uses that date's own offset.
        $previous = $second->subMonths(1);
        self::assertSame('1400/05/30 23:30:00', $previous->toDateTimeString());
        self::assertSame(16200, $previous->toGregorian()->getOffset());
    }
}
