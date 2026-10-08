<?php

declare(strict_types=1);

namespace RtlyKit\Laravel;

use DateTimeInterface;
use DateTimeZone;
use RtlyKit\Calendar\Jalali;

/**
 * Container-resolvable, non-static wrapper around the static Jalali
 * constructors; it is the root behind the `Jalali` facade.
 */
final class JalaliFactory
{
    public function make(DateTimeInterface|string|int|Jalali|null $time = null, ?DateTimeZone $timezone = null): Jalali
    {
        return Jalali::make($time, $timezone);
    }

    public function now(?DateTimeZone $timezone = null): Jalali
    {
        return Jalali::now($timezone);
    }

    public function today(?DateTimeZone $timezone = null): Jalali
    {
        return Jalali::today($timezone);
    }

    public function create(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?DateTimeZone $timezone = null,
    ): Jalali {
        return Jalali::create($year, $month, $day, $hour, $minute, $second, $timezone);
    }
}
