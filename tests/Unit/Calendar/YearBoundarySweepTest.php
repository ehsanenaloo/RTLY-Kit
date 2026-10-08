<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Calendar\Jalali;

/**
 * Walks the day before every year start: the converter's first estimate of the
 * year is often one too high there, so this exercises the correction loops.
 */
final class YearBoundarySweepTest extends TestCase
{
    public function test_day_before_each_jalali_new_year_is_the_last_day_of_esfand(): void
    {
        $utc = new DateTimeZone('UTC');

        for ($year = Jalali::MIN_YEAR + 1; $year <= Jalali::MAX_YEAR; $year++) {
            $eve = Jalali::create($year, 1, 1, 12, 0, 0, $utc)->subDays(1);

            $this->assertSame($year - 1, $eve->getYear(), "year of the day before {$year}/1/1");
            $this->assertSame(12, $eve->getMonth());
            $this->assertSame(Jalali::daysInMonth($year - 1, 12), $eve->getDay());
        }
    }

    public function test_day_before_each_umm_al_qura_new_year_is_the_last_day_of_dhul_hijjah(): void
    {
        $utc = new DateTimeZone('UTC');

        for ($year = 1301; $year <= 1500; $year++) {
            $eve = Hijri::create($year, 1, 1, 12, 0, 0, $utc, HijriVariant::UmmAlQura)->subDays(1);
            [$y, $m, $d] = Hijri::gregorianToHijri(
                (int) $eve->toGregorian()->format('Y'),
                (int) $eve->toGregorian()->format('n'),
                (int) $eve->toGregorian()->format('j'),
                HijriVariant::UmmAlQura,
            );

            $this->assertSame([$year - 1, 12], [$y, $m], "day before {$year}/1/1");
            $this->assertSame(Hijri::daysInMonth($year - 1, 12, HijriVariant::UmmAlQura), $d);
        }
    }
}
