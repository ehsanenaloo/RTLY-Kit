<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

/**
 * How well the Islamic-calendar holiday dates of one Jalali year are known.
 *
 * - `Official`: taken from the published official yearly calendar.
 * - `Reported`: taken from a single secondary source; the dates are listed but the holiday set is unverified
 *   and may be incomplete (e.g. Imam Reza or Imam Hasan Askari can be missing).
 * - `Estimated`: no published data; computed from the Umm al-Qura table, which can be 0-2 days off the
 *   moon-sighting based official dates.
 */
enum HolidaySource: string
{
    case Official = 'official';
    case Reported = 'reported';
    case Estimated = 'estimated';
}
