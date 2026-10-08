<?php

declare(strict_types=1);

namespace RtlyKit\Support;

use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Registers Carbon macros when nesbot/carbon is available.
 *
 * Macros: toJalali(), jformat($format), and the static createFromJalali(...)
 * which returns the class it is called on (Carbon or CarbonImmutable).
 * Carbon 3 shares macros between Carbon and CarbonImmutable, so the closures
 * are class-agnostic and registered once per class (safe for Carbon 2 too).
 */
final class CarbonMacros
{
    /**
     * Normalise a timezone argument (object, identifier string or null).
     *
     * @internal used by the registered macros
     * @throws InvalidDateException on an unknown timezone identifier
     */
    public static function zone(\DateTimeZone|string|null $tz): ?\DateTimeZone
    {
        if ($tz === null || $tz instanceof \DateTimeZone) {
            return $tz;
        }

        try {
            return new \DateTimeZone($tz);
        } catch (\Exception $e) {
            throw new InvalidDateException("Unknown timezone: {$tz}", previous: $e);
        }
    }

    /**
     * Narrow the class a static macro was invoked on to a Carbon class.
     *
     * Inside a macro closure `static::class` is only known at runtime (Carbon
     * binds it to the called class), so static analysis cannot prove its type;
     * this runtime check replaces a suppressed annotation.
     *
     * @internal used by the registered macros
     *
     * @return class-string<\Carbon\CarbonInterface>
     */
    public static function target(string $class): string
    {
        if (! is_a($class, \Carbon\CarbonInterface::class, true)) {
            throw new \RtlyKit\Exceptions\RtlyKitException(sprintf('%s is not a Carbon class.', $class));
        }

        return $class;
    }

    public static function register(): void
    {
        if (! class_exists(\Carbon\Carbon::class)) {
            // Carbon is always installed in the test environment.
            return; // @codeCoverageIgnore
        }

        $classes = [\Carbon\Carbon::class];
        if (class_exists(\Carbon\CarbonImmutable::class)) {
            $classes[] = \Carbon\CarbonImmutable::class;
        }

        foreach ($classes as $class) {
            // Convert a Carbon instance to Jalali
            $class::macro('toJalali', function (): Jalali {
                /** @var \DateTimeInterface $this */
                return Jalali::make($this);
            });

            // Format as Jalali
            $class::macro('jformat', function (string $format = 'Y/m/d H:i:s'): string {
                /** @var \DateTimeInterface $this */
                return Jalali::make($this)->format($format);
            });

            // Create an instance of the called class from Jalali values
            $class::macro('createFromJalali', static function (
                int $year,
                int $month,
                int $day,
                int $hour = 0,
                int $minute = 0,
                int $second = 0,
                \DateTimeZone|string|null $tz = null,
            ): \DateTimeInterface {
                $jalali = Jalali::create($year, $month, $day, $hour, $minute, $second, CarbonMacros::zone($tz));

                // At runtime Carbon binds `static` to the class the macro was called on.
                $target = CarbonMacros::target(static::class);

                return $target::instance($jalali->toGregorian());
            });

            // Convert to Hijri (Umm al-Qura unless a variant is given)
            $class::macro('toHijri', function (?HijriVariant $variant = null): Hijri {
                /** @var \DateTimeInterface $this */
                return Hijri::make($this, null, $variant ?? HijriVariant::UmmAlQura);
            });

            // Convert to Hebrew
            $class::macro('toHebrew', function (): Hebrew {
                /** @var \DateTimeInterface $this */
                return Hebrew::make($this);
            });

            $class::macro('createFromHijri', static function (
                int $year,
                int $month,
                int $day,
                int $hour = 0,
                int $minute = 0,
                int $second = 0,
                \DateTimeZone|string|null $tz = null,
                ?HijriVariant $variant = null,
            ): \DateTimeInterface {
                $hijri = Hijri::create($year, $month, $day, $hour, $minute, $second, CarbonMacros::zone($tz), $variant ?? HijriVariant::UmmAlQura);

                $target = CarbonMacros::target(static::class);

                return $target::instance($hijri->toGregorian());
            });

            $class::macro('createFromHebrew', static function (
                int $year,
                int $month,
                int $day,
                int $hour = 0,
                int $minute = 0,
                int $second = 0,
                \DateTimeZone|string|null $tz = null,
            ): \DateTimeInterface {
                $hebrew = Hebrew::create($year, $month, $day, $hour, $minute, $second, CarbonMacros::zone($tz));

                $target = CarbonMacros::target(static::class);

                return $target::instance($hebrew->toGregorian());
            });
        }
    }
}
