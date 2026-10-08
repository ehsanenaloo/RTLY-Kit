<?php

declare(strict_types=1);

namespace RtlyKit\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RtlyKit\Contracts\CalendarDate;

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

    /** New instance of the same calendar (and variant) for another instant. */
    abstract private function withInstant(DateTimeImmutable $instant): static;

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
        return $this->getTimestamp() === $other->getTimestamp();
    }

    public function ne(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->getTimestamp() !== $other->getTimestamp();
    }

    public function gt(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->getTimestamp() > $other->getTimestamp();
    }

    public function gte(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->getTimestamp() >= $other->getTimestamp();
    }

    public function lt(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->getTimestamp() < $other->getTimestamp();
    }

    public function lte(CalendarDate|DateTimeInterface $other): bool
    {
        return $this->getTimestamp() <= $other->getTimestamp();
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
        $a = $first->getTimestamp();
        $b = $second->getTimestamp();
        $t = $this->getTimestamp();

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
