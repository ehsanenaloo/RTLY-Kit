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
 * Immutable Hebrew (Jewish) calendar date-time class.
 *
 * Pure PHP, no ext-calendar. Implements the fixed arithmetic Hebrew calendar
 * (molad of Tishrei, the four dehiyyot postponements, 353/354/355 and
 * 383/384/385-day years) and converts through the Julian Day Number, using
 * the proleptic Gregorian calendar.
 *
 * Supported range: Hebrew years {@see self::MIN_YEAR} .. {@see self::MAX_YEAR},
 * exactly the years that lie inside Gregorian years 1..9999 (a Hebrew year
 * starts in September/October). Anything outside throws
 * {@see InvalidDateException} (create(), add*()/sub*(), make()); isValid()
 * is false. The pure arithmetic helpers daysInYear()/daysInMonth()/monthName()
 * accept years 1..MAX_YEAR.
 *
 * String parsing in {@see self::make()}: Persian/Arabic digits are normalised
 * first; `Y/m/d` or `Y-m-d` (4-digit year, optional ` H:i[:s]`) with a year of
 * 3000 or more is read as a HEBREW date using the ordinal month numbering
 * below (`5785/01/10`); every other string is parsed as Gregorian/free-form
 * text. Invalid Hebrew dates and empty/whitespace strings throw
 * {@see InvalidDateException}; `null` means "now".
 *
 * Weekday numbering: `getDayOfWeek()` / `w` are Sunday-first (0 = Sunday ..
 * 6 = Saturday), unlike {@see Jalali::getDayOfWeek()} (0 = Saturday).
 *
 * Month numbering is the ORDINAL position in the year starting from Tishrei
 * (the civil new year): 1 Tishrei, 2 Cheshvan, 3 Kislev, 4 Tevet, 5 Shevat,
 * then 6 Adar (regular year) or 6 Adar I + 7 Adar II (leap year), followed by
 * Nisan, Iyar, Sivan, Tammuz, Av, Elul. So Elul is month 12 in a regular year
 * and month 13 in a leap year, and Nisan is month 7 or 8. This differs from
 * Hebcal (Nisan = 1) and from ext-calendar's numbering.
 *
 * Limitation: Hebrew days really begin at sunset; this class uses civil
 * midnight, like the other calendars in this package.
 */
final class Hebrew implements CalendarDate
{
    use CalendarDateTrait;

    /** Smallest supported Hebrew year (1 Tishrei 3762 = 0001-09-06 CE). */
    public const MIN_YEAR = 3762;

    /** Longest accepted format() pattern, in bytes. */
    public const MAX_FORMAT_LENGTH = 256;

    /** Largest supported Hebrew year (ends 9999-11-03 CE). */
    public const MAX_YEAR = 13759;

    private const OWN_YEAR_MIN = 3000;
    /** JDN of 1 Tishrei, AM 1 (molad-based epoch used by the arithmetic calendar). */
    private const EPOCH_JDN = 347998;

    /**
     * Month names indexed by canonical slot 0..12: Tishrei ... Shevat, Adar I,
     * Adar II (= plain "Adar" in a regular year), Nisan ... Elul.
     */
    private const MONTHS = [
        'en' => ['Tishrei', 'Cheshvan', 'Kislev', 'Tevet', 'Shevat', 'Adar I', 'Adar II', 'Nisan', 'Iyar', 'Sivan', 'Tammuz', 'Av', 'Elul'],
        'he' => ['תשרי', 'חשוון', 'כסלו', 'טבת', 'שבט', 'אדר א׳', 'אדר ב׳', 'ניסן', 'אייר', 'סיוון', 'תמוז', 'אב', 'אלול'],
        'fa' => ['تشری', 'حشوان', 'کسلو', 'طوت', 'شواط', 'آدار اول', 'آدار دوم', 'نیسان', 'ایار', 'سیوان', 'تموز', 'آو', 'الول'],
    ];

    /** Name for slot 6 in a regular (non-leap) year. */
    private const PLAIN_ADAR = ['en' => 'Adar', 'he' => 'אדר', 'fa' => 'آدار'];

    private const WEEKDAYS = [ // Sunday first
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'he' => ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'],
        'fa' => ['یکشنبه', 'دوشنبه', "سه\u{200C}شنبه", 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'],
    ];

    private function __construct(DateTimeImmutable $gregorian)
    {
        $this->gregorian = $gregorian;
        [$this->year, $this->month, $this->day] = self::gregorianToHebrew(
            (int) $gregorian->format('Y'),
            (int) $gregorian->format('n'),
            (int) $gregorian->format('j'),
        );
        if ($this->year < self::MIN_YEAR || $this->year > self::MAX_YEAR) {
            throw InvalidDateException::because(
                ErrorCode::DateOutOfRange,
                sprintf('Date out of the supported Hebrew range (%d..%d): %d', self::MIN_YEAR, self::MAX_YEAR, $this->year),
            );
        }
        $this->hour   = (int) $gregorian->format('G');
        $this->minute = (int) $gregorian->format('i');
        $this->second = (int) $gregorian->format('s');
    }

    /* -----------------------------------------------------------------
     |  Factories
     | -----------------------------------------------------------------
     */

    private function withInstant(DateTimeImmutable $instant): static
    {
        return new self($instant);
    }

    public static function make(DateTimeInterface|CalendarDate|string|int|null $time = null, ?DateTimeZone $timezone = null): static
    {
        if ($time instanceof self) {
            return $time;
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
            $own = CalendarLimits::matchOwnFormat($normalized);
            if ($own !== null && $own[0] >= self::OWN_YEAR_MIN) {
                return self::create($own[0], $own[1], $own[2], $own[3], $own[4], $own[5], $timezone);
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
        return self::now($timezone)->startOfDay();
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
                "Invalid Hebrew date: {$year}/{$month}/{$day}",
            );
        }
        CalendarLimits::time($hour, $minute, $second);
        [$gy, $gm, $gd] = self::hebrewToGregorian($year, $month, $day);
        $dateString = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $hour, $minute, $second);

        return new self(CalendarLimits::parseGregorian($dateString, $timezone));
    }

    /* -----------------------------------------------------------------
     |  Getters
     | -----------------------------------------------------------------
     */

    /** Sunday-first: 0 = Sunday ... 6 = Saturday (Jalali is Saturday-first). */
    public function getDayOfWeek(): int
    {
        return (int) $this->gregorian->format('w');
    }

    /* -----------------------------------------------------------------
     |  Formatting
     | -----------------------------------------------------------------
     */

    /**
     * Tokens: Y y m n d j H G i s, F/M (month name), t (days in month),
     * L (1 if leap year), l (weekday name), w (weekday, 0 = Sunday),
     * N (ISO weekday 1 = Monday .. 7 = Sunday), z (zero-based day of the Hebrew
     * year), a A g h (12-hour clock; a/A are "am"/"pm" and "AM"/"PM"), S (always
     * empty), W (ISO week of the underlying Gregorian instant), c
     * (`Y-m-d\TH:i:sP` with the Hebrew date), r (RFC 2822 of the Gregorian
     * instant) and U e T P p O Z I u v (delegated to the underlying instant).
     * Prefix a character with a backslash to output it literally (a trailing
     * backslash is dropped). Patterns longer than {@see self::MAX_FORMAT_LENGTH}
     * bytes throw {@see InvalidDateException} (ErrorCode::InputTooLong).
     *
     * @param string $locale 'en' | 'he' | 'fa' (names; unknown falls back to 'en')
     */
    public function format(string $format = 'Y/m/d H:i:s', string $locale = 'en'): string
    {
        self::assertFormatLength($format);
        $out = '';
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $c = $format[$i];
            if ($c === '\\') {
                $i++;
                $out .= $i < $len ? $format[$i] : '';
                continue;
            }
            $out .= match ($c) {
                'Y' => sprintf('%04d', $this->year),
                'y' => sprintf('%02d', $this->year % 100),
                'm' => sprintf('%02d', $this->month),
                'n' => (string) $this->month,
                'd' => sprintf('%02d', $this->day),
                'j' => (string) $this->day,
                'H' => sprintf('%02d', $this->hour),
                'G' => (string) $this->hour,
                'i' => sprintf('%02d', $this->minute),
                's' => sprintf('%02d', $this->second),
                'F', 'M' => self::monthName($this->year, $this->month, $locale),
                'N' => $this->gregorian->format('N'),
                'z' => (string) $this->dayOfYear(),
                'a' => $this->hour < 12 ? 'am' : 'pm',
                'A' => $this->hour < 12 ? 'AM' : 'PM',
                'g' => (string) ($this->hour % 12 === 0 ? 12 : $this->hour % 12),
                'h' => sprintf('%02d', $this->hour % 12 === 0 ? 12 : $this->hour % 12),
                'S' => '',
                'W' => $this->gregorian->format('W'),
                'c' => sprintf('%04d-%02d-%02dT%02d:%02d:%02d', $this->year, $this->month, $this->day, $this->hour, $this->minute, $this->second)
                    .$this->gregorian->format('P'),
                'r' => $this->gregorian->format('r'),
                'U', 'e', 'T', 'P', 'p', 'O', 'Z', 'I', 'u', 'v' => $this->gregorian->format($c),
                't' => (string) self::daysInMonth($this->year, $this->month),
                'L' => self::isLeapYear($this->year) ? '1' : '0',
                'w' => (string) $this->getDayOfWeek(),
                'l' => (self::WEEKDAYS[$locale] ?? self::WEEKDAYS['en'])[$this->getDayOfWeek()],
                default => $c,
            };
        }

        return $out;
    }

    /** Zero-based day of the Hebrew year. */
    private function dayOfYear(): int
    {
        $n = $this->day - 1;
        for ($m = 1; $m < $this->month; $m++) {
            $n += self::daysInMonth($this->year, $m);
        }

        return $n;
    }

    /** Name of an ordinal month (see class docblock) in the given year. */
    public static function monthName(int $year, int $month, string $locale = 'en'): string
    {
        $slot = self::slot($year, $month);
        $locale = isset(self::MONTHS[$locale]) ? $locale : 'en';
        if ($slot === 6 && ! self::isLeapYear($year)) {
            return self::PLAIN_ADAR[$locale];
        }

        return self::MONTHS[$locale][$slot];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /* -----------------------------------------------------------------
     |  Modification (immutable)
     | -----------------------------------------------------------------
     */

    /**
     * Moves by ordinal months (leap Adar I/II counted separately); the day is
     * clamped to the target month's length. Bounded work: the target is
     * located through whole 19-year Metonic cycles (235 months each) plus at
     * most 19 yearly steps, independent of $months.
     *
     * @throws InvalidDateException when the result leaves MIN_YEAR..MAX_YEAR
     */
    public function addMonths(int $months): static
    {
        // A year has 12 or 13 months; this bound is far beyond MIN_YEAR..MAX_YEAR.
        CalendarLimits::delta($months, 13 * (self::MAX_YEAR - self::MIN_YEAR + 2), 'months');

        $target = $this->monthIndex() + $months;
        if ($target < 0) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, 'Hebrew year out of the supported range.');
        }

        $y = intdiv($target, 235) * 19 + 1;
        while (self::monthsBefore($y + 1) <= $target) {
            $y++;
        }

        return $this->rebuild($y, $target - self::monthsBefore($y) + 1);
    }

    /**
     * Same month in the target year. Adar maps to Adar II when the target is a
     * leap year; Adar I / Adar II map to Adar when the target is a regular year.
     */
    public function addYears(int $years): static
    {
        CalendarLimits::delta($years, self::MAX_YEAR - self::MIN_YEAR + 2, 'years');

        $y = $this->year + $years;
        if ($y < self::MIN_YEAR || $y > self::MAX_YEAR) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Hebrew year out of the supported range: {$y}");
        }
        $m = $this->month;
        $fromLeap = self::isLeapYear($this->year);
        $toLeap = self::isLeapYear($y);
        if ($fromLeap && ! $toLeap) {
            $m = $m <= 6 ? $m : ($m === 7 ? 6 : $m - 1);
        } elseif (! $fromLeap && $toLeap) {
            $m = $m <= 5 ? $m : $m + 1;
        }

        return $this->rebuild($y, $m);
    }

    public function startOfDay(): static
    {
        return self::create($this->year, $this->month, $this->day, 0, 0, 0, $this->getTimezone());
    }

    public function endOfDay(): static
    {
        return self::create($this->year, $this->month, $this->day, 23, 59, 59, $this->getTimezone());
    }

    public function startOfMonth(): static
    {
        return self::create($this->year, $this->month, 1, 0, 0, 0, $this->getTimezone());
    }

    public function endOfMonth(): static
    {
        return self::create($this->year, $this->month, self::daysInMonth($this->year, $this->month), 23, 59, 59, $this->getTimezone());
    }

    public function startOfYear(): static
    {
        return self::create($this->year, 1, 1, 0, 0, 0, $this->getTimezone());
    }

    public function endOfYear(): static
    {
        $m = self::monthsInYear($this->year);

        return self::create($this->year, $m, self::daysInMonth($this->year, $m), 23, 59, 59, $this->getTimezone());
    }

    private function rebuild(int $year, int $month): static
    {
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Hebrew year out of the supported range: {$year}");
        }
        $day = min($this->day, self::daysInMonth($year, $month));

        return self::create($year, $month, $day, $this->hour, $this->minute, $this->second, $this->getTimezone());
    }

    /* -----------------------------------------------------------------
     |  Difference
     | -----------------------------------------------------------------
     */

    /** Months elapsed before 1 Tishrei of $year (Metonic: 235 months per 19 years). */
    private static function monthsBefore(int $year): int
    {
        return intdiv(235 * $year - 234, 19);
    }

    /** Months elapsed since the epoch (Adar I and Adar II count separately). */
    private function monthIndex(): int
    {
        return self::monthsBefore($this->year) + $this->month - 1;
    }

    private function coerce(CalendarDate|DateTimeInterface $other, ?DateTimeZone $tz = null): self
    {
        return self::make(self::instantOf($other), $tz);
    }

    /**
     * Whole Hebrew months between two instants (Adar I / Adar II count as
     * separate months; day and time-of-day count).
     */
    public function diffInMonths(CalendarDate|DateTimeInterface $other, bool $absolute = true): int
    {
        $other = $this->coerce($other, $this->getTimezone());

        [$lo, $hi] = $this->getTimestamp() <= $other->getTimestamp() ? [$this, $other] : [$other, $this];

        $months = $hi->monthIndex() - $lo->monthIndex();
        if ([$hi->day, $hi->hour, $hi->minute, $hi->second] < [$lo->day, $lo->hour, $lo->minute, $lo->second]) {
            $months--;
        }

        return $absolute || $lo === $other ? $months : -$months;
    }

    /**
     * Whole Hebrew years between two instants (a year is 12 or 13 months, so
     * this is anniversary-based rather than months / 12).
     */
    public function diffInYears(CalendarDate|DateTimeInterface $other, bool $absolute = true): int
    {
        $other = $this->coerce($other, $this->getTimezone());

        [$lo, $hi] = $this->getTimestamp() <= $other->getTimestamp() ? [$this, $other] : [$other, $this];

        $years = $hi->year - $lo->year;
        if ($years > 0 && $lo->addYears($years)->getTimestamp() > $hi->getTimestamp()) {
            $years--;
        }

        return $absolute || $lo === $other ? $years : -$years;
    }

    /* -----------------------------------------------------------------
     |  Calendar arithmetic
     | -----------------------------------------------------------------
     */

    public static function isValid(int $year, int $month, int $day): bool
    {
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR || $month < 1 || $month > self::monthsInYear($year) || $day < 1) {
            return false;
        }

        return $day <= self::daysInMonth($year, $month);
    }

    /** Leap years (Adar I added) follow the 19-year Metonic cycle: 3, 6, 8, 11, 14, 17, 19. */
    public static function isLeapYear(int $year): bool
    {
        return (7 * ((($year % 19) + 19) % 19) + 1) % 19 < 7; // depends on year mod 19 only
    }

    public static function monthsInYear(int $year): int
    {
        return self::isLeapYear($year) ? 13 : 12;
    }

    /** 353, 354, 355 (regular) or 383, 384, 385 (leap). */
    public static function daysInYear(int $year): int
    {
        self::assertArithmeticYear($year);

        return self::newYearJdn($year + 1) - self::newYearJdn($year);
    }

    public static function daysInMonth(int $year, int $month): int
    {
        self::assertArithmeticYear($year);
        if ($month < 1 || $month > self::monthsInYear($year)) {
            throw new InvalidDateException("Invalid Hebrew month: {$month}");
        }
        $len = self::daysInYear($year) % 10; // 3 deficient, 4 regular, 5 complete

        return match (self::slot($year, $month)) {
            0, 4, 5, 7, 9, 11 => 30,
            1 => $len === 5 ? 30 : 29,
            2 => $len === 3 ? 29 : 30,
            default => 29, // Tevet, Adar II / Adar, Iyar, Tammuz, Elul
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int} [hebrewYear, ordinalMonth, day]
     */
    public static function gregorianToHebrew(int $gy, int $gm, int $gd): array
    {
        CalendarLimits::gregorianDate($gy, $gm, $gd);
        $jdn = Jdn::fromGregorian($gy, $gm, $gd);
        // Unreachable: Gregorian year 1 (the minimum) is already after the Hebrew epoch.
        // @codeCoverageIgnoreStart
        if ($jdn < self::EPOCH_JDN) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Date precedes the Hebrew calendar epoch: {$gy}-{$gm}-{$gd}");
        }
        // @codeCoverageIgnoreEnd

        $y = intdiv(($jdn - self::EPOCH_JDN) * 98496, 35975351) + 1;
        while (self::newYearJdn($y) > $jdn) {
            $y--;
        }
        while (self::newYearJdn($y + 1) <= $jdn) {
            $y++;
        }

        $offset = $jdn - self::newYearJdn($y);
        $months = self::monthsInYear($y);
        for ($m = 1; $m <= $months; $m++) {
            $len = self::daysInMonth($y, $m);
            if ($offset < $len) {
                return [$y, $m, $offset + 1];
            }
            $offset -= $len;
        }
        throw new InvalidDateException('Hebrew calendar arithmetic inconsistency'); // @codeCoverageIgnore
    }

    /**
     * @return array{0: int, 1: int, 2: int} [gregorianYear, month, day]
     */
    public static function hebrewToGregorian(int $hy, int $hm, int $hd): array
    {
        if (! self::isValid($hy, $hm, $hd)) {
            throw new InvalidDateException("Invalid Hebrew date: {$hy}/{$hm}/{$hd}");
        }
        $jdn = self::newYearJdn($hy) + $hd - 1;
        for ($m = 1; $m < $hm; $m++) {
            $jdn += self::daysInMonth($hy, $m);
        }

        return Jdn::toGregorian($jdn);
    }

    private static function assertArithmeticYear(int $year): void
    {
        if ($year < 1 || $year > self::MAX_YEAR) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Hebrew year out of the supported range: {$year}");
        }
    }

    /** Canonical month slot 0..12 (5 = Adar I, 6 = Adar II / plain Adar). */
    private static function slot(int $year, int $month): int
    {
        if ($month < 1 || $month > self::monthsInYear($year)) {
            throw new InvalidDateException("Invalid Hebrew month: {$month}");
        }
        if (self::isLeapYear($year) || $month <= 5) {
            return $month - 1;
        }

        return $month; // regular year: Adar = slot 6, Nisan = slot 7, ...
    }

    /** Days from the molad of Tishrei epoch to the (undelayed-by-weekday) new year. */
    private static function elapsedDays(int $year): int
    {
        $months = intdiv(235 * $year - 234, 19);
        $parts = 12084 + 13753 * $months;
        $days = $months * 29 + intdiv($parts, 25920);
        if ((3 * ($days + 1)) % 7 < 3) {
            $days++;
        }

        return $days;
    }

    private static function newYearJdn(int $year): int
    {
        $ny0 = self::elapsedDays($year - 1);
        $ny1 = self::elapsedDays($year);
        $ny2 = self::elapsedDays($year + 1);
        $delay = 0;
        if ($ny2 - $ny1 === 356) {
            $delay = 2;
        } elseif ($ny1 - $ny0 === 382) {
            $delay = 1;
        }

        return self::EPOCH_JDN + $ny1 + $delay;
    }
}
