<?php

declare(strict_types=1);

namespace RtlyKit\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use RtlyKit\Calendar\Jalali as JalaliClass;
use RtlyKit\Laravel\RtlyKitServiceProvider;

/**
 * @method static JalaliClass make(\DateTimeInterface|string|int|null $time = null, ?\DateTimeZone $timezone = null)
 * @method static JalaliClass now(?\DateTimeZone $timezone = null)
 * @method static JalaliClass today(?\DateTimeZone $timezone = null)
 * @method static JalaliClass create(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0, ?\DateTimeZone $timezone = null)
 *
 * @see \RtlyKit\Calendar\Jalali
 */
class Jalali extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RtlyKitServiceProvider::FACTORY_ABSTRACT;
    }
}
