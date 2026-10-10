<?php

declare(strict_types=1);

/*
 * RTLY-Kit configuration. Publish with:
 *
 *     php artisan vendor:publish --tag=rtly-kit-config
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Holidays
    |--------------------------------------------------------------------------
    |
    | Iran sets its religious holidays by moon sighting, so dates computed from the
    | Umm al-Qura table can be 0-2 days off. The package ships the published official
    | dates for the Jalali years it knows (see HolidayCalendar::sourceOf()); the options
    | below let you correct the rest. They build the HolidayCalendar bound in the container.
    |
    | The `holidays` entry must be an array (or absent): any other value throws an
    | InvalidDateException when the HolidayCalendar is first resolved. Unknown keys and
    | wrongly typed options are rejected the same way.
    |
    | islamic_offset      -3..3 days added to ESTIMATED Islamic holidays only (years without
    |                     official data). Fixed Jalali holidays never move.
    |                     An int-like string such as env() returns ('1', '-2') is cast to int;
    |                     floats and other strings are rejected.
    | hijri_month_starts  The real first day of a Hijri month, from moon sighting. Every Islamic
    |                     holiday of that month is derived from it, official years included.
    |                     Format: '1447-10' => '2026-03-21' (Gregorian, at most 3 days from the
    |                     computed start).
    | extra               Holidays to add: '1405/02/03' => 'Title' (or a list of titles).
    | removed             Holidays to remove: '1405/02/03' (whole day) or
    |                     '1405/02/03' => 'Title' (or a list of titles).
    | use_official_data   Set to false to ignore the shipped official dates and estimate every year.
    |
    */
    'holidays' => [
        'islamic_offset' => 0,
        'hijri_month_starts' => [],
        'extra' => [],
        'removed' => [],
        'use_official_data' => true,
    ],

];
