<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;

/**
 * addMonths()/addYears() clamp the day at a short month end, and diff*() counts
 * whole units from the real days, so the two are not inverses there (the same
 * as Carbon). The behaviour is documented on the methods and in the guide.
 */
final class AddDiffNotInverseTest extends TestCase
{
    public function test_a_clamped_month_end_is_not_undone_by_diff_in_months(): void
    {
        $utc = new DateTimeZone('UTC');
        $start = Jalali::create(1403, 6, 31, 0, 0, 0, $utc);
        $end = $start->addMonths(1);

        self::assertSame('1403/07/30', $end->toDateString());
        self::assertSame(0, $start->diffInMonths($end));
        self::assertSame(1, Jalali::create(1403, 6, 30, 0, 0, 0, $utc)->diffInMonths(Jalali::create(1403, 7, 30, 0, 0, 0, $utc)));
    }

    public function test_a_clamped_leap_day_is_not_undone_by_diff_in_years(): void
    {
        $utc = new DateTimeZone('UTC');
        $start = Jalali::create(1403, 12, 30, 0, 0, 0, $utc);
        $end = $start->addYears(1);

        self::assertSame('1404/12/29', $end->toDateString());
        self::assertSame(0, $start->diffInYears($end));
        self::assertSame(1, Jalali::create(1403, 12, 29, 0, 0, 0, $utc)->diffInYears($end));
    }
}
