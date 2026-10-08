<?php

declare(strict_types=1);

namespace RtlyKit\Contracts;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use RtlyKit\Exceptions\InvalidDateException;
use Stringable;

/**
 * Common contract of the immutable calendar date classes
 * ({@see \RtlyKit\Calendar\Jalali}, {@see \RtlyKit\Calendar\Hijri},
 * {@see \RtlyKit\Calendar\Hebrew}).
 *
 * Every implementation wraps one instant (a DateTimeImmutable) and exposes it
 * in its own calendar. Comparison and difference methods accept any other
 * CalendarDate or any DateTimeInterface, so dates of different calendars can
 * be compared directly (they are compared as instants).
 *
 * All methods that take a date, timestamp or delta throw only
 * {@see InvalidDateException} for out-of-range input, never TypeError or
 * ValueError. Implementations are final and immutable: every modifier returns
 * a new instance.
 *
 * Weekday numbering differs per calendar and is documented on each class:
 * Jalali is Saturday-first (0 = Saturday), Hijri and Hebrew are Sunday-first.
 *
 * `format()` is declared with the pattern only; each class adds its own
 * optional trailing parameters (digits, locale).
 */
interface CalendarDate extends Stringable, JsonSerializable
{
    /* ----- construction ----- */

    /**
     * @param static|DateTimeInterface|CalendarDate|string|int|null $time null = now; int = Unix timestamp
     *
     * @throws InvalidDateException
     */
    public static function make(DateTimeInterface|CalendarDate|string|int|null $time = null, ?DateTimeZone $timezone = null): static;

    public static function now(?DateTimeZone $timezone = null): static;

    public static function today(?DateTimeZone $timezone = null): static;

    /** @throws InvalidDateException */
    public static function create(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?DateTimeZone $timezone = null,
    ): static;

    /* ----- calendar arithmetic ----- */

    public static function isValid(int $year, int $month, int $day): bool;

    public static function isLeapYear(int $year): bool;

    public static function daysInYear(int $year): int;

    public static function daysInMonth(int $year, int $month): int;

    /* ----- getters ----- */

    public function getYear(): int;

    public function getMonth(): int;

    public function getDay(): int;

    public function getHour(): int;

    public function getMinute(): int;

    public function getSecond(): int;

    /** Per-calendar numbering, see the class docblocks. */
    public function getDayOfWeek(): int;

    public function getTimestamp(): int;

    public function getTimezone(): DateTimeZone;

    /** The underlying instant (proleptic Gregorian). */
    public function toGregorian(): DateTimeImmutable;

    /* ----- formatting ----- */

    public function format(string $format = 'Y/m/d H:i:s'): string;

    public function toDateString(): string;

    public function toDateTimeString(): string;

    /* ----- modification (immutable) ----- */

    /** @throws InvalidDateException */
    public function addDays(int $days): static;

    /** @throws InvalidDateException */
    public function subDays(int $days): static;

    /** @throws InvalidDateException */
    public function addHours(int $hours): static;

    /** @throws InvalidDateException */
    public function subHours(int $hours): static;

    /** @throws InvalidDateException */
    public function addMinutes(int $minutes): static;

    /** @throws InvalidDateException */
    public function subMinutes(int $minutes): static;

    /** @throws InvalidDateException */
    public function addSeconds(int $seconds): static;

    /** @throws InvalidDateException */
    public function subSeconds(int $seconds): static;

    /** @throws InvalidDateException */
    public function addMonths(int $months): static;

    /** @throws InvalidDateException */
    public function subMonths(int $months): static;

    /** @throws InvalidDateException */
    public function addYears(int $years): static;

    /** @throws InvalidDateException */
    public function subYears(int $years): static;

    public function startOfDay(): static;

    public function endOfDay(): static;

    public function startOfMonth(): static;

    public function endOfMonth(): static;

    public function startOfYear(): static;

    public function endOfYear(): static;

    /* ----- comparison (as instants; any calendar or DateTimeInterface) ----- */

    public function eq(CalendarDate|DateTimeInterface $other): bool;

    public function ne(CalendarDate|DateTimeInterface $other): bool;

    public function gt(CalendarDate|DateTimeInterface $other): bool;

    public function gte(CalendarDate|DateTimeInterface $other): bool;

    public function lt(CalendarDate|DateTimeInterface $other): bool;

    public function lte(CalendarDate|DateTimeInterface $other): bool;

    /** Alias of {@see self::eq()}. */
    public function equals(CalendarDate|DateTimeInterface $other): bool;

    /** Alias of {@see self::lt()}. */
    public function isBefore(CalendarDate|DateTimeInterface $other): bool;

    /** Alias of {@see self::gt()}. */
    public function isAfter(CalendarDate|DateTimeInterface $other): bool;

    public function between(CalendarDate|DateTimeInterface $first, CalendarDate|DateTimeInterface $second, bool $equal = true): bool;

    public function isPast(): bool;

    public function isFuture(): bool;

    public function isToday(): bool;

    /* ----- difference ----- */

    /** Whole days between two instants (truncated toward zero); signed when $absolute is false. */
    public function diffInDays(CalendarDate|DateTimeInterface $other, bool $absolute = true): int;

    /** Whole months of THIS calendar between two instants. */
    public function diffInMonths(CalendarDate|DateTimeInterface $other, bool $absolute = true): int;

    /** Whole years of THIS calendar between two instants. */
    public function diffInYears(CalendarDate|DateTimeInterface $other, bool $absolute = true): int;
}
