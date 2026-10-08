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
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Behaviour shared by the three calendars through the common trait: the size
 * limits of day/hour/minute/second shifts, instant comparison, between() and the
 * sign rules of diffInDays().
 */
final class CalendarDateTraitTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    /** @return array<string, array{class-string<CalendarDate>}> */
    public static function calendars(): array
    {
        return [
            'jalali' => [Jalali::class],
            'hijri' => [Hijri::class],
            'hebrew' => [Hebrew::class],
        ];
    }

    /**
     * Largest accepted shift per unit: 3,700,000 days (a little more than the 3,652,059
     * days of Gregorian years 1..9999) and the same span in hours, minutes and seconds.
     *
     * @return array<string, array{string, int}>
     */
    public static function shiftUnits(): array
    {
        return [
            'days' => ['days', 3_700_000],
            'hours' => ['hours', 88_800_000],
            'minutes' => ['minutes', 5_328_000_000],
            'seconds' => ['seconds', 319_680_000_000],
        ];
    }

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_shift_limit_is_inclusive_per_unit(string $class): void
    {
        $date = $class::make('2025-03-21 10:30:00', $this->utc);

        foreach (self::shiftUnits() as [$unit, $limit]) {
            $add = 'add'.ucfirst($unit);
            $sub = 'sub'.ucfirst($unit);

            // At the limit the size guard passes; the date itself is then out of range.
            foreach ([[$add, $limit], [$add, -$limit], [$sub, $limit], [$sub, -$limit]] as [$method, $value]) {
                try {
                    $date->{$method}($value);
                    self::fail("{$class}::{$method}({$value}) must leave the supported range");
                } catch (InvalidDateException $e) {
                    self::assertStringNotContainsString('Cannot shift', $e->getMessage(), "{$class}::{$method}({$value})");
                    self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode(), "{$class}::{$method}({$value})");
                }
            }

            // One more and the size guard rejects it.
            $tooBig = $limit + 1;
            foreach ([[$add, $tooBig], [$add, -$tooBig], [$sub, $tooBig], [$sub, -$tooBig]] as [$method, $value]) {
                try {
                    $date->{$method}($value);
                    self::fail("{$class}::{$method}({$value}) must be rejected");
                } catch (InvalidDateException $e) {
                    $shown = $method === $sub ? -$value : $value;
                    self::assertSame(
                        "Cannot shift a date by {$shown} {$unit}: out of the supported range.",
                        $e->getMessage(),
                    );
                    self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
                }
            }
        }
    }

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_shifts_just_inside_the_limit_still_work(string $class): void
    {
        $date = $class::make('2025-03-21 10:30:00', $this->utc);

        self::assertSame('2025-03-22 10:30:00', $date->addDays(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-21 11:30:00', $date->addHours(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-21 10:31:00', $date->addMinutes(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-21 10:30:01', $date->addSeconds(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-20 10:30:00', $date->subDays(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-21 09:30:00', $date->subHours(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-21 10:29:00', $date->subMinutes(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-21 10:29:59', $date->subSeconds(1)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-21 10:30:00', $date->addDays(0)->toGregorian()->format('Y-m-d H:i:s'));
    }

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_negating_the_smallest_integer_is_rejected(string $class): void
    {
        $date = $class::make('2025-03-21', $this->utc);

        foreach (['subDays', 'subHours', 'subMinutes', 'subSeconds', 'subMonths', 'subYears'] as $method) {
            try {
                $date->{$method}(PHP_INT_MIN);
                self::fail("{$class}::{$method}(PHP_INT_MIN)");
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode(), $method);
            }
        }
    }

    /* ---------------- comparison ---------------- */

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_comparison_of_earlier_equal_and_later_instants(string $class): void
    {
        $date = $class::make('2025-03-21 10:30:00', $this->utc);
        $same = new DateTimeImmutable('2025-03-21 10:30:00', $this->utc);
        $later = new DateTimeImmutable('2025-03-21 10:30:01', $this->utc);
        $earlier = new DateTimeImmutable('2025-03-21 10:29:59', $this->utc);

        self::assertTrue($date->eq($same));
        self::assertTrue($date->equals($same));
        self::assertFalse($date->ne($same));
        self::assertFalse($date->gt($same));
        self::assertTrue($date->gte($same));
        self::assertFalse($date->lt($same));
        self::assertTrue($date->lte($same));
        self::assertFalse($date->isBefore($same));
        self::assertFalse($date->isAfter($same));

        self::assertFalse($date->eq($later));
        self::assertTrue($date->ne($later));
        self::assertFalse($date->gt($later));
        self::assertFalse($date->gte($later));
        self::assertTrue($date->lt($later));
        self::assertTrue($date->lte($later));
        self::assertTrue($date->isBefore($later));
        self::assertFalse($date->isAfter($later));

        self::assertFalse($date->eq($earlier));
        self::assertTrue($date->ne($earlier));
        self::assertTrue($date->gt($earlier));
        self::assertTrue($date->gte($earlier));
        self::assertFalse($date->lt($earlier));
        self::assertFalse($date->lte($earlier));
        self::assertFalse($date->isBefore($earlier));
        self::assertTrue($date->isAfter($earlier));
    }

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_between_is_inclusive_by_default_and_order_independent(string $class): void
    {
        $date = $class::make('2025-03-21 10:30:00', $this->utc);
        $low = new DateTimeImmutable('2025-03-21 10:30:00', $this->utc);
        $high = new DateTimeImmutable('2025-03-21 10:30:10', $this->utc);
        $below = new DateTimeImmutable('2025-03-21 10:29:00', $this->utc);
        $above = new DateTimeImmutable('2025-03-21 10:31:00', $this->utc);

        // Default: both ends belong to the range.
        self::assertTrue($date->between($low, $high));
        self::assertTrue($date->between($high, $low));
        self::assertTrue($date->between($below, $above));
        self::assertTrue($date->between($above, $below));
        self::assertTrue($date->addSeconds(10)->between($low, $high));
        self::assertFalse($date->addSeconds(11)->between($low, $high));
        self::assertFalse($date->subSeconds(1)->between($low, $high));

        // Exclusive: the ends do not.
        self::assertFalse($date->between($low, $high, false));
        self::assertFalse($date->between($high, $low, false));
        self::assertFalse($date->addSeconds(10)->between($low, $high, false));
        self::assertTrue($date->addSeconds(1)->between($low, $high, false));
        self::assertTrue($date->addSeconds(9)->between($high, $low, false));
        self::assertTrue($date->between($below, $above, false));

        // Explicitly inclusive.
        self::assertTrue($date->between($low, $high, true));
        self::assertTrue($date->addSeconds(10)->between($low, $high, true));
        self::assertTrue($date->between($date, $date));
        self::assertFalse($date->between($date, $date, false));
    }

    /* ---------------- diffInDays ---------------- */

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_diff_in_days_truncates_and_follows_the_sign_rules(string $class): void
    {
        $date = $class::make('2025-03-21 00:00:00', $this->utc);
        $later = new DateTimeImmutable('2025-03-25 18:00:00', $this->utc);

        // Absolute is the default.
        self::assertSame(4, $date->diffInDays($later));
        self::assertSame(4, $date->diffInDays($later, true));
        // Not absolute: the sign of $this - $other.
        self::assertSame(-4, $date->diffInDays($later, false));

        $laterDate = $class::make('2025-03-25 18:00:00', $this->utc);
        self::assertSame(4, $laterDate->diffInDays($date));
        self::assertSame(4, $laterDate->diffInDays($date, false));
        self::assertSame(-4, $date->diffInDays($laterDate, false));

        self::assertSame(0, $date->diffInDays($date));
        self::assertSame(0, $date->diffInDays($date, false));
        // 23 hours is not a whole day.
        self::assertSame(0, $date->diffInDays(new DateTimeImmutable('2025-03-21 23:00:00', $this->utc)));
    }

    public function test_diff_accepts_any_calendar_as_the_other_operand(): void
    {
        $jalali = Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc);
        $hebrew = Hebrew::make('2025-03-31 00:00:00', $this->utc);

        self::assertSame(10, $jalali->diffInDays($hebrew));
        self::assertSame(-10, $jalali->diffInDays($hebrew, false));
        self::assertSame(10, $hebrew->diffInDays($jalali));
        self::assertSame(10, $hebrew->diffInDays($jalali, false));
    }

    /* ---------------- relative to now ---------------- */

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_past_and_future(string $class): void
    {
        $now = $class::now($this->utc);

        self::assertTrue($now->subSeconds(30)->isPast());
        self::assertFalse($now->subSeconds(30)->isFuture());
        self::assertTrue($now->addSeconds(30)->isFuture());
        self::assertFalse($now->addSeconds(30)->isPast());
    }

    /**
     * The present second is neither past nor future.
     *
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_the_current_second_is_neither_past_nor_future(string $class): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $second = time();
            $date = $class::make($second, $this->utc);
            $past = $date->isPast();
            $future = $date->isFuture();

            if (time() === $second) { // the clock did not tick during the three calls above
                self::assertFalse($past);
                self::assertFalse($future);

                return;
            }
        }

        self::markTestSkipped('the clock ticked during every attempt');
    }

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_is_today_uses_the_zone_of_the_instance(string $class): void
    {
        // UTC+14 is never on the same calendar day as UTC-12 at one instant.
        foreach (['Pacific/Kiritimati', 'Pacific/Pago_Pago', 'UTC'] as $zone) {
            $tz = new DateTimeZone($zone);
            $noon = $class::now($tz)->startOfDay()->addHours(12);

            self::assertTrue($noon->isToday(), $zone);
            self::assertFalse($noon->addDays(1)->isToday(), $zone);
            self::assertFalse($noon->subDays(1)->isToday(), $zone);
        }
    }

    /* ---------------- text forms ---------------- */

    /**
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_date_strings(string $class): void
    {
        $date = $class::make('2025-03-21 07:08:09', $this->utc);

        self::assertSame($date->format('Y/m/d'), $date->toDateString());
        self::assertSame($date->format('Y/m/d H:i:s'), $date->toDateTimeString());
        self::assertSame($date->toDateTimeString(), $date->jsonSerialize());
        self::assertSame(['hour' => 7, 'minute' => 8, 'second' => 9], [
            'hour' => $date->getHour(),
            'minute' => $date->getMinute(),
            'second' => $date->getSecond(),
        ]);
    }

    /**
     * The format pattern cap is 256 bytes, inclusive.
     *
     * @param class-string<CalendarDate> $class
     */
    #[DataProvider('calendars')]
    public function test_format_length_cap_is_inclusive(string $class): void
    {
        $date = $class::make('2025-03-21 07:08:09', $this->utc);

        self::assertSame(str_repeat('-', 256), $date->format(str_repeat('-', 256)));

        try {
            $date->format(str_repeat('-', 257));
            self::fail('a 257-byte pattern must be rejected');
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
            self::assertSame('Format string too long (257 bytes, max 256).', $e->getMessage());
            self::assertSame(['argument' => 'format', 'limit' => 256], $e->getContext());
        }
    }
}
