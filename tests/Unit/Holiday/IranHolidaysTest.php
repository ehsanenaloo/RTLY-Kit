<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Holiday;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Holiday\IranHolidays;

/**
 * Known-answer tests for the Iranian holiday tables and business-day logic.
 * 2024-03-20 (Wednesday) = 1403/01/01 Jalali = 1445/09/10 Hijri = 10 Adar II 5784.
 */
final class IranHolidaysTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    public function test_iran_holidays_bad_year_throws_invalid_date(): void
    {
        $this->expectException(InvalidDateException::class);
        IranHolidays::all(1000000);
    }

    /* ---------------- 5. holidays outside the table ---------------- */

    public function test_islamic_holidays_only_inside_the_umm_al_qura_table(): void
    {
        // 1 Shawwal 1446 (Eid al-Fitr) = 2025-03-30 -> Jalali 1404/01/10, inside the table
        $this->assertContains('عید فطر', IranHolidays::getTitles(Jalali::make('2025-03-30', $this->utc)));

        // Far outside AH 1300-1500: tabular guesses are NOT reported as holidays
        $future = Jalali::create(1500, 1, 1, 0, 0, 0, $this->utc); // ~2121 CE, AH ~1543
        $this->assertFalse(Hijri::hasUmmAlQuraData(Hijri::make($future->toGregorian())->getYear()));
        $all = IranHolidays::all(1500);
        $this->assertSame(array_keys(IranHolidays::allFixed(1500)), array_keys($all));
        $this->assertSame(['جشن نوروز'], IranHolidays::getTitles(1500, 1, 1));
        $this->assertFalse(IranHolidays::isHoliday(1500, 6, 6));

        // Inside the table the Islamic holidays are still present
        $this->assertGreaterThan(count(IranHolidays::allFixed(1404)), count(IranHolidays::all(1404)));
    }

    /* ---------------- IranHolidays ---------------- */

    public function test_next_business_day_skips_holidays_and_weekends(): void
    {
        // 1403/01/11 is Saturday; 12 and 13 Farvardin are national holidays.
        $this->assertSame('1403/01/14', IranHolidays::nextBusinessDay(Jalali::create(1403, 1, 11))->format('Y/m/d'));
        // Thursday 1403/01/16 (2024-04-04): next day is Friday (weekend) -> Saturday
        $thu = Jalali::make('2024-04-04', $this->utc());
        $this->assertSame('1403/01/16', $thu->format('Y/m/d'));
        $this->assertSame('1403/01/18', IranHolidays::nextBusinessDay($thu)->format('Y/m/d'));
    }

    public function test_fixed_holidays_known_dates(): void
    {
        $this->assertSame('جشن نوروز', IranHolidays::getTitle(1403, 1, 1));
        $this->assertSame('روز طبیعت', IranHolidays::getTitle(1403, 1, 13));
        $this->assertSame('رحلت امام خمینی', IranHolidays::getTitle(1403, 3, 14));
        $this->assertSame('قیام ۱۵ خرداد', IranHolidays::getTitle(1403, 3, 15));
        $this->assertSame('پیروزی انقلاب اسلامی', IranHolidays::getTitle(1404, 11, 22));
        $this->assertSame('ملی شدن صنعت نفت', IranHolidays::getTitle(1404, 12, 29));
        // Leap-year extra day is not a holiday
        $this->assertFalse(IranHolidays::isHoliday(1403, 12, 30));
    }

    public function test_get_title_accepts_jalali_and_ints_identically(): void
    {
        $this->assertSame(
            IranHolidays::getTitle(Jalali::create(1403, 1, 2)),
            IranHolidays::getTitle(1403, 1, 2),
        );
    }

    public function test_get_title_with_missing_month_or_day_throws_clear_exception(): void
    {
        $this->expectException(InvalidDateException::class);
        IranHolidays::getTitle(1403);
    }

    public function test_invalid_date_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        IranHolidays::getTitle(1404, 12, 30);
    }

    public function test_get_titles_for_plain_day_is_empty(): void
    {
        $this->assertSame([], IranHolidays::getTitles(1403, 2, 2));
        $this->assertNull(IranHolidays::getTitle(1403, 2, 2));
    }

    public function test_every_title_is_a_holiday_and_consistent(): void
    {
        $count = 0;
        foreach (IranHolidays::allTitles(1403) as $key => $titles) {
            [$y, $m, $d] = array_map('intval', explode('/', $key));
            $this->assertSame($titles, IranHolidays::getTitles($y, $m, $d));
            $this->assertSame($titles[0], IranHolidays::getTitle($y, $m, $d));
            $this->assertTrue(IranHolidays::isHoliday($y, $m, $d));
            $count++;
        }

        $this->assertGreaterThan(20, $count);
        $this->assertCount($count, IranHolidays::all(1403));
    }

    public function test_fixed_holidays_do_not_hide_islamic_ones(): void
    {
        $found = null;
        for ($year = 1380; $year < 1460 && $found === null; $year++) {
            foreach (IranHolidays::allTitles($year) as $key => $titles) {
                if (count($titles) > 1) {
                    $found = [$key, $titles];
                    break;
                }
            }
        }

        $this->assertNotNull($found, 'tabular calendar must produce at least one fixed/Islamic overlap in 80 years');
        [$key, $titles] = $found;
        [$y, $m, $d] = array_map('intval', explode('/', $key));

        // BC: getTitle returns the fixed holiday (first), all() joins every title
        $this->assertSame($titles[0], IranHolidays::getTitle($y, $m, $d));
        $this->assertSame(implode(' / ', $titles), IranHolidays::all($y)[$key]);
        $this->assertGreaterThanOrEqual(2, count(array_unique($titles)));
    }

    public function test_imam_reza_is_on_last_day_of_safar(): void
    {
        $seen = 0;
        foreach (IranHolidays::allTitles(1403) + IranHolidays::allTitles(1404) as $key => $titles) {
            if (! in_array('شهادت امام رضا', $titles, true)) {
                continue;
            }
            $seen++;
            [$y, $m, $d] = array_map('intval', explode('/', $key));
            $hijri = Hijri::make(Jalali::create($y, $m, $d)->toGregorian());
            $this->assertSame(2, $hijri->getMonth());
            $this->assertSame(Hijri::daysInMonth($hijri->getYear(), 2), $hijri->getDay());
            // next day starts Rabi' al-awwal
            $next = Hijri::make(Jalali::create($y, $m, $d)->addDays(1)->toGregorian());
            $this->assertSame([3, 1], [$next->getMonth(), $next->getDay()]);
        }

        $this->assertGreaterThanOrEqual(1, $seen);
    }

    public function test_imam_sadiq_on_25_shawwal(): void
    {
        $seen = 0;
        foreach (IranHolidays::allTitles(1403) + IranHolidays::allTitles(1404) as $key => $titles) {
            if (! in_array('شهادت امام جعفر صادق', $titles, true)) {
                continue;
            }
            $seen++;
            [$y, $m, $d] = array_map('intval', explode('/', $key));
            $hijri = Hijri::make(Jalali::create($y, $m, $d)->toGregorian());
            $this->assertSame([10, 25], [$hijri->getMonth(), $hijri->getDay()]);
        }

        $this->assertGreaterThanOrEqual(1, $seen);
    }

    public function test_all_fixed_year_keys(): void
    {
        $fixed = IranHolidays::allFixed(1403);
        $this->assertSame('جشن نوروز', $fixed['1403/01/01']);
        $this->assertArrayNotHasKey('1403/12/30', $fixed);
    }

    public function test_business_days(): void
    {
        $this->assertFalse(IranHolidays::isBusinessDay(Jalali::create(1403, 1, 1)));
        // Friday 1403/01/10 (2024-03-29)
        $friday = Jalali::create(1403, 1, 10);
        $this->assertTrue(IranHolidays::isWeekend($friday));
        $this->assertFalse(IranHolidays::isBusinessDay($friday));
        $this->assertTrue(IranHolidays::isBusinessDay(Jalali::create(1403, 2, 2)));
    }

    public function test_iran_holidays(): void
    {
        $norooz = Jalali::create(1403, 1, 1);
        $this->assertTrue(IranHolidays::isHoliday($norooz));
        $this->assertSame('جشن نوروز', IranHolidays::getTitle($norooz));

        $normal = Jalali::create(1403, 2, 10);
        $this->assertFalse(IranHolidays::isHoliday($normal));

        $this->assertNotEmpty(IranHolidays::allFixed(1403));
    }

    public function test_business_day(): void
    {
        // A known non-holiday weekday - we just check the method runs
        $date = Jalali::create(1403, 2, 10);
        $next = IranHolidays::nextBusinessDay($date);
        $this->assertTrue($next->gt($date));
        $this->assertTrue(IranHolidays::isBusinessDay($next));
    }

    private function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
