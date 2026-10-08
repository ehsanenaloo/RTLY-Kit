<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Official Iranian public holidays.
 *
 * - Fixed Solar (Jalali) holidays
 * - Variable Islamic holidays derived from Hijri calendar
 *
 * Accuracy: Islamic dates follow Umm al-Qura (Hijri default); Iran officially uses moon sighting, so these can differ by 1-2 days.
 * Iran's official calendar is moon-sighting based, so Islamic holidays here can
 * be off by 1-2 days relative to the official announcement. Treat them as
 * estimates; fixed Jalali holidays are exact.
 *
 * Range: Islamic holidays are only reported for dates inside the embedded
 * Umm al-Qura table (Hijri AH 1300-1500, 1882-11-12 .. 2077-11-16 CE; see
 * {@see Hijri::hasUmmAlQuraData()}). Outside it (where Hijri would fall back to
 * tabular extrapolation, which is not reliable for holidays) isHoliday(),
 * getTitles(), all() and allTitles() return the fixed Jalali holidays only.
 * Year arguments must lie within {@see Jalali::MIN_YEAR}..{@see Jalali::MAX_YEAR};
 * otherwise InvalidDateException is thrown.
 */
final class IranHolidays
{
    /**
     * Fixed Jalali holidays: month => [day => title]
     * @var array<int, array<int, string>>
     */
    private static array $fixed = [
        1  => [
            1  => 'جشن نوروز',
            2  => 'عید نوروز',
            3  => 'عید نوروز',
            4  => 'عید نوروز',
            12 => 'روز جمهوری اسلامی',
            13 => 'روز طبیعت',
        ],
        3  => [
            14 => 'رحلت امام خمینی',
            15 => 'قیام ۱۵ خرداد',
        ],
        11 => [
            22 => 'پیروزی انقلاب اسلامی',
        ],
        12 => [
            29 => 'ملی شدن صنعت نفت',
        ],
    ];

    /**
     * Islamic (Hijri) holidays: month => [day => title]
     * @var array<int, array<int, string>>
     */
    private static array $islamic = [
        1  => [
            9  => 'تاسوعای حسینی',
            10 => 'عاشورای حسینی',
        ],
        2  => [
            20 => 'اربعین حسینی',
            28 => 'رحلت پیامبر و شهادت امام حسن',
            // Imam Reza martyrdom (last day of Safar) is handled in islamicTitles()
        ],
        3  => [
            8  => 'شهادت امام حسن عسکری',
            17 => 'میلاد پیامبر و امام صادق',
        ],
        6  => [
            3  => 'شهادت حضرت فاطمه',
        ],
        7  => [
            13 => 'ولادت امام علی',
            27 => 'مبعث پیامبر',
        ],
        8  => [
            15 => 'ولادت امام زمان',
        ],
        9  => [
            21 => 'شهادت امام علی',
        ],
        10 => [
            1  => 'عید فطر',
            2  => 'تعطیل عید فطر',
            25 => 'شهادت امام جعفر صادق',
        ],
        12 => [
            10 => 'عید قربان',
            18 => 'عید غدیر خم',
        ],
    ];

    public static function isHoliday(Jalali|int $year, ?int $month = null, ?int $day = null): bool
    {
        return self::getTitle($year, $month, $day) !== null;
    }

    public static function getTitle(Jalali|int $year, ?int $month = null, ?int $day = null): ?string
    {
        return self::getTitles($year, $month, $day)[0] ?? null;
    }

    /**
     * All holiday titles for a day: fixed Jalali ones first, then Islamic.
     *
     * @return list<string>
     */
    public static function getTitles(Jalali|int $year, ?int $month = null, ?int $day = null): array
    {
        if ($year instanceof Jalali) {
            $date = $year;
        } else {
            if ($month === null || $day === null) {
                throw new InvalidDateException('Month and day are required when the year is given as int.');
            }
            $date = Jalali::create($year, $month, $day);
        }

        $titles = [];

        $fixed = self::$fixed[$date->getMonth()][$date->getDay()] ?? null;
        if ($fixed !== null) {
            $titles[] = $fixed;
        }

        $hijri = Hijri::make($date->toGregorian());
        if (! Hijri::hasUmmAlQuraData($hijri->getYear())) {
            return $titles; // outside the Umm al-Qura table: fixed Jalali holidays only
        }

        return [...$titles, ...self::islamicTitles($hijri)];
    }

    /**
     * @return list<string>
     */
    private static function islamicTitles(Hijri $hijri): array
    {
        $hm = $hijri->getMonth();
        $hd = $hijri->getDay();

        $titles = [];
        if (isset(self::$islamic[$hm][$hd])) {
            $titles[] = self::$islamic[$hm][$hd];
        }

        // Imam Reza martyrdom: last day of Safar (29 or 30 days).
        if ($hm === 2 && $hd === Hijri::daysInMonth($hijri->getYear(), 2)) {
            $titles[] = 'شهادت امام رضا';
        }

        return $titles;
    }

    /**
     * All fixed holidays for a Jalali year.
     * @return array<string, string>
     */
    public static function allFixed(int $year): array
    {
        if ($year < Jalali::MIN_YEAR || $year > Jalali::MAX_YEAR) {
            throw new InvalidDateException("Jalali year out of the supported range: {$year}");
        }

        $result = [];
        foreach (self::$fixed as $m => $days) {
            foreach ($days as $d => $title) {
                $result[sprintf('%04d/%02d/%02d', $year, $m, $d)] = $title;
            }
        }

        return $result;
    }

    /**
     * All holidays (fixed + islamic) for a Jalali year; titles falling on the
     * same day are joined with " / ". See allTitles() for the structured form.
     *
     * @return array<string, string>
     */
    public static function all(int $year): array
    {
        return array_map(static fn (array $t): string => implode(' / ', $t), self::allTitles($year));
    }

    /**
     * All holidays for a Jalali year keyed by Y/m/d, each a list of titles.
     *
     * @return array<string, list<string>>
     */
    public static function allTitles(int $year): array
    {
        $result = [];
        $end    = Jalali::create($year, 12, Jalali::daysInMonth($year, 12));

        for ($current = Jalali::create($year, 1, 1); $current->lte($end); $current = $current->addDays(1)) {
            $titles = self::getTitles($current);
            if ($titles !== []) {
                $result[$current->toDateString()] = $titles;
            }
        }

        return $result;
    }

    public static function isWeekend(Jalali $date): bool
    {
        return (int) $date->toGregorian()->format('w') === 5; // Friday
    }

    public static function isBusinessDay(Jalali $date): bool
    {
        return ! self::isWeekend($date) && ! self::isHoliday($date);
    }

    public static function nextBusinessDay(Jalali $date): Jalali
    {
        $next = $date->addDays(1);
        while (! self::isBusinessDay($next)) {
            $next = $next->addDays(1);
        }

        return $next;
    }
}
