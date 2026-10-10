<?php

declare(strict_types=1);

namespace RtlyKit\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RtlyKit\Contracts\CalendarDate;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Behaviour shared by {@see Jalali}, {@see Hijri} and {@see Hebrew}: the
 * wrapped instant, field getters, instant-based comparison, day difference
 * and the unit-based add/sub methods. Calendar-specific logic stays in the
 * using class.
 *
 * Using classes must declare `private function withInstant(DateTimeImmutable): static`
 * and `addMonths()`, `addYears()` and `format()`.
 *
 * @internal Not part of the public API.
 */
trait CalendarDateTrait
{
    private readonly DateTimeImmutable $gregorian;
    private readonly int $year;
    private readonly int $month;
    private readonly int $day;
    private readonly int $hour;
    private readonly int $minute;
    private readonly int $second;

    /**
     * Reject a format pattern longer than the shared cap (the using class
     * declares `MAX_FORMAT_LENGTH`).
     *
     * @throws InvalidDateException with ErrorCode::InputTooLong
     */
    private static function assertFormatLength(string $format): void
    {
        $len = strlen($format);
        if ($len > self::MAX_FORMAT_LENGTH) {
            throw new InvalidDateException(
                sprintf('Format string too long (%d bytes, max %d).', $len, self::MAX_FORMAT_LENGTH),
                errorCode: ErrorCode::InputTooLong,
                context: ['argument' => 'format', 'limit' => self::MAX_FORMAT_LENGTH],
            );
        }
    }

    /** New instance of the same calendar (and variant) for another instant. */
    abstract private function withInstant(DateTimeImmutable $instant): static;

    /**
     * Year for the `Y` token: at least 4 digits, zero-padded, with a leading
     * minus for negative years (like PHP: `-0005`, `0622`, `1403`, `10000`).
     */
    private function yearText(): string
    {
        return ($this->year < 0 ? '-' : '').str_pad((string) abs($this->year), 4, '0', STR_PAD_LEFT);
    }

    /** Last two digits of the year for the `y` token, by floor modulo (-620 gives `80`, 5 gives `05`). */
    private function shortYearText(): string
    {
        return sprintf('%02d', (($this->year % 100) + 100) % 100);
    }

    /** Version of the serialised payload layout. */
    private const SERIAL_VERSION = 1;

    private const SERIAL_INSTANT_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * Serialised form: only the instant, so that unserialize() can rebuild every
     * field through the constructor checks. Keys: `v` (layout version),
     * `instant` (ISO 8601 with microseconds and UTC offset) and `timezone`
     * (zone name or offset of the instance); Hijri adds `variant`.
     *
     * @return array<string, int|string>
     */
    public function __serialize(): array
    {
        return [
            'v' => self::SERIAL_VERSION,
            'instant' => $this->gregorian->format(self::SERIAL_INSTANT_FORMAT),
            'timezone' => $this->gregorian->getTimezone()->getName(),
        ] + $this->serialExtra();
    }

    /**
     * Rebuilds the object from a payload of {@see self::__serialize()} (or from
     * the property layout older releases wrote) and runs the same range checks
     * as the constructor.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidDateException for a payload that is malformed or outside the supported range
     */
    public function __unserialize(array $data): void
    {
        if (isset($this->gregorian)) {
            throw InvalidDateException::because(ErrorCode::InvalidDate, 'Cannot unserialize into a date that already exists.');
        }

        $legacy = $data["\0".self::class."\0gregorian"] ?? null;
        if ($legacy instanceof DateTimeImmutable) {
            $instant = $legacy;
            $data = ['variant' => $data["\0".self::class."\0variant"] ?? null];
        } else {
            if (($data['v'] ?? null) !== self::SERIAL_VERSION) {
                throw InvalidDateException::because(ErrorCode::InvalidDate, 'Cannot unserialize the date: unknown payload version.');
            }
            $instant = self::instantFromPayload($data['instant'] ?? null, $data['timezone'] ?? null);
        }

        $this->restoreFrom($instant, $data);
    }

    /**
     * Extra payload keys of the using class (Hijri: the variant).
     *
     * @return array<string, string>
     */
    private function serialExtra(): array
    {
        return [];
    }

    /**
     * Run the constructor on the (still empty) object with the validated instant.
     *
     * @param array<array-key, mixed> $data
     */
    abstract private function restoreFrom(DateTimeImmutable $instant, array $data): void;

    private static function instantFromPayload(mixed $text, mixed $zoneName): DateTimeImmutable
    {
        $fail = static fn (string $why): InvalidDateException => InvalidDateException::because(ErrorCode::InvalidDate, "Cannot unserialize the date: {$why}.");

        if (! is_string($text) || ! is_string($zoneName)) {
            throw $fail('the instant and the time zone must be strings');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/D', $text) !== 1) {
            throw $fail('the instant is not an ISO 8601 date-time');
        }
        $instant = DateTimeImmutable::createFromFormat(self::SERIAL_INSTANT_FORMAT, $text);
        if ($instant === false || $instant->format(self::SERIAL_INSTANT_FORMAT) !== $text) {
            throw $fail('the instant is not a real date-time');
        }
        if (strlen($zoneName) > 64) {
            throw $fail('the time zone is not valid');
        }
        try {
            $zone = new DateTimeZone($zoneName);
        } catch (\Throwable) {
            throw $fail('the time zone is not valid');
        }

        return $instant->setTimezone($zone);
    }


    /**
     * Finish a result that was built from calendar fields (whole-second
     * precision, wall-clock time): carry this instant's microseconds over, and
     * when the wall-clock time is the same as before but a DST fold made PHP
     * pick the other of its two occurrences, keep this instant's UTC offset
     * (01:30 EST stays 01:30 EST, not 01:30 EDT).
     */
    private function settle(self $result): static
    {
        $g = $result->gregorian;

        $before = $this->gregorian->getOffset();
        $after  = $g->getOffset();
        if ($before !== $after && $g->format('H:i:s') === $this->gregorian->format('H:i:s')) {
            $other = $g->setTimestamp($g->getTimestamp() + $after - $before);
            if ($other->getOffset() === $before && $other->format('Y-m-d H:i:s') === $g->format('Y-m-d H:i:s')) {
                $g = $other;
            }
        }

        $micro = (int) $this->gregorian->format('u');
        if ($micro !== 0) {
            $g = $g->modify(sprintf('+%d usec', $micro));
        }

        return $g === $result->gregorian ? $result : $this->withInstant($g);
    }

    /** This date plus a sub-second part (0..999999 microseconds). */
    private function plusMicroseconds(int $microseconds): static
    {
        return $microseconds === 0 ? $this : $this->withInstant($this->gregorian->modify(sprintf('+%d usec', $microseconds)));
    }

    /** The last microsecond of this second (end-of-period results end at 23:59:59.999999). */
    private function atLastMicrosecond(): static
    {
        return $this->plusMicroseconds(999999);
    }

    /** Any date value as an instant expressed in this instance's time zone (no range check). */
    private function instantInOwnZone(CalendarDate|DateTimeInterface $other): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface(self::instantOf($other))->setTimezone($this->getTimezone());
    }

    /**
     * Clock part of a calendar date for comparisons, microseconds included.
     *
     * @return array{int, int, int, int}
     */
    private static function clockOf(DateTimeImmutable $g): array
    {
        return [(int) $g->format('G'), (int) $g->format('i'), (int) $g->format('s'), (int) $g->format('u')];
    }

    /**
     * Whole months between this date and another, signed as `this - other`
     * when $absolute is false. The month counts and day/clock tails are the
     * calendar fields of both dates in this instance's time zone.
     *
     * @param array{int, int, int, int, int} $thisTail  [day, hour, minute, second, microsecond]
     * @param array{int, int, int, int, int} $otherTail
     */
    private function monthsBetween(int $thisIndex, array $thisTail, int $otherIndex, array $otherTail, bool $thisIsLower, bool $absolute): int
    {
        [$loIndex, $loTail, $hiIndex, $hiTail] = $thisIsLower
            ? [$thisIndex, $thisTail, $otherIndex, $otherTail]
            : [$otherIndex, $otherTail, $thisIndex, $thisTail];

        $months = $hiIndex - $loIndex;
        if ($hiTail < $loTail) {
            $months--;
        }

        return $absolute || ! $thisIsLower ? $months : -$months;
    }

    /** Order of two instants with microsecond precision, in any time zone: -1, 0 or 1. */
    private function compareInstant(CalendarDate|DateTimeInterface $other): int
    {
        return $this->gregorian <=> self::instantOf($other);
    }

    /* -----------------------------------------------------------------
     |  Getters
     | -----------------------------------------------------------------
     */

    public function getYear(): int
    {
        return $this->year;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function getHour(): int
    {
        return $this->hour;
    }

    public function getMinute(): int
    {
        return $this->minute;
    }

    public function getSecond(): int
    {
        return $this->second;
    }

    public function getTimestamp(): int
    {
        return $this->gregorian->getTimestamp();
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->gregorian->getTimezone();
    }

    public function toGregorian(): DateTimeImmutable
    {
        return $this->gregorian;
    }

    public function toDateString(): string
    {
        return $this->format('Y/m/d');
    }

    /**
     * JSON form: the same text as __toString (e.g. "1405/01/01 00:00:00"), so models and
     * arrays holding calendar dates serialise to readable text instead of {}.
     */
    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    public function toDateTimeString(): string
    {
        return $this->format('Y/m/d H:i:s');
    }

    /* -----------------------------------------------------------------
     |  Modification (immutable)
     | -----------------------------------------------------------------
     */

    public function addDays(int $days): static
    {
        CalendarLimits::delta($days, CalendarLimits::DELTA_DAYS, 'days');

        return $this->withInstant($this->gregorian->modify(sprintf('%+d days', $days)));
    }

    public function subDays(int $days): static
    {
        return $this->addDays(CalendarLimits::negate($days));
    }

    public function addHours(int $hours): static
    {
        CalendarLimits::delta($hours, CalendarLimits::DELTA_HOURS, 'hours');

        return $this->withInstant($this->gregorian->modify(sprintf('%+d hours', $hours)));
    }

    public function subHours(int $hours): static
    {
        return $this->addHours(CalendarLimits::negate($hours));
    }

    public function addMinutes(int $minutes): static
    {
        CalendarLimits::delta($minutes, CalendarLimits::DELTA_MINUTES, 'minutes');

        return $this->withInstant($this->gregorian->modify(sprintf('%+d minutes', $minutes)));
    }

    public function subMinutes(int $minutes): static
    {
        return $this->addMinutes(CalendarLimits::negate($minutes));
    }

    public function addSeconds(int $seconds): static
    {
        CalendarLimits::delta($seconds, CalendarLimits::DELTA_SECONDS, 'seconds');

        return $this->withInstant($this->gregorian->modify(sprintf('%+d seconds', $seconds)));
    }

    public function subSeconds(int $seconds): static
    {
        return $this->addSeconds(CalendarLimits::negate($seconds));
    }

    public function subMonths(int $months): static
    {
        return $this->addMonths(CalendarLimits::negate($months));
    }

    public function subYears(int $years): static
    {
        return $this->addYears(CalendarLimits::negate($years));
    }

    /* -----------------------------------------------------------------
     |  Comparison (as instants; any calendar or DateTimeInterface)
     | -----------------------------------------------------------------
     */

    public function eq(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->compareInstant($other) === 0;
    }

    public function ne(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->compareInstant($other) !== 0;
    }

    public function gt(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->compareInstant($other) > 0;
    }

    public function gte(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->compareInstant($other) >= 0;
    }

    public function lt(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->compareInstant($other) < 0;
    }

    public function lte(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->compareInstant($other) <= 0;
    }

    public function equals(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->eq($other);
    }

    public function isBefore(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->lt($other);
    }

    public function isAfter(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->gt($other);
    }

    public function between(CalendarDate|DateTimeInterface $first, CalendarDate|DateTimeInterface $second, bool $equal = true): bool
    {
        $a = self::instantOf($first);
        $b = self::instantOf($second);
        $t = $this->gregorian;

        if ($a > $b) {
            [$a, $b] = [$b, $a];
        }

        return $equal ? ($t >= $a && $t <= $b) : ($t > $a && $t < $b);
    }

    public function isPast(): bool
    {
        return $this->getTimestamp() < time();
    }

    public function isFuture(): bool
    {
        return $this->getTimestamp() > time();
    }

    /** True when this date is today's date in this instance's own time zone. */
    public function isToday(): bool
    {
        $now = new DateTimeImmutable('now', $this->getTimezone());

        return $this->gregorian->format('Y-m-d') === $now->format('Y-m-d');
    }

    /* -----------------------------------------------------------------
     |  Difference
     | -----------------------------------------------------------------
     */

    /** The underlying instant of any supported date value. */
    private static function instantOf(CalendarDate|DateTimeInterface $date): DateTimeInterface
    {
        return $date instanceof CalendarDate ? $date->toGregorian() : $date;
    }

    /**
     * Whole days between two instants (calendar/DST aware, truncated toward zero).
     * With $absolute = false the sign is that of `$this - $other`.
     */
    public function diffInDays(CalendarDate|DateTimeInterface $other, bool $absolute = true): int
    {
        $interval = $this->gregorian->diff(self::instantOf($other));
        $days     = (int) $interval->days;

        return $absolute || $interval->invert === 1 ? $days : -$days;
    }
}
