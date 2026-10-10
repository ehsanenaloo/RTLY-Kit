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
 * be compared directly (they are compared as instants, with microsecond
 * precision; month and year arithmetic keeps the microseconds).
 *
 * All methods that take a date, timestamp or delta throw only
 * {@see InvalidDateException} for out-of-range or unreadable values of the
 * accepted types, never TypeError or ValueError. A value of a type the
 * signature does not accept (for example an array) is rejected by PHP itself
 * with a TypeError. Implementations are final and immutable: every modifier
 * returns a new instance.
 *
 * String input of `make()`: Persian/Arabic digits are read as digits, and a
 * no-break space, ZWNJ or LRM/RLM mark counts as a space. Text that starts
 * like a date of the calendar itself (`Y/m/d` or `Y-m-d`; the year window is
 * given on each class) must then be exactly `Y/m/d`, optionally followed by a
 * space, `T` or `t` and `H:i[:s[.u]]` and a zone designator (`Z`, `+HH:MM`,
 * `+HHMM`, `+HH`; offsets within +-14:00), or {@see InvalidDateException} is
 * thrown (a zone name, `PM`, one-digit minutes or seconds, a year with a month
 * only). A designator sets the time zone of the result; a $timezone argument
 * then converts the result to that zone (the designator is read first).
 * A bare run of 3-8 digits (`1403`, `14030101`) is not a date and throws too.
 * Any other text is parsed as Gregorian/free-form text by DateTimeImmutable.
 *
 * `endOfDay()`, `endOfMonth()` and `endOfYear()` end at 23:59:59.999999 (the
 * last microsecond, as Carbon does), so `between(startOfDay(), endOfDay())`
 * holds for every time of the day; `startOf*()` results have no microseconds.
 *
 * `make()` given an instance of the same class returns that instance when no
 * time zone is given. A time zone converts it to that zone (same instant).
 * `Hijri::make()` also takes a variant, `null` by default: null keeps the variant
 * of a Hijri instance (and means UmmAlQura for any other input); a variant that is
 * given, UmmAlQura included, always applies and converts an instance that differs.
 *
 * `createFromFormat()` (Jalali, Hijri and Hebrew; the signatures differ by the
 * optional trailing Hijri variant, so it is not part of this interface) reads
 * Y/m/d H:i:s style tokens as values of that calendar. Tokens with a Gregorian
 * or time-zone meaning (`z e T P O p u v y F M D l S`, and `U` mixed with
 * others) throw {@see InvalidDateException}; the format `U` alone reads a Unix
 * timestamp.
 *
 * `make()` and the calendar `create*()` methods reject an impossible Gregorian
 * day in text (`2024-02-30`) with {@see InvalidDateException}; PHP itself would
 * roll it over to 2024-03-01.
 *
 * `format()` writes `Y` with at least 4 digits, zero-padded, and a leading minus
 * for negative years (`-0005`, `0622`, `1403`, `10000`); `y` is the last two
 * digits by floor modulo (year -620 gives `80`, year 5 gives `05`). Text dates
 * have no negative years: make() cannot read `-0005/01/01` (use create()).
 *
 * Serialising keeps only the instant (a versioned array); unserialize() rebuilds
 * the object through the same range checks as the constructor and throws
 * {@see InvalidDateException} for a malformed or out-of-range payload.
 *
 * `diffInMonths()` and `diffInYears()` count whole units with the microsecond
 * part included, and work for any two valid instants, whatever their zones.
 * `addMonths(0)` and `addYears(0)` return the same instance; other shifts keep
 * the UTC offset when only a DST overlap would pick the other offset.
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

    /**
     * Whole months of THIS calendar between two instants.
     *
     * add*() and diff*() are not exact inverses at a clamped month end (like
     * Carbon): 1403/06/31 plus one month is 1403/07/30, and diffInMonths()
     * from 1403/06/31 to 1403/07/30 is 0 because the day 30 is before the day 31.
     */
    public function diffInMonths(CalendarDate|DateTimeInterface $other, bool $absolute = true): int;

    /**
     * Whole years of THIS calendar between two instants.
     *
     * Not the inverse of addYears() at a clamped end: 1403/12/30 (a leap-year
     * last day) plus one year is 1404/12/29, and diffInYears() between them is 0.
     */
    public function diffInYears(CalendarDate|DateTimeInterface $other, bool $absolute = true): int;
}
