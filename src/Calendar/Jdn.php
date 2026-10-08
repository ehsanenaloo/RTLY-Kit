<?php

declare(strict_types=1);

namespace RtlyKit\Calendar;

/**
 * Julian Day Number conversions for the proleptic Gregorian calendar, shared by
 * every calendar class (single source of truth).
 *
 * @internal Not part of the public API.
 */
final class Jdn
{
    /** @codeCoverageIgnore Private constructor only prevents instantiation. */
    private function __construct() {}

    /**
     * Julian Day Number of a proleptic Gregorian date (Fliegel-Van Flandern,
     * public domain). Valid for years >= 1.
     */
    public static function fromGregorian(int $y, int $m, int $d): int
    {
        $a = intdiv(14 - $m, 12);
        $y2 = $y + 4800 - $a;
        $m2 = $m + 12 * $a - 3;

        return $d + intdiv(153 * $m2 + 2, 5) + 365 * $y2 + intdiv($y2, 4) - intdiv($y2, 100) + intdiv($y2, 400) - 32045;
    }

    /**
     * Inverse of {@see self::fromGregorian()} (Richards, public domain).
     *
     * @return array{0: int, 1: int, 2: int} [year, month, day]
     */
    public static function toGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        return [100 * $b + $d - 4800 + intdiv($m, 10), $m + 3 - 12 * intdiv($m, 10), $e - intdiv(153 * $m + 2, 5) + 1];
    }
}
