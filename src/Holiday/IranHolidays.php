<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Official Iranian public holidays (static facade over the default {@see HolidayCalendar}).
 *
 * - Fixed Solar (Jalali) holidays: exact.
 * - Variable Islamic holidays: for Jalali years covered by the published official table the official dates
 *   are used (see {@see self::sourceOf()}); for every other year they are estimated from the Hijri
 *   Umm al-Qura table. Iran fixes these holidays by moon sighting, so an estimate can be 0-2 days off.
 *
 * To correct estimates, add your own knowledge or read the origin of a date, build a calendar with
 * {@see self::calendar()} and its `with*` methods (a `HolidayCalendar` is immutable).
 *
 * Range: estimated Islamic holidays are only reported for dates inside the embedded Umm al-Qura table
 * (Hijri AH 1300-1500, 1882-11-12 .. 2077-11-16 CE; see {@see Hijri::hasUmmAlQuraData()}). Outside it
 * (where Hijri would fall back to tabular extrapolation, which is not reliable for holidays)
 * isHoliday(), getTitles(), all() and allTitles() return the fixed Jalali holidays only.
 * Year arguments must lie within {@see Jalali::MIN_YEAR}..{@see Jalali::MAX_YEAR};
 * otherwise InvalidDateException is thrown.
 */
final class IranHolidays
{
    /**
     * The default calendar these static methods use: official data where available, estimates elsewhere.
     */
    public static function calendar(): HolidayCalendar
    {
        return HolidayCalendar::default();
    }

    /**
     * How the Islamic holiday dates of a Jalali year are known. In a {@see HolidaySource::Reported} year
     * (1380-1393, 1395) the set of holidays may be incomplete.
     *
     * @throws InvalidDateException when the year is outside Jalali::MIN_YEAR..MAX_YEAR
     */
    public static function sourceOf(int $year): HolidaySource
    {
        return self::calendar()->sourceOf($year);
    }

    public static function isHoliday(Jalali|int $year, ?int $month = null, ?int $day = null): bool
    {
        return self::calendar()->isHoliday($year, $month, $day);
    }

    public static function getTitle(Jalali|int $year, ?int $month = null, ?int $day = null): ?string
    {
        return self::calendar()->getTitle($year, $month, $day);
    }

    /**
     * All holiday titles for a day: fixed Jalali ones first, then Islamic.
     *
     * @return list<string>
     */
    public static function getTitles(Jalali|int $year, ?int $month = null, ?int $day = null): array
    {
        return self::calendar()->getTitles($year, $month, $day);
    }

    /**
     * All fixed holidays for a Jalali year.
     *
     * @return array<string, string>
     */
    public static function allFixed(int $year): array
    {
        return self::calendar()->allFixed($year);
    }

    /**
     * All holidays (fixed + islamic) for a Jalali year; titles falling on the
     * same day are joined with " / ". See allTitles() for the structured form.
     *
     * @return array<string, string>
     */
    public static function all(int $year): array
    {
        return self::calendar()->all($year);
    }

    /**
     * All holidays for a Jalali year keyed by Y/m/d, each a list of titles.
     *
     * @return array<string, list<string>>
     */
    public static function allTitles(int $year): array
    {
        return self::calendar()->allTitles($year);
    }

    public static function isWeekend(Jalali $date): bool
    {
        return self::calendar()->isWeekend($date);
    }

    public static function isBusinessDay(Jalali $date): bool
    {
        return self::calendar()->isBusinessDay($date);
    }

    public static function nextBusinessDay(Jalali $date): Jalali
    {
        return self::calendar()->nextBusinessDay($date);
    }
}
