<?php

declare(strict_types=1);

namespace RtlyKit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Number\Digits;

/**
 * Shared range guards and own-calendar string parsing for the calendar classes.
 *
 * Every supported date of every calendar maps to a Gregorian year in
 * 1..9999, so all values the classes produce can be represented by
 * DateTimeImmutable without wraparound.
 *
 * @internal Not part of the public API; may change or disappear in any release.
 *            Julian Day Number maths lives in {@see Jdn}.
 */
final class CalendarLimits
{
    public const MIN_GREGORIAN_YEAR = 1;

    public const MAX_GREGORIAN_YEAR = 9999;

    /** Days spanned by Gregorian years 1..9999, rounded up. */
    private const MAX_DAYS = 3_700_000;

    /** Unix timestamps comfortably outside Gregorian years 1..9999 (time zone slack included). */
    private const MIN_TIMESTAMP = -62_135_769_600;

    private const MAX_TIMESTAMP = 253_402_387_200;

    /** Largest magnitude accepted for a day/hour/minute/second delta. */
    public const DELTA_DAYS = self::MAX_DAYS;

    public const DELTA_HOURS = self::MAX_DAYS * 24;

    public const DELTA_MINUTES = self::MAX_DAYS * 1440;

    public const DELTA_SECONDS = self::MAX_DAYS * 86400;

    /** @codeCoverageIgnore Private constructor only prevents instantiation. */
    private function __construct() {}

    /**
     * @throws InvalidDateException when |$value| exceeds $max
     */
    public static function delta(int $value, int $max, string $unit): int
    {
        if ($value > $max || $value < -$max) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Cannot shift a date by {$value} {$unit}: out of the supported range.");
        }

        return $value;
    }

    /**
     * Negate safely (PHP_INT_MIN has no positive counterpart).
     *
     * @throws InvalidDateException
     */
    public static function negate(int $value): int
    {
        if ($value === PHP_INT_MIN) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, 'Value out of the supported range.');
        }

        return -$value;
    }

    /**
     * @throws InvalidDateException
     */
    public static function gregorianDate(int $y, int $m, int $d): void
    {
        if ($y < self::MIN_GREGORIAN_YEAR || $y > self::MAX_GREGORIAN_YEAR || $m < 1 || $m > 12 || $d < 1 || $d > 31) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Gregorian date out of the supported range (years 1-9999): {$y}-{$m}-{$d}");
        }
    }

    /**
     * @throws InvalidDateException
     */
    public static function time(int $hour, int $minute, int $second): void
    {
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            throw new InvalidDateException("Invalid time: {$hour}:{$minute}:{$second}");
        }
    }

    /**
     * Instant for a Unix timestamp; rejects timestamps outside Gregorian years 1..9999.
     *
     * @throws InvalidDateException
     */
    public static function fromTimestamp(int $timestamp, DateTimeZone $tz): DateTimeImmutable
    {
        if ($timestamp < self::MIN_TIMESTAMP || $timestamp > self::MAX_TIMESTAMP) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Timestamp out of the supported range: {$timestamp}");
        }

        return (new DateTimeImmutable('@0'))->setTimezone($tz)->setTimestamp($timestamp);
    }

    /**
     * Parse a Gregorian / free-form string with DateTimeImmutable.
     *
     * @throws InvalidDateException
     */
    public static function parseGregorian(string $time, ?DateTimeZone $timezone): DateTimeImmutable
    {
        if (str_contains($time, "\0")) {
            throw new InvalidDateException('Unable to parse date: the text contains a NUL byte.');
        }

        try {
            $instant = new DateTimeImmutable($time, $timezone);
        } catch (\Exception $e) {
            throw new InvalidDateException("Unable to parse date: {$time}", previous: $e);
        }

        $parts = date_parse($time);

        // PHP rolls an impossible day over (2024-02-30 becomes 2024-03-01); create() is strict, so is this.
        if (is_array($parts['warnings']) && in_array('The parsed date was invalid', $parts['warnings'], true)) {
            throw InvalidDateException::because(ErrorCode::InvalidDate, "Unable to parse date: {$time} is not a real date.");
        }

        // A bare zone token ("x", "z", "EST") parses as "now in that zone"; it is not a date.
        if (
            ($parts['zone_type'] ?? 0) !== 0
            && $parts['year'] === false && $parts['month'] === false && $parts['day'] === false
            && $parts['hour'] === false && ! isset($parts['relative'])
        ) {
            throw new InvalidDateException("Unable to parse date: {$time}");
        }

        return $instant;
    }

    /**
     * Tokens whose PHP meaning is Gregorian (month/day names, day of the
     * year, 2-digit years, ordinal suffixes) or that carry a zone or a
     * timestamp; reading them as a calendar value would give a wrong date silently.
     */
    private const FORMAT_TOKENS_REJECTED = 'UzeTPOpuvyFMDlS';

    /**
     * Shared reader of the createFromFormat() methods: Persian/Arabic digits are
     * normalised, year, month and day are returned as the calendar's own values
     * (the caller validates them with create()).
     *
     * @return int|array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int} a Unix timestamp for the format `U` alone, otherwise [y, m, d, h, i, s]
     *
     * @throws InvalidDateException
     */
    public static function readFormat(string $format, string $time): int|array
    {
        if (str_contains($format, "\0") || str_contains($time, "\0")) {
            throw new InvalidDateException('Unable to parse the date: the format or the text contains a NUL byte.');
        }

        $english = trim(Digits::toEnglish($time));
        if (trim($format) === 'U') {
            if (preg_match('/^-?\d{1,19}$/D', $english) !== 1) {
                throw new InvalidDateException("Unable to parse '{$time}' with format '{$format}'");
            }

            return (int) $english;
        }

        self::assertFormatTokens($format);

        try {
            $parsed = date_parse_from_format($format, Digits::toEnglish($time));
        } catch (\ValueError $e) {
            throw new InvalidDateException("Unable to parse '{$time}' with format '{$format}'", previous: $e);
        }

        // "The parsed date was invalid" is raised for Gregorian-invalid days
        // (e.g. 02/31) that can be valid in the calendar; create() validates properly.
        $warnings = array_filter(
            $parsed['warnings'],
            static fn (string $w): bool => $w !== 'The parsed date was invalid',
        );

        if ($parsed['error_count'] > 0 || $warnings !== []
            || $parsed['year'] === false || $parsed['month'] === false || $parsed['day'] === false) {
            throw new InvalidDateException("Unable to parse '{$time}' with format '{$format}'");
        }

        return [
            $parsed['year'],
            $parsed['month'],
            $parsed['day'],
            is_int($parsed['hour']) ? $parsed['hour'] : 0,
            is_int($parsed['minute']) ? $parsed['minute'] : 0,
            is_int($parsed['second']) ? $parsed['second'] : 0,
        ];
    }

    /**
     * @throws InvalidDateException naming the first token that createFromFormat() does not read
     */
    private static function assertFormatTokens(string $format): void
    {
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $c = $format[$i];
            if ($c === '\\') {
                $i++;

                continue;
            }
            if (str_contains(self::FORMAT_TOKENS_REJECTED, $c)) {
                throw new InvalidDateException(
                    "createFromFormat() does not support the '{$c}' token (it has a Gregorian or time-zone meaning). Use Y, m, d, H, i, s; pass a timezone argument; or use the format 'U' alone for a Unix timestamp.",
                );
            }
        }
    }

    /**
     * Normalise a string input for make(): digits to English, no-break space,
     * ZWNJ and the LRM/RLM marks treated like spaces, then trimmed.
     *
     * @throws InvalidDateException for empty / whitespace-only input or a NUL byte
     */
    public static function normalize(string $time): string
    {
        if (str_contains($time, "\0")) {
            throw new InvalidDateException('Unable to parse date: the text contains a NUL byte.');
        }

        $english = Digits::toEnglish($time);
        $normalized = trim(preg_replace('/[\x{00A0}\x{200C}\x{200E}\x{200F}]/u', ' ', $english) ?? $english);
        if ($normalized === '') {
            throw new InvalidDateException('Unable to parse an empty date string.');
        }

        return $normalized;
    }

    /**
     * Read `Y/m/d[<sep>H:i[:s[.u]][zone]]` (slash or dash; the separator is a
     * space, `T` or `t`; the zone is `Z`, `+HH:MM`, `+HHMM` or `+HH`) when its
     * year belongs to the calling calendar.
     *
     * Returns null when the text is not own-calendar text (the caller then
     * parses it as Gregorian/free-form). A string that starts like a date of
     * the calling calendar but is not exactly that shape, a bare 3-8 digit
     * run (which PHP would read as a clock time), and a 5-digit year in a
     * calendar that has none all throw instead of silently turning into a
     * Gregorian value.
     *
     * @param \Closure(int): bool $isOwnYear true for years the calling calendar owns
     * @param bool $fiveDigitYears whether the calendar has years of 5 digits (Hebrew)
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: ?DateTimeZone}|null [y, m, d, h, i, s, microseconds, zone from the text]
     *
     * @throws InvalidDateException
     */
    public static function matchOwnFormat(string $normalized, \Closure $isOwnYear, bool $fiveDigitYears = false): ?array
    {
        if (preg_match('/^-\d{1,5}[\/-]\d{1,2}[\/-]\d{1,2}/', $normalized) === 1) {
            throw InvalidDateException::because(
                ErrorCode::InvalidDate,
                "Cannot read '{$normalized}' as a date: text dates have no negative years. Use create() or pass a DateTimeImmutable.",
            );
        }

        $exact = '/^(\d{3,5})([\/-])(\d{1,2})\2(\d{1,2})(?:(?:\s+|[Tt])(\d{1,2}):(\d{2})(?::(\d{2})(?:\.(\d{1,6}))?)?(Z|z|[+-]\d{2}(?::?\d{2})?)?)?$/D';
        if (preg_match($exact, $normalized, $m) === 1) {
            self::assertYearDigits($m[1], $fiveDigitYears);
            if (! $isOwnYear((int) $m[1])) {
                return null;
            }

            return [
                (int) $m[1], (int) $m[3], (int) $m[4],
                (int) ($m[5] ?? 0), (int) ($m[6] ?? 0), (int) ($m[7] ?? 0),
                (int) str_pad($m[8] ?? '', 6, '0'),
                self::designatorZone($m[9] ?? ''),
            ];
        }

        if (preg_match('/^(\d{3,5})[\/-]\d{1,2}(?:[\/-]\d{1,2})?/', $normalized, $p) === 1) {
            self::assertYearDigits($p[1], $fiveDigitYears);
            if ($isOwnYear((int) $p[1])) {
                throw InvalidDateException::because(
                    ErrorCode::InvalidDate,
                    "Cannot read '{$p[0]}...' as a date: it starts like a date of this calendar but is not exactly Y/m/d or Y/m/d H:i[:s[.u]]. Use that exact form, or pass a DateTimeImmutable.",
                );
            }

            return null;
        }

        if (preg_match('/^\d{3,8}$/D', $normalized) === 1) {
            // Eight digits that start with a year of another calendar are a compact Gregorian date (20240101).
            if (strlen($normalized) === 8 && ! $isOwnYear((int) substr($normalized, 0, 4))) {
                return null;
            }

            throw InvalidDateException::because(
                ErrorCode::InvalidDate,
                "Cannot read '{$normalized}' as a date: write it as Y/m/d, pass a Unix timestamp as an int, or pass a DateTimeImmutable.",
            );
        }

        return null;
    }

    /**
     * Zone for a trailing designator (`Z`, `+03:30`, `+0330`, `+03`); null when there is none.
     *
     * @throws InvalidDateException for an offset beyond +-14:00 or minutes above 59
     */
    private static function designatorZone(string $designator): ?DateTimeZone
    {
        if ($designator === '') {
            return null;
        }
        if ($designator === 'Z' || $designator === 'z') {
            return new DateTimeZone('UTC');
        }

        $digits = str_replace(':', '', substr($designator, 1));
        $hours = (int) substr($digits, 0, 2);
        $minutes = (int) substr($digits, 2, 2);
        if ($minutes > 59 || $hours * 60 + $minutes > 14 * 60) {
            throw new InvalidDateException("Invalid UTC offset: {$designator} (allowed range is -14:00 to +14:00)");
        }

        return new DateTimeZone(sprintf('%s%02d:%02d', $designator[0], $hours, $minutes));
    }

    private static function assertYearDigits(string $digits, bool $fiveDigitYears): void
    {
        if (strlen($digits) >= 5 && ! $fiveDigitYears) {
            throw InvalidDateException::because(ErrorCode::DateOutOfRange, "Year {$digits} is outside the supported range of this calendar.");
        }
    }
}
