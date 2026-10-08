<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

/**
 * Where one returned holiday title came from. See {@see HolidayCalendar::statusOf()}.
 *
 * - `Fixed`: a fixed Jalali holiday (never moved by offsets).
 * - `Official` / `Reported` / `Estimated`: an Islamic holiday, with the quality of {@see HolidaySource}.
 * - `User`: added by the caller (`withHoliday()` or `withHijriMonthStart()`).
 */
enum HolidayOrigin: string
{
    case Fixed = 'fixed';
    case Official = 'official';
    case Reported = 'reported';
    case Estimated = 'estimated';
    case User = 'user';
}
