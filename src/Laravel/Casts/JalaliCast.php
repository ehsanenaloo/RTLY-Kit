<?php

declare(strict_types=1);

namespace RtlyKit\Laravel\Casts;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RtlyKit\Calendar\CalendarLimits;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Number\Digits;

/**
 * Eloquent cast: the column stores a Gregorian datetime ("Y-m-d H:i:s"),
 * the attribute is exposed as an immutable {@see Jalali}.
 *
 * Accepted on assignment: Jalali, any DateTimeInterface, a Gregorian string,
 * a Unix timestamp (int) or a Jalali string such as "1404/01/15 10:30"
 * (separator "/" or "-", Persian digits allowed; years 1200-1599 are read as
 * Jalali). Invalid input throws {@see \RtlyKit\Exceptions\InvalidDateException}.
 *
 * Reading: the stored value is always read as Gregorian (a string, a Unix
 * timestamp or a date object). A value of any other type throws
 * InvalidDateException; only null and the empty string give null.
 *
 * @implements CastsAttributes<Jalali, mixed>
 */
final class JalaliCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Jalali
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            // The column holds a Gregorian datetime: read it as one, whatever the year
            // (Jalali::make() would read a Gregorian year below 1700 as Jalali).
            return Jalali::make(CalendarLimits::parseGregorian(CalendarLimits::normalize($value), null));
        }

        if ($value instanceof DateTimeInterface || is_int($value)) {
            return Jalali::make($value);
        }

        throw new InvalidDateException(sprintf("Cannot read the value of '%s' as a date.", $key), context: ['attribute' => $key, 'type' => get_debug_type($value)]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $jalali = self::fromJalaliString($value) ?? Jalali::make($value);
        } elseif ($value instanceof Jalali || $value instanceof DateTimeInterface || is_int($value)) {
            $jalali = Jalali::make($value);
        } else {
            throw new InvalidDateException(sprintf("Cannot cast value for '%s' to a date.", $key), context: ['attribute' => $key, 'type' => get_debug_type($value)]);
        }

        return $jalali->toGregorian()->format('Y-m-d H:i:s');
    }

    private static function fromJalaliString(string $value): ?Jalali
    {
        $value = Digits::toEnglish(trim($value));
        $pattern = '/^(1[2-5]\d\d)[\/-](\d{1,2})[\/-](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/';

        if (preg_match($pattern, $value, $m) !== 1) {
            return null;
        }

        return Jalali::create((int) $m[1], (int) $m[2], (int) $m[3], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));
    }
}
