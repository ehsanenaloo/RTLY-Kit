<?php

declare(strict_types=1);

namespace RtlyKit\Laravel\Casts;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RtlyKit\Calendar\CalendarLimits;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
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
 * Storage time zone and precision are opt-in cast arguments:
 * `JalaliCast::class.':UTC'` converts the instant to UTC on write and reads
 * the stored text as UTC (the attribute is then shown in PHP's default time
 * zone), `JalaliCast::class.':app'` uses PHP's default zone (Laravel's
 * `app.timezone`), and `JalaliCast::class.':UTC,6'` also keeps microseconds (0 to 6
 * fraction digits, matching the column precision). Without arguments the
 * wall-clock time of the assigned value is stored as it is, to the second,
 * and a stored value is read in PHP's default time zone.
 *
 * @implements CastsAttributes<Jalali, mixed>
 */
final class JalaliCast implements CastsAttributes
{
    private readonly ?DateTimeZone $fixedZone;

    /** True for the argument `app`: the storage zone is PHP's default zone, read each time (Laravel sets it from `app.timezone`). */
    private readonly bool $followsDefaultZone;

    private readonly int $precision;

    /**
     * @param string|null $storageTimezone zone of the stored text: a name such as `UTC`, an offset, or `app` for PHP's default zone; null or an empty string keeps the wall-clock behaviour
     * @param int|string  $precision       fraction digits stored, 0 to 6
     *
     * @throws InvalidDateException for an unknown time zone or a precision outside 0 to 6
     */
    public function __construct(?string $storageTimezone = null, int|string $precision = 0)
    {
        $zone = null;
        $follows = $storageTimezone === 'app';
        if ($storageTimezone !== null && $storageTimezone !== '' && ! $follows) {
            try {
                $zone = new DateTimeZone($storageTimezone);
            } catch (\Throwable $e) {
                throw InvalidDateException::because(ErrorCode::InvalidArgument, 'JalaliCast: the storage time zone is not valid.', ['argument' => 'storageTimezone'], $e);
            }
        }
        if ((is_string($precision) && preg_match('/^[0-6]$/D', $precision) !== 1) || (is_int($precision) && ($precision < 0 || $precision > 6))) {
            throw InvalidDateException::because(ErrorCode::InvalidDate, 'JalaliCast: the precision must be a whole number from 0 to 6.', ['argument' => 'precision']);
        }

        $this->fixedZone = $zone;
        $this->followsDefaultZone = $follows;
        $this->precision = (int) $precision;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Jalali
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            // The column holds a Gregorian datetime: read it as one, whatever the year
            // (Jalali::make() would read a Gregorian year below 1700 as Jalali).
            $zone = $this->storageZone();
            $instant = CalendarLimits::parseGregorian(CalendarLimits::normalize($value), $zone);
            if ($zone !== null) {
                $instant = $instant->setTimezone(new DateTimeZone(date_default_timezone_get()));
            }

            return Jalali::make($instant);
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

        // Eloquent calls set() again for every cast object it has cached, on every save. When the object still
        // is the moment the stored text reads as, keep that text: reading an attribute must not rewrite the column.
        if ($value instanceof Jalali && isset($attributes[$key]) && is_string($attributes[$key])) {
            try {
                $current = $this->get($model, $key, $attributes[$key], $attributes);
            } catch (InvalidDateException) {
                $current = null;
            }
            if ($current !== null && $current->format('U u') === $value->format('U u')) {
                return $attributes[$key];
            }
        }

        if (is_string($value)) {
            CalendarLimits::assertTextLength($value);
            $jalali = self::fromJalaliString($value) ?? Jalali::make($value);
        } elseif ($value instanceof Jalali || $value instanceof DateTimeInterface || is_int($value)) {
            $jalali = Jalali::make($value);
        } else {
            throw new InvalidDateException(sprintf("Cannot cast value for '%s' to a date.", $key), context: ['attribute' => $key, 'type' => get_debug_type($value)]);
        }

        return $this->format($jalali->toGregorian());
    }

    private function storageZone(): ?DateTimeZone
    {
        return $this->followsDefaultZone ? new DateTimeZone(date_default_timezone_get()) : $this->fixedZone;
    }

    private function format(DateTimeImmutable $instant): string
    {
        $zone = $this->storageZone();
        if ($zone !== null) {
            // Every supported Jalali date lies at least a day inside Gregorian years 1 to 9999, so no zone offset leaves that range.
            $instant = $instant->setTimezone($zone);
        }

        $text = $instant->format('Y-m-d H:i:s');

        return $this->precision === 0 ? $text : $text.'.'.substr($instant->format('u'), 0, $this->precision);
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
