<?php

declare(strict_types=1);

namespace RtlyKit\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RtlyKit\Contracts\CalendarDate;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Number\Digits;

/**
 * Immutable Jalali (Persian / Solar Hijri) date-time class.
 *
 * Fully compatible with DateTimeInterface and Carbon.
 *
 * Supported range: Jalali years {@see self::MIN_YEAR} .. {@see self::MAX_YEAR},
 * i.e. exactly the dates that map to Gregorian years 1..9999. Anything outside
 * (create(), add*()/sub*(), make(), createFromFormat()) throws
 * {@see InvalidDateException}; no other exception type, TypeError or wraparound
 * is produced by these entry points.
 *
 * String parsing in {@see self::make()}: Persian/Arabic digits are normalised
 * first, and a no-break space, ZWNJ or LRM/RLM mark counts as a space. A string
 * of the exact form `Y/m/d` or `Y-m-d` (3-4 digit year), optionally followed by
 * a space, `T` or `t` and `H:i[:s[.u]]` and a zone designator (`Z`, `+HH:MM`,
 * `+HHMM`, `+HH`; within +-14:00) whose year is below 1700 is read as a JALALI
 * date (so `1403/12/30`, `1404-07-15` and `1403-01-01T10:00:00+03:30` work). A
 * designator sets the zone of the result, and a $timezone argument then converts
 * to that zone. Text that starts like such a date but is not exactly that shape
 * (` UTC` or `Asia/Tehran` after the time, `1403/01/01 10:00 PM`, `1403-01`) throws {@see InvalidDateException}, as does a
 * bare run of 3-8 digits (`1403`, `14030101`) and a 5-digit year (code
 * date_out_of_range); pass a DateTimeImmutable for such values. Every other
 * string, including any year of 1700 or later and eight digits that start with
 * such a year (`20240101`), is handed to DateTimeImmutable as Gregorian/free-form
 * text. An invalid Jalali date (e.g. `1404/12/30`, a non-leap year) throws
 * {@see InvalidDateException}. Empty or whitespace-only strings and strings with
 * a NUL byte throw; `null` means "now". Given a Jalali instance, make() returns
 * it when no zone is given and converts it to the zone otherwise.
 *
 * An impossible Gregorian day in text (`2024-02-30`) throws InvalidDateException
 * instead of rolling over to the next month. Text dates have no negative years
 * (`-0100/01/01` throws); use create() for them.
 *
 * format(): `Y` has at least 4 digits, zero-padded, with a leading minus for
 * negative years (`-0005`, `0622`, `1403`); `y` is the last two digits by floor
 * modulo (year -620 gives `80`).
 *
 * Serialising keeps only the instant; unserialize() re-checks the range and
 * throws InvalidDateException for a malformed payload.
 *
 * createFromFormat() reads year, month and day as Jalali values. Tokens with a
 * Gregorian or time-zone meaning (`z e T P O p u v y F M D l S`) throw
 * {@see InvalidDateException} naming the token; the format `U` alone reads a Unix
 * timestamp. endOfDay()/endOfMonth()/endOfYear() end at 23:59:59.999999.
 *
 * The Gregorian/Jalali conversion is an independent implementation of the
 * arithmetic 33-year-cycle rule (leap years at residues 1, 5, 9, 13, 17, 22,
 * 26, 30 mod 33), pivoting on the Julian Day Number; it is not derived from
 * any third-party conversion library.
 *
 * Weekday numbering: `getDayOfWeek()` and the `w` format token are
 * Saturday-first (0 = Saturday .. 6 = Friday); `N` is 1 = Saturday .. 7 = Friday.
 * {@see Hijri} and {@see Hebrew} are Sunday-first instead (0 = Sunday ..
 * 6 = Saturday, like PHP's `w`).
 */
final class Jalali implements CalendarDate
{
    use CalendarDateTrait;

    /** Smallest supported Jalali year (begins 0001-03-21 CE). */
    public const MIN_YEAR = -620;

    /** Largest supported Jalali year (ends 9999-03-20 CE). */
    public const MAX_YEAR = 9377;

    private const OWN_YEAR_LIMIT = 1700;

    /** Longest accepted format() pattern, in bytes. */
    public const MAX_FORMAT_LENGTH = 256;

    /**
     * JDN of the (virtual) 1 Farvardin of year 0, chosen so that
     * 1 Farvardin 1404 = 2025-03-21 = JDN 2460756.
     */
    private const EPOCH_JDN = 1947955;

    /**
     * Leap years per 33-year cycle sit at residues 1, 5, 9, 13, 17, 22, 26, 30.
     * Entry r = how many of those residues are smaller than r (r = 0..32).
     */
    private const LEAP_BEFORE_RESIDUE = [
        0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 5, 6, 6, 6, 6, 7, 7, 7, 7, 8, 8,
    ];

    private const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /** Index 0 = Saturday (Shanbe) ... 6 = Friday (Jomeh). */
    private const WEEKDAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    private const WEEKDAY_SHORT = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    private function __construct(DateTimeImmutable $gregorian)
    {
        $this->gregorian = $gregorian;

        [$this->year, $this->month, $this->day] = self::gregorianToJalali(
            (int) $gregorian->format('Y'),
            (int) $gregorian->format('n'),
            (int) $gregorian->format('j'),
        );

        if ($this->year < self::MIN_YEAR || $this->year > self::MAX_YEAR) {
            throw InvalidDateException::because(
                ErrorCode::DateOutOfRange,
                sprintf('Date out of the supported Jalali range (%d..%d): %d', self::MIN_YEAR, self::MAX_YEAR, $this->year),
            );
        }

        $this->hour   = (int) $gregorian->format('G');
        $this->minute = (int) $gregorian->format('i');
        $this->second = (int) $gregorian->format('s');
    }

    private function withInstant(DateTimeImmutable $instant): static
    {
        return new self($instant);
    }

    private function restoreFrom(DateTimeImmutable $instant, array $data): void
    {
        $this->__construct($instant);
    }

    /* -----------------------------------------------------------------
     |  Factory Methods
     | -----------------------------------------------------------------
     */

    public static function make(DateTimeInterface|CalendarDate|string|int|null $time = null, ?DateTimeZone $timezone = null): static
    {
        if ($time instanceof self) {
            return $timezone === null ? $time : new self($time->gregorian->setTimezone($timezone));
        }

        if ($time instanceof CalendarDate) {
            $time = $time->toGregorian();
        }

        if ($time instanceof DateTimeInterface) {
            $immutable = DateTimeImmutable::createFromInterface($time);
            if ($timezone !== null) {
                $immutable = $immutable->setTimezone($timezone);
            }

            return new self($immutable);
        }

        if (is_int($time)) {
            $tz = $timezone ?? new DateTimeZone(date_default_timezone_get());

            return new self(CalendarLimits::fromTimestamp($time, $tz));
        }

        if (is_string($time)) {
            $normalized = CalendarLimits::normalize($time);
            $own = CalendarLimits::matchOwnFormat($normalized, static fn (int $year): bool => $year < self::OWN_YEAR_LIMIT);
            if ($own !== null) {
                // A zone designator in the text is read first; $timezone then converts the result.
                $created = self::create($own[0], $own[1], $own[2], $own[3], $own[4], $own[5], $own[7] ?? $timezone)->plusMicroseconds($own[6]);

                return $own[7] !== null && $timezone !== null ? self::make($created, $timezone) : $created;
            }

            return new self(CalendarLimits::parseGregorian($normalized, $timezone));
        }

        return new self(new DateTimeImmutable('now', $timezone));
    }

    public static function now(?DateTimeZone $timezone = null): static
    {
        return self::make(null, $timezone);
    }

    public static function today(?DateTimeZone $timezone = null): static
    {
        return self::make('today', $timezone)->startOfDay();
    }

    public static function create(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?DateTimeZone $timezone = null,
    ): static {
        if (! self::isValid($year, $month, $day)) {
            throw InvalidDateException::because(
                $year < self::MIN_YEAR || $year > self::MAX_YEAR ? ErrorCode::DateOutOfRange : ErrorCode::InvalidDate,
                "Invalid Jalali date: {$year}/{$month}/{$day}",
            );
        }

        [$gy, $gm, $gd] = self::jalaliToGregorian($year, $month, $day);

        CalendarLimits::time($hour, $minute, $second);

        $dateString = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $hour, $minute, $second);

        return new self(CalendarLimits::parseGregorian($dateString, $timezone));
    }

    public static function createFromFormat(string $format, string $time, ?DateTimeZone $timezone = null): static
    {
        $read = CalendarLimits::readFormat($format, $time);
        if (is_int($read)) {
            return self::make($read, $timezone);
        }

        return self::create($read[0], $read[1], $read[2], $read[3], $read[4], $read[5], $timezone);
    }

    /* -----------------------------------------------------------------
     |  Getters
     | -----------------------------------------------------------------
     */

    /**
     * Day of week, SATURDAY-first: 0 = Saturday (Shanbe) .. 6 = Friday (Jomeh).
     * Same as the `w` format token; differs from {@see Hijri::getDayOfWeek()}
     * and {@see Hebrew::getDayOfWeek()} (0 = Sunday).
     */
    public function getDayOfWeek(): int
    {
        return $this->weekdayIndex();
    }

    /** Persian name of this date's month (e.g. "مهر"). */
    public function monthName(): string
    {
        return self::MONTH_NAMES[$this->month];
    }

    /* -----------------------------------------------------------------
     |  Formatting
     | -----------------------------------------------------------------
     */

    /**
     * Format using PHP date() style tokens evaluated in the Jalali calendar.
     *
     * Supported: d D j l N w z F M m n t L Y y a A g G h H i s S W c r, plus
     * U e T P p O Z I u v (delegated to the underlying instant).
     * `w` is 0 = Saturday .. 6 = Friday; `N` is 1 = Saturday .. 7 = Friday.
     * `M` equals `F` (Persian month names have no abbreviation); `D` is the
     * one-letter weekday. `S` (English ordinal suffix) yields an empty string
     * because Persian has none. `W` is the ISO-8601 week number of the
     * underlying (Gregorian) instant. `c` is `Y-m-d\TH:i:sP` with the JALALI
     * date; `r` is the RFC 2822 string of the underlying Gregorian instant
     * (the RFC mandates English Gregorian names). Escape literals with a backslash. Unknown characters
     * are copied as-is. Pass $persianDigits = true to convert digits to Persian.
     * Patterns longer than {@see self::MAX_FORMAT_LENGTH} bytes throw
     * {@see InvalidDateException} (ErrorCode::InputTooLong).
     */
    public function format(string $format = 'Y/m/d H:i:s', bool $persianDigits = false): string
    {
        self::assertFormatLength($format);
        $len = strlen($format);

        $out = '';

        for ($i = 0; $i < $len; $i++) {
            $c = $format[$i];

            if ($c === '\\') {
                $i++;
                $out .= $i < $len ? $format[$i] : '';

                continue;
            }

            $out .= $this->formatToken($c);
        }

        return $persianDigits ? Digits::toPersian($out) : $out;
    }

    private function weekdayIndex(): int
    {
        return ((int) $this->gregorian->format('w') + 1) % 7;
    }

    private function formatToken(string $c): string
    {
        $h12 = $this->hour % 12 === 0 ? 12 : $this->hour % 12;

        return match ($c) {
            'Y' => $this->yearText(),
            'y' => $this->shortYearText(),
            'm' => sprintf('%02d', $this->month),
            'n' => (string) $this->month,
            'F', 'M' => self::MONTH_NAMES[$this->month],
            't' => (string) self::daysInMonth($this->year, $this->month),
            'L' => self::isLeapYear($this->year) ? '1' : '0',
            'd' => sprintf('%02d', $this->day),
            'j' => (string) $this->day,
            'l' => self::WEEKDAY_NAMES[$this->weekdayIndex()],
            'D' => self::WEEKDAY_SHORT[$this->weekdayIndex()],
            'w' => (string) $this->weekdayIndex(),
            'N' => (string) ($this->weekdayIndex() + 1),
            'z' => (string) $this->dayOfYear(),
            'a' => $this->hour < 12 ? 'ق.ظ' : 'ب.ظ',
            'A' => $this->hour < 12 ? 'قبل از ظهر' : 'بعد از ظهر',
            'g' => (string) $h12,
            'h' => sprintf('%02d', $h12),
            'G' => (string) $this->hour,
            'H' => sprintf('%02d', $this->hour),
            'i' => sprintf('%02d', $this->minute),
            's' => sprintf('%02d', $this->second),
            'S' => '',
            'W' => $this->gregorian->format('W'),
            'c' => sprintf('%s-%02d-%02dT%02d:%02d:%02d', $this->yearText(), $this->month, $this->day, $this->hour, $this->minute, $this->second)
                .$this->gregorian->format('P'),
            'r' => $this->gregorian->format('r'),
            'U', 'e', 'T', 'P', 'p', 'O', 'Z', 'I', 'u', 'v' => $this->gregorian->format($c),
            default => $c,
        };
    }

    /** Zero-based day of the Jalali year. */
    private function dayOfYear(): int
    {
        return $this->month <= 6
            ? ($this->month - 1) * 31 + $this->day - 1
            : 186 + ($this->month - 7) * 30 + $this->day - 1;
    }

    public function __toString(): string
    {
        return $this->toDateTimeString();
    }

    /* -----------------------------------------------------------------
     |  Modification (Immutable)
     | -----------------------------------------------------------------
     */

    public function addMonths(int $months): static
    {
        CalendarLimits::delta($months, (self::MAX_YEAR - self::MIN_YEAR + 2) * 12, 'months');

        if ($months === 0) {
            return $this;
        }

        $totalMonths = $this->year * 12 + ($this->month - 1) + $months;
        $newYear     = intdiv($totalMonths, 12);
        $newMonth    = $totalMonths - $newYear * 12;
        if ($newMonth < 0) {
            $newYear--;
            $newMonth += 12;
        }
        $newMonth++;

        if ($newYear < self::MIN_YEAR || $newYear > self::MAX_YEAR) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Jalali year out of the supported range: {$newYear}");
        }

        $day = min($this->day, self::daysInMonth($newYear, $newMonth));

        return $this->settle(self::create($newYear, $newMonth, $day, $this->hour, $this->minute, $this->second, $this->getTimezone()));
    }

    public function addYears(int $years): static
    {
        CalendarLimits::delta($years, self::MAX_YEAR - self::MIN_YEAR + 2, 'years');

        return $this->addMonths($years * 12);
    }

    public function startOfDay(): static
    {
        return self::create($this->year, $this->month, $this->day, 0, 0, 0, $this->getTimezone());
    }

    public function endOfDay(): static
    {
        return self::create($this->year, $this->month, $this->day, 23, 59, 59, $this->getTimezone())->atLastMicrosecond();
    }

    public function startOfMonth(): static
    {
        return self::create($this->year, $this->month, 1, 0, 0, 0, $this->getTimezone());
    }

    public function endOfMonth(): static
    {
        $lastDay = self::daysInMonth($this->year, $this->month);

        return self::create($this->year, $this->month, $lastDay, 23, 59, 59, $this->getTimezone())->atLastMicrosecond();
    }

    public function startOfYear(): static
    {
        return self::create($this->year, 1, 1, 0, 0, 0, $this->getTimezone());
    }

    public function endOfYear(): static
    {
        $lastDay = self::daysInMonth($this->year, 12);

        return self::create($this->year, 12, $lastDay, 23, 59, 59, $this->getTimezone())->atLastMicrosecond();
    }

    /* -----------------------------------------------------------------
     |  Difference
     | -----------------------------------------------------------------
     */

    /**
     * Whole Jalali months between two instants (day and time-of-day count,
     * microseconds included). Works for any two valid instants, whatever
     * their time zones.
     *
     * Not an exact inverse of addMonths() at a clamped month end (like Carbon):
     * create(1403, 6, 31)->addMonths(1) is 1403/07/30, and diffInMonths() between
     * those two is 0, because day 30 is before day 31.
     */
    public function diffInMonths(CalendarDate|DateTimeInterface $other, bool $absolute = true): int
    {
        $g = $this->instantInOwnZone($other);
        [$oy, $om, $od] = self::gregorianToJalali((int) $g->format('Y'), (int) $g->format('n'), (int) $g->format('j'));

        return $this->monthsBetween(
            $this->year * 12 + $this->month,
            [$this->day, ...self::clockOf($this->gregorian)],
            $oy * 12 + $om,
            [$od, ...self::clockOf($g)],
            $this->gregorian <= $g,
            $absolute,
        );
    }

    /**
     * Whole Jalali years between two instants (month, day and time count).
     *
     * Not an exact inverse of addYears() at a clamped end (like Carbon):
     * create(1403, 12, 30)->addYears(1) is 1404/12/29, and diffInYears() between
     * those two is 0.
     */
    public function diffInYears(CalendarDate|DateTimeInterface $other, bool $absolute = true): int
    {
        return intdiv($this->diffInMonths($other, $absolute), 12);
    }

    /* -----------------------------------------------------------------
     |  Static Helpers
     | -----------------------------------------------------------------
     */

    public static function isValid(int $year, int $month, int $day): bool
    {
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        return $day <= self::daysInMonth($year, $month);
    }

    /** 366 in a leap year, otherwise 365. */
    public static function daysInYear(int $year): int
    {
        return self::isLeapYear($year) ? 366 : 365;
    }

    public static function isLeapYear(int $year): bool
    {
        $mod = (($year % 33) + 33) % 33;

        return in_array($mod, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            return 0;
        }

        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return self::isLeapYear($year) ? 30 : 29;
    }


    /**
     * Number of leap years among the years before $year, counted from year 0
     * (so it is an offset, not an absolute count; negative years yield
     * negative values via floor division).
     *
     * Each full 33-year cycle holds 8 leap years; the remainder is looked up
     * in LEAP_BEFORE_RESIDUE.
     */
    private static function leapsBefore(int $year): int
    {
        $cycles = intdiv($year, 33);
        $rem    = $year - $cycles * 33;
        if ($rem < 0) {
            $rem += 33;
            $cycles--;
        }

        return 8 * $cycles + self::LEAP_BEFORE_RESIDUE[$rem];
    }

    /** Day number (JDN) of 1 Farvardin of $year. */
    private static function yearStartJdn(int $year): int
    {
        return self::EPOCH_JDN + 365 * $year + self::leapsBefore($year);
    }

    /**
     * Convert a Gregorian date (years 1..9999) to Jalali.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        CalendarLimits::gregorianDate($gy, $gm, $gd);

        $jdn = Jdn::fromGregorian($gy, $gm, $gd);
        $rel = $jdn - self::EPOCH_JDN;

        // The mean Jalali year is 12053/33 days; use it as a first guess,
        // then correct by at most a year in either direction.
        $guess = intdiv($rel * 33, 12053);
        if ($rel < 0 && $rel * 33 % 12053 !== 0) {
            $guess--;
        }
        $jy = $guess;
        // Defensive: the 33-year-cycle estimate does not overshoot for any supported year.
        // @codeCoverageIgnoreStart
        while (self::yearStartJdn($jy) > $jdn) {
            $jy--;
        }
        // @codeCoverageIgnoreEnd
        while (self::yearStartJdn($jy + 1) <= $jdn) {
            $jy++;
        }

        $doy = $jdn - self::yearStartJdn($jy); // 0-based day of year

        if ($doy < 186) {
            return [$jy, 1 + intdiv($doy, 31), 1 + $doy % 31];
        }

        return [$jy, 7 + intdiv($doy - 186, 30), 1 + ($doy - 186) % 30];
    }

    /**
     * Convert a Jalali date to Gregorian. The day may be 1..31 for any month
     * (overflow rolls forward, as in DateTime); the year must lie in
     * MIN_YEAR..MAX_YEAR.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        if ($jy < self::MIN_YEAR || $jy > self::MAX_YEAR || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Jalali date out of the supported range: {$jy}/{$jm}/{$jd}");
        }

        // Months 1-6 have 31 days, months 7-11 have 30.
        $monthOffset = $jm <= 6 ? ($jm - 1) * 31 : 186 + ($jm - 7) * 30;

        return Jdn::toGregorian(self::yearStartJdn($jy) + $monthOffset + $jd - 1);
    }
}
