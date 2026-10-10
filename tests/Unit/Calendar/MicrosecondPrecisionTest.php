<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Contracts\CalendarDate;

/**
 * Precision policy: a date made from a DateTimeInterface keeps its microseconds.
 * Comparison and between() look at the whole instant, and addMonths() and
 * addYears() keep the microseconds like addDays() and addSeconds() do.
 */
final class MicrosecondPrecisionTest extends TestCase
{
    /** @return iterable<string, array{class-string<CalendarDate>}> */
    public static function calendars(): iterable
    {
        yield 'Jalali' => [Jalali::class];
        yield 'Hijri' => [Hijri::class];
        yield 'Hebrew' => [Hebrew::class];
    }

    private static function at(string $class, string $time): CalendarDate
    {
        return $class::make(new DateTimeImmutable($time, new DateTimeZone('UTC')));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_comparison_sees_microseconds(string $class): void
    {
        $early = self::at($class, '2026-03-21 10:00:00.100000');
        $late = self::at($class, '2026-03-21 10:00:00.900000');

        self::assertFalse($early->eq($late));
        self::assertTrue($early->ne($late));
        self::assertTrue($early->lt($late));
        self::assertTrue($early->lte($late));
        self::assertTrue($late->gt($early));
        self::assertTrue($late->gte($early));
        self::assertFalse($late->lt($early));
        self::assertTrue($early->eq(self::at($class, '2026-03-21 10:00:00.100000')));
        // Same instant seen from another time zone and another calendar.
        self::assertTrue($early->eq(new DateTimeImmutable('2026-03-21 13:30:00.100000', new DateTimeZone('Asia/Tehran'))));
        // The whole-second view stays the same.
        self::assertSame($early->getTimestamp(), $late->getTimestamp());
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_between_sees_microseconds(string $class): void
    {
        $a = self::at($class, '2026-03-21 10:00:00.200000');
        $b = self::at($class, '2026-03-21 10:00:00.800000');

        self::assertTrue(self::at($class, '2026-03-21 10:00:00.500000')->between($a, $b));
        self::assertTrue(self::at($class, '2026-03-21 10:00:00.500000')->between($b, $a));
        self::assertFalse(self::at($class, '2026-03-21 10:00:00.100000')->between($a, $b));
        self::assertFalse(self::at($class, '2026-03-21 10:00:00.900000')->between($a, $b));
        self::assertTrue($a->between($a, $b));
        self::assertFalse($a->between($a, $b, false));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_every_adder_keeps_the_microseconds(string $class): void
    {
        $date = self::at($class, '2026-03-21 10:20:30.123456');

        foreach ([
            'addMonths(0)' => $date->addMonths(0),
            'addMonths(1)' => $date->addMonths(1),
            'addMonths(-14)' => $date->addMonths(-14),
            'addYears(0)' => $date->addYears(0),
            'addYears(2)' => $date->addYears(2),
            'subMonths(3)' => $date->subMonths(3),
            'subYears(1)' => $date->subYears(1),
            'addDays(1)' => $date->addDays(1),
            'addSeconds(1)' => $date->addSeconds(1),
        ] as $label => $result) {
            self::assertSame('123456', $result->format('u'), $label);
        }

        // A zero step is the same instant.
        self::assertTrue($date->addMonths(0)->eq($date));
        self::assertTrue($date->addYears(0)->eq($date));
        // The wall-clock time is kept as before.
        self::assertSame('10:20:30', $date->addMonths(1)->format('H:i:s'));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_whole_second_dates_are_unchanged(string $class): void
    {
        $date = self::at($class, '2026-03-21 10:20:30');

        self::assertSame('000000', $date->addMonths(1)->format('u'));
        self::assertTrue($date->addMonths(0)->eq($date));
        self::assertTrue($date->eq(self::at($class, '2026-03-21 10:20:30')));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_constructors_from_fields_have_whole_seconds(string $class): void
    {
        $created = $class::create(...match ($class) {
            Jalali::class => [1405, 1, 1, 8, 0, 0],
            Hijri::class => [1447, 9, 1, 8, 0, 0],
            default => [5786, 7, 1, 8, 0, 0],
        });

        self::assertSame('000000', $created->format('u'));
        self::assertSame('000000', $created->startOfDay()->format('u'));
    }
}
