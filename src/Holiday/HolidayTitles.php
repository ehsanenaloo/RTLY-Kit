<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

/**
 * The statutory title tables shared by {@see HolidayCalendar} and the data loader.
 *
 * @internal
 */
final class HolidayTitles
{
    /**
     * Fixed Jalali holidays: month => [day => title].
     */
    public const FIXED = [
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
     * Islamic (Hijri) holidays: month => [day => title]. Imam Reza (last day of Safar) is {@see self::IMAM_REZA}.
     */
    public const ISLAMIC = [
        1  => [
            9  => 'تاسوعای حسینی',
            10 => 'عاشورای حسینی',
        ],
        2  => [
            20 => 'اربعین حسینی',
            28 => 'رحلت پیامبر و شهادت امام حسن',
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

    /** Last day of Safar (29 or 30 days, so it cannot sit in the day table). */
    public const IMAM_REZA = 'شهادت امام رضا';

    /**
     * Every Islamic title mapped to the Hijri months whose start decides its date
     * (Imam Reza depends on the end of Safar, i.e. the start of Rabi I as well).
     *
     * @return array<string, list<int>>
     */
    public static function islamicMonths(): array
    {
        $map = [self::IMAM_REZA => [2, 3]];
        foreach (self::ISLAMIC as $month => $days) {
            foreach ($days as $title) {
                $map[$title] = [$month];
            }
        }

        return $map;
    }

    /**
     * Every Islamic title the data file may use.
     *
     * @return list<string>
     */
    public static function knownIslamic(): array
    {
        return array_keys(self::islamicMonths());
    }
}
