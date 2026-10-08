<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\CalendarLimits;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Exact edges of the timestamp window that maps onto Gregorian years 1..9999
 * (with two days of slack on each side for time zones).
 */
final class CalendarLimitsBoundaryTest extends TestCase
{
    private const MIN = -62_135_769_600; // 0000-12-30 00:00:00 UTC
    private const MAX = 253_402_387_200; // 10000-01-02 00:00:00 UTC

    /** @return array<string, array{int}> */
    public static function insideTheWindow(): array
    {
        return [
            'minimum' => [self::MIN],
            'minimum + 1' => [self::MIN + 1],
            'epoch' => [0],
            'maximum - 1' => [self::MAX - 1],
            'maximum' => [self::MAX],
        ];
    }

    #[DataProvider('insideTheWindow')]
    public function test_timestamps_inside_the_window_are_kept_exactly(int $timestamp): void
    {
        $instant = CalendarLimits::fromTimestamp($timestamp, new DateTimeZone('UTC'));

        self::assertSame($timestamp, $instant->getTimestamp());
    }

    /** @return array<string, array{int}> */
    public static function outsideTheWindow(): array
    {
        return [
            'minimum - 1' => [self::MIN - 1],
            'far below' => [PHP_INT_MIN],
            'maximum + 1' => [self::MAX + 1],
            'far above' => [PHP_INT_MAX],
        ];
    }

    #[DataProvider('outsideTheWindow')]
    public function test_timestamps_outside_the_window_are_rejected(int $timestamp): void
    {
        try {
            CalendarLimits::fromTimestamp($timestamp, new DateTimeZone('UTC'));
            self::fail('InvalidDateException expected');
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
            self::assertSame("Timestamp out of the supported range: {$timestamp}", $e->getMessage());
        }
    }

    public function test_the_window_covers_gregorian_years_1_to_9999(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('0001-01-01', CalendarLimits::fromTimestamp(-62_135_596_800, $utc)->format('Y-m-d'));
        self::assertSame('9999-12-31', CalendarLimits::fromTimestamp(253_402_214_400, $utc)->format('Y-m-d'));
        self::assertSame('9999-12-31 23:59:59', CalendarLimits::fromTimestamp(253_402_300_799, $utc)->format('Y-m-d H:i:s'));
    }

    public function test_time_and_gregorian_date_guards_accept_their_extremes(): void
    {
        CalendarLimits::time(0, 0, 0);
        CalendarLimits::time(23, 59, 59);
        CalendarLimits::gregorianDate(1, 1, 1);
        CalendarLimits::gregorianDate(9999, 12, 31);

        foreach ([[-1, 0, 0], [24, 0, 0], [0, -1, 0], [0, 60, 0], [0, 0, -1], [0, 0, 60]] as [$h, $i, $s]) {
            try {
                CalendarLimits::time($h, $i, $s);
                self::fail("time({$h}, {$i}, {$s}) must be rejected");
            } catch (InvalidDateException $e) {
                self::assertSame("Invalid time: {$h}:{$i}:{$s}", $e->getMessage());
            }
        }

        foreach ([[0, 1, 1], [10000, 1, 1], [2025, 0, 1], [2025, 13, 1], [2025, 1, 0], [2025, 1, 32]] as [$y, $m, $d]) {
            try {
                CalendarLimits::gregorianDate($y, $m, $d);
                self::fail("gregorianDate({$y}, {$m}, {$d}) must be rejected");
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
                self::assertSame("Gregorian date out of the supported range (years 1-9999): {$y}-{$m}-{$d}", $e->getMessage());
            }
        }
    }

    public function test_delta_and_negate_edges(): void
    {
        self::assertSame(5, CalendarLimits::delta(5, 5, 'days'));
        self::assertSame(-5, CalendarLimits::delta(-5, 5, 'days'));
        self::assertSame(0, CalendarLimits::delta(0, 0, 'days'));

        foreach ([6, -6] as $value) {
            try {
                CalendarLimits::delta($value, 5, 'days');
                self::fail("delta({$value}, 5) must be rejected");
            } catch (InvalidDateException $e) {
                self::assertSame("Cannot shift a date by {$value} days: out of the supported range.", $e->getMessage());
            }
        }

        self::assertSame(-7, CalendarLimits::negate(7));
        self::assertSame(7, CalendarLimits::negate(-7));
        self::assertSame(PHP_INT_MIN + 1, CalendarLimits::negate(PHP_INT_MAX));
        self::assertSame(PHP_INT_MAX, CalendarLimits::negate(PHP_INT_MIN + 1));

        $this->expectException(InvalidDateException::class);
        CalendarLimits::negate(PHP_INT_MIN);
    }
}
