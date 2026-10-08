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
        try {
            $instant = new DateTimeImmutable($time, $timezone);
        } catch (\Exception $e) {
            throw new InvalidDateException("Unable to parse date: {$time}", previous: $e);
        }

        // A bare zone token ("x", "z", "EST") parses as "now in that zone"; it is not a date.
        $parts = date_parse($time);
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
     * Normalise a string input for make(): digits to English, trimmed.
     *
     * @throws InvalidDateException for empty / whitespace-only input
     */
    public static function normalize(string $time): string
    {
        $normalized = trim(Digits::toEnglish($time));
        if ($normalized === '') {
            throw new InvalidDateException('Unable to parse an empty date string.');
        }

        return $normalized;
    }

    /**
     * Recognise `Y/m/d[ H:i[:s]]` (slash or dash, 3-4 digit year).
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int}|null [y, m, d, h, i, s]
     */
    public static function matchOwnFormat(string $normalized): ?array
    {
        $pattern = '/^(\d{3,4})([\/-])(\d{1,2})\2(\d{1,2})(?:(?:\s+|T)(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/';
        if (preg_match($pattern, $normalized, $m) !== 1) {
            return null;
        }

        return [(int) $m[1], (int) $m[3], (int) $m[4], (int) ($m[5] ?? 0), (int) ($m[6] ?? 0), (int) ($m[7] ?? 0)];
    }
}
