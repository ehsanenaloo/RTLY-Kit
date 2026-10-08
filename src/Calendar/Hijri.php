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
 * Immutable Hijri (Islamic) date-time class.
 *
 * Two rule sets are available via {@see HijriVariant}:
 *
 * - UmmAlQura (default): the Saudi civil calendar, driven by the month-length
 *   table in `resources/data/umm-al-qura.php` (loaded lazily, integrity-checked)
 *   for AH 1300-1500 (1882-11-12 .. 2077-11-16 CE).
 *   The table was generated from the ICU/CLDR "islamic-umalqura" calendar
 *   data and spot-checked against known anchors (1 Ramadan 1446 =
 *   2025-03-01, 1 Muharram 1447 = 2025-06-26, 1 Shawwal 1445 = 2024-04-10).
 *   Every month start from AH 1318 to AH 1500 was compared with the official
 *   KACST calendar (ummulqura.org.sa) on 2026-10-08 with no differences. AH
 *   1300-1317 could not be checked against that source and are NOT verified.
 *   Outside AH 1300-1500 the tabular rules are used (the two rule sets may
 *   not join seamlessly there).
 * - Tabular: the arithmetic civil calendar (30-year cycle). Can differ from
 *   Umm al-Qura by 1-2 days.
 *
 * Neither variant reproduces moon-sighting calendars (e.g. Iran, Morocco),
 * which can differ by a day. Day boundaries follow civil midnight, not sunset.
 *
 * Table coverage: {@see self::hasUmmAlQuraData()} is true for AH 1300-1500
 * only. create()/make() outside it do NOT throw; they extrapolate with the
 * tabular rules. {@see self::usesUmmAlQuraTable()} tells whether an instance
 * is backed by the real table or by tabular extrapolation.
 *
 * Supported range: Hijri years 1 .. {@see self::MAX_YEAR} (the dates that map to
 * Gregorian years 622..9999); anything outside throws
 * {@see InvalidDateException}, as do absurd add*()/sub*() deltas.
 *
 * String parsing in {@see self::make()}: Persian/Arabic digits are normalised
 * first; `Y/m/d` or `Y-m-d` (3-4 digit year, optional ` H:i[:s]`) with a year
 * below 1700 is read as a HIJRI date in the chosen variant (`1446/09/01`);
 * every other string (including years of 1700 or later) is parsed as
 * Gregorian/free-form text. Invalid Hijri dates and empty/whitespace strings
 * throw {@see InvalidDateException}; `null` means "now".
 *
 * Weekday numbering: `getDayOfWeek()` / `w` are Sunday-first (0 = Sunday ..
 * 6 = Saturday), unlike {@see Jalali::getDayOfWeek()} (0 = Saturday).
 */
final class Hijri implements CalendarDate
{
    use CalendarDateTrait;

    /** Smallest supported Hijri year (begins 622-07-19 CE, proleptic Gregorian). */
    public const MIN_YEAR = 1;

    /** Longest accepted format() pattern, in bytes. */
    public const MAX_FORMAT_LENGTH = 256;

    /** Largest supported Hijri year (ends 9999-10-01 CE). */
    public const MAX_YEAR = 9665;

    private const OWN_YEAR_LIMIT = 1700;

    private const TABULAR_EPOCH = 1948440; // JDN of 1 Muharram 1 AH (civil)

    private const MONTHS = [
        'ar' => ['محرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة', 'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة'],
        'fa' => ['محرم', 'صفر', "ربیع\u{200C}الاول", "ربیع\u{200C}الثانی", "جمادی\u{200C}الاول", "جمادی\u{200C}الثانی", 'رجب', 'شعبان', 'رمضان', 'شوال', 'ذیقعده', 'ذیحجه'],
        'en' => ['Muharram', 'Safar', "Rabi' al-awwal", "Rabi' al-thani", 'Jumada al-awwal', 'Jumada al-thani', 'Rajab', "Sha'ban", 'Ramadan', 'Shawwal', "Dhu al-Qi'dah", 'Dhu al-Hijjah'],
    ];

    private const WEEKDAYS = [ // Sunday first
        'ar' => ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
        'fa' => ['یکشنبه', 'دوشنبه', "سه\u{200C}شنبه", 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'],
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    ];

    private readonly HijriVariant $variant;

    private function __construct(DateTimeImmutable $gregorian, HijriVariant $variant)
    {
        $this->gregorian = $gregorian;
        $this->variant = $variant;
        [$this->year, $this->month, $this->day] = self::gregorianToHijri(
            (int) $gregorian->format('Y'),
            (int) $gregorian->format('n'),
            (int) $gregorian->format('j'),
            $variant,
        );
        if ($this->year < self::MIN_YEAR || $this->year > self::MAX_YEAR) {
            throw InvalidDateException::because(
                ErrorCode::DateOutOfRange,
                sprintf('Date out of the supported Hijri range (%d..%d): %d', self::MIN_YEAR, self::MAX_YEAR, $this->year),
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
        return new self($instant, $this->variant);
    }

    public static function make(
        DateTimeInterface|CalendarDate|string|int|null $time = null,
        ?DateTimeZone $timezone = null,
        HijriVariant $variant = HijriVariant::UmmAlQura,
    ): static {
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

            return new self($immutable, $variant);
        }
        if (is_int($time)) {
            $tz = $timezone ?? new DateTimeZone(date_default_timezone_get());

            return new self(CalendarLimits::fromTimestamp($time, $tz), $variant);
        }
        if (is_string($time)) {
            $normalized = CalendarLimits::normalize($time);
            $own = CalendarLimits::matchOwnFormat($normalized);
            if ($own !== null && $own[0] < self::OWN_YEAR_LIMIT) {
                return self::create($own[0], $own[1], $own[2], $own[3], $own[4], $own[5], $timezone, $variant);
            }

            return new self(CalendarLimits::parseGregorian($normalized, $timezone), $variant);
        }

        return new self(new DateTimeImmutable('now', $timezone), $variant);
    }

    public static function now(?DateTimeZone $timezone = null, HijriVariant $variant = HijriVariant::UmmAlQura): static
    {
        return self::make(null, $timezone, $variant);
    }

    public static function today(?DateTimeZone $timezone = null, HijriVariant $variant = HijriVariant::UmmAlQura): static
    {
        return self::now($timezone, $variant)->startOfDay();
    }

    public static function create(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?DateTimeZone $timezone = null,
        HijriVariant $variant = HijriVariant::UmmAlQura,
    ): static {
        if (! self::isValid($year, $month, $day, $variant)) {
            throw InvalidDateException::because(
                $year < self::MIN_YEAR || $year > self::MAX_YEAR ? ErrorCode::DateOutOfRange : ErrorCode::InvalidDate,
                "Invalid Hijri date: {$year}/{$month}/{$day}",
            );
        }
        CalendarLimits::time($hour, $minute, $second);
        [$gy, $gm, $gd] = self::hijriToGregorian($year, $month, $day, $variant);
        $dateString = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $hour, $minute, $second);

        return new self(CalendarLimits::parseGregorian($dateString, $timezone), $variant);
    }

    /* -----------------------------------------------------------------
     |  Getters
     | -----------------------------------------------------------------
     */

    public function getVariant(): HijriVariant
    {
        return $this->variant;
    }
    /** Sunday-first: 0 = Sunday ... 6 = Saturday (Jalali is Saturday-first). */
    public function getDayOfWeek(): int
    {
        return (int) $this->gregorian->format('w');
    }

    /**
     * True when this instance's date is resolved from the embedded Umm al-Qura
     * table (variant UmmAlQura and AH 1300-1500); false for the Tabular variant
     * or when the date lies outside the table and is tabular-extrapolated.
     */
    public function usesUmmAlQuraTable(): bool
    {
        return $this->variant === HijriVariant::UmmAlQura && self::inUqRange($this->year);
    }

    /* -----------------------------------------------------------------
     |  Formatting
     | -----------------------------------------------------------------
     */

    /**
     * Tokens: Y y m n d j H G i s, F/M (month name), t (days in month),
     * L (1 if leap year), l (weekday name), w (weekday, 0 = Sunday),
     * N (ISO weekday 1 = Monday .. 7 = Sunday), z (zero-based day of the Hijri
     * year), a A g h (12-hour clock; a/A are locale-aware), S (always empty:
     * no ordinal suffix), W (ISO week of the underlying Gregorian instant),
     * c (`Y-m-d\TH:i:sP` with the Hijri date), r (RFC 2822 of the Gregorian
     * instant) and U e T P p O Z I u v (delegated to the underlying instant).
     * Prefix a character with a backslash to output it literally (a trailing
     * backslash is dropped). Patterns longer than {@see self::MAX_FORMAT_LENGTH}
     * bytes throw {@see InvalidDateException} (ErrorCode::InputTooLong).
     *
     * @param string $locale 'ar' | 'fa' | 'en' (names; unknown falls back to 'en')
     * @param string $digits 'latin' | 'persian' | 'arabic'
     */
    public function format(string $format = 'Y/m/d H:i:s', string $locale = 'ar', string $digits = 'latin'): string
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
                'F', 'M' => self::monthName($this->month, $locale),
                'N' => $this->gregorian->format('N'),
                'z' => (string) $this->dayOfYear(),
                'a' => self::meridiem($this->hour, $locale, false),
                'A' => self::meridiem($this->hour, $locale, true),
                'g' => (string) ($this->hour % 12 === 0 ? 12 : $this->hour % 12),
                'h' => sprintf('%02d', $this->hour % 12 === 0 ? 12 : $this->hour % 12),
                'S' => '',
                'W' => $this->gregorian->format('W'),
                'c' => sprintf('%04d-%02d-%02dT%02d:%02d:%02d', $this->year, $this->month, $this->day, $this->hour, $this->minute, $this->second)
                    .$this->gregorian->format('P'),
                'r' => $this->gregorian->format('r'),
                'U', 'e', 'T', 'P', 'p', 'O', 'Z', 'I', 'u', 'v' => $this->gregorian->format($c),
                't' => (string) self::daysInMonth($this->year, $this->month, $this->variant),
                'L' => self::isLeapYear($this->year, $this->variant) ? '1' : '0',
                'w' => (string) $this->getDayOfWeek(),
                'l' => (self::WEEKDAYS[$locale] ?? self::WEEKDAYS['en'])[$this->getDayOfWeek()],
                default => $c,
            };
        }

        return match ($digits) {
            'persian' => Digits::toPersian($out),
            'arabic' => Digits::toArabic($out),
            default => $out,
        };
    }

    /** Zero-based day of the Hijri year. */
    private function dayOfYear(): int
    {
        $n = $this->day - 1;
        for ($m = 1; $m < $this->month; $m++) {
            $n += self::daysInMonth($this->year, $m, $this->variant);
        }

        return $n;
    }

    private static function meridiem(int $hour, string $locale, bool $upper): string
    {
        $pm = $hour >= 12;
        if ($locale === 'ar') {
            return $pm ? 'م' : 'ص';
        }
        if ($locale === 'fa') {
            return $pm ? 'ب.ظ' : 'ق.ظ';
        }
        $s = $pm ? 'pm' : 'am';

        return $upper ? strtoupper($s) : $s;
    }

    public static function monthName(int $month, string $locale = 'ar'): string
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidDateException("Invalid Hijri month: {$month}");
        }

        return (self::MONTHS[$locale] ?? self::MONTHS['en'])[$month - 1];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /* -----------------------------------------------------------------
     |  Modification (immutable)
     | -----------------------------------------------------------------
     */

    /** The day is clamped to the target month's length (30 Muharram + 1 month = 29 Safar). */
    public function addMonths(int $months): static
    {
        CalendarLimits::delta($months, (self::MAX_YEAR - self::MIN_YEAR + 2) * 12, 'months');

        $total = $this->year * 12 + ($this->month - 1) + $months;
        $year = intdiv($total, 12);
        $month = $total - $year * 12;
        if ($month < 0) {
            $year--;
            $month += 12;
        }
        $month++;
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Hijri year out of the supported range: {$year}");
        }
        $day = min($this->day, self::daysInMonth($year, $month, $this->variant));

        return self::create($year, $month, $day, $this->hour, $this->minute, $this->second, $this->getTimezone(), $this->variant);
    }

    public function addYears(int $years): static
    {
        CalendarLimits::delta($years, self::MAX_YEAR - self::MIN_YEAR + 2, 'years');

        return $this->addMonths($years * 12);
    }

    public function startOfDay(): static
    {
        return self::create($this->year, $this->month, $this->day, 0, 0, 0, $this->getTimezone(), $this->variant);
    }

    public function endOfDay(): static
    {
        return self::create($this->year, $this->month, $this->day, 23, 59, 59, $this->getTimezone(), $this->variant);
    }

    public function startOfMonth(): static
    {
        return self::create($this->year, $this->month, 1, 0, 0, 0, $this->getTimezone(), $this->variant);
    }

    public function endOfMonth(): static
    {
        $last = self::daysInMonth($this->year, $this->month, $this->variant);

        return self::create($this->year, $this->month, $last, 23, 59, 59, $this->getTimezone(), $this->variant);
    }

    public function startOfYear(): static
    {
        return self::create($this->year, 1, 1, 0, 0, 0, $this->getTimezone(), $this->variant);
    }

    public function endOfYear(): static
    {
        $last = self::daysInMonth($this->year, 12, $this->variant);

        return self::create($this->year, 12, $last, 23, 59, 59, $this->getTimezone(), $this->variant);
    }

    /* -----------------------------------------------------------------
     |  Difference
     | -----------------------------------------------------------------
     */

    private function monthIndex(): int
    {
        return $this->year * 12 + $this->month;
    }

    /** Re-express $other in this instance's variant (and optionally a timezone). */
    private function coerce(CalendarDate|DateTimeInterface $other, ?DateTimeZone $tz = null): self
    {
        return self::make(self::instantOf($other), $tz, $this->variant);
    }

    /**
     * Whole Hijri months between two instants (day and time-of-day count).
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
     * Whole Hijri years between two instants (month, day and time count).
     */
    public function diffInYears(CalendarDate|DateTimeInterface $other, bool $absolute = true): int
    {
        return intdiv($this->diffInMonths($other, $absolute), 12);
    }

    /* -----------------------------------------------------------------
     |  Calendar arithmetic
     | -----------------------------------------------------------------
     */

    public static function isValid(int $year, int $month, int $day, HijriVariant $variant = HijriVariant::UmmAlQura): bool
    {
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        return $day <= self::daysInMonth($year, $month, $variant);
    }

    public static function daysInMonth(int $year, int $month, HijriVariant $variant = HijriVariant::UmmAlQura): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidDateException("Invalid Hijri month: {$month}");
        }
        if ($variant === HijriVariant::UmmAlQura && self::inUqRange($year)) {
            return UmmAlQuraTable::default()->monthLength($year, $month);
        }
        if ($month === 12) {
            return self::tabularLeap($year) ? 30 : 29;
        }

        return $month % 2 === 1 ? 30 : 29;
    }

    public static function daysInYear(int $year, HijriVariant $variant = HijriVariant::UmmAlQura): int
    {
        $sum = 0;
        for ($m = 1; $m <= 12; $m++) {
            $sum += self::daysInMonth($year, $m, $variant);
        }

        return $sum;
    }

    /** A leap year has 355 days. */
    public static function isLeapYear(int $year, HijriVariant $variant = HijriVariant::UmmAlQura): bool
    {
        if ($variant === HijriVariant::UmmAlQura && self::inUqRange($year)) {
            return self::daysInYear($year, $variant) === 355;
        }

        return self::tabularLeap($year);
    }

    /** True if the embedded Umm al-Qura table covers this Hijri year (AH 1300-1500). */
    public static function hasUmmAlQuraData(int $year): bool
    {
        return self::inUqRange($year);
    }

    /**
     * @return array{0: int, 1: int, 2: int} [hijriYear, month, day]
     */
    public static function gregorianToHijri(int $gy, int $gm, int $gd, HijriVariant $variant = HijriVariant::UmmAlQura): array
    {
        CalendarLimits::gregorianDate($gy, $gm, $gd);
        $jdn = Jdn::fromGregorian($gy, $gm, $gd);

        if ($variant === HijriVariant::UmmAlQura) {
            $table = UmmAlQuraTable::default();
            if ($jdn >= $table->firstJdn() && $jdn < $table->endJdn()) {
                // Mean Hijri year is 354.37 days: start near the answer, then correct.
                $year = min($table->lastYear, $table->firstYear + intdiv(($jdn - $table->firstJdn()) * 100, 35437));
                // Defensive: the mean-year estimate does not overshoot for any table year.
                // @codeCoverageIgnoreStart
                while ($jdn < $table->yearStart($year)) {
                    $year--;
                }
                // @codeCoverageIgnoreEnd
                while ($jdn >= $table->yearStart($year + 1)) {
                    $year++;
                }
                $offset = $jdn - $table->yearStart($year);
                for ($m = 1; $m <= 12; $m++) {
                    $len = self::daysInMonth($year, $m, $variant);
                    if ($offset < $len) {
                        return [$year, $m, $offset + 1];
                    }
                    $offset -= $len;
                }
            }
        }

        return self::tabularFromJdn($jdn);
    }

    /**
     * @return array{0: int, 1: int, 2: int} [gregorianYear, month, day]
     */
    public static function hijriToGregorian(int $hy, int $hm, int $hd, HijriVariant $variant = HijriVariant::UmmAlQura): array
    {
        if ($hy < self::MIN_YEAR || $hy > self::MAX_YEAR || $hm < 1 || $hm > 12 || $hd < 1 || $hd > 30) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Hijri date out of the supported range: {$hy}/{$hm}/{$hd}");
        }

        if ($variant === HijriVariant::UmmAlQura && self::inUqRange($hy)) {
            $jdn = UmmAlQuraTable::default()->yearStart($hy) + $hd - 1;
            for ($m = 1; $m < $hm; $m++) {
                $jdn += self::daysInMonth($hy, $m, $variant);
            }

            return Jdn::toGregorian($jdn);
        }

        return Jdn::toGregorian(self::tabularToJdn($hy, $hm, $hd));
    }

    /**
     * Hijri years [first, last] of the Umm al-Qura data whose month starts were verified:
     * AH 1318-1500 were compared with the official KACST calendar on 2026-10-08 (0 differences).
     * AH 1300-1317 come from ICU/CLDR data and could not be confirmed against a KACST source;
     * {@see self::hasUmmAlQuraData()} still covers them.
     *
     * @return array{0: int, 1: int}
     */
    public static function ummAlQuraVerifiedRange(): array
    {
        return [1318, 1500];
    }

    /**
     * True when the year lies in {@see self::ummAlQuraVerifiedRange()} (AH 1318-1500, compared
     * with the official KACST calendar). False for AH 1300-1317 (ICU/CLDR data, unconfirmed)
     * and for every year outside the embedded table.
     */
    public static function isUmmAlQuraVerified(int $hijriYear): bool
    {
        [$first, $last] = self::ummAlQuraVerifiedRange();

        return $hijriYear >= $first && $hijriYear <= $last && self::inUqRange($hijriYear);
    }

    private static function inUqRange(int $year): bool
    {
        return UmmAlQuraTable::default()->covers($year);
    }

    private static function tabularLeap(int $year): bool
    {
        return (14 + 11 * ((($year % 30) + 30) % 30)) % 30 < 11; // depends on year mod 30 only
    }

    private static function tabularToJdn(int $y, int $m, int $d): int
    {
        return $d + intdiv(59 * ($m - 1) + 1, 2) + 354 * ($y - 1) + intdiv(3 + 11 * $y, 30) + self::TABULAR_EPOCH - 1;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function tabularFromJdn(int $jdn): array
    {
        $y = intdiv(30 * ($jdn - self::TABULAR_EPOCH) + 10646, 10631);
        $offset = $jdn - self::tabularToJdn($y, 1, 1);
        for ($m = 1; $m <= 12; $m++) {
            $len = self::daysInMonth($y, $m, HijriVariant::Tabular);
            if ($offset < $len) {
                return [$y, $m, $offset + 1];
            }
            $offset -= $len;
        }

        // Unreachable for consistent arithmetic.
        return [$y, 12, $offset + 1]; // @codeCoverageIgnore
    }
}
