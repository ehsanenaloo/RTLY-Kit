<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Holiday;

use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Holiday\IranHolidays;

final class IranHolidaysRangeTest extends TestCase
{
    public function test_out_of_range_years_throw_a_clear_error_with_context(): void
    {
        $calls = [
            static fn () => IranHolidays::all(99999),
            static fn () => IranHolidays::allTitles(99999),
            static fn () => IranHolidays::allFixed(99999),
            static fn () => IranHolidays::all(Jalali::MIN_YEAR - 1),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                self::fail('expected exception');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
                self::assertStringContainsString('outside the supported range', $e->getMessage());
                self::assertStringNotContainsString('Invalid Jalali date', $e->getMessage());
                self::assertSame(['year', 'min', 'max'], array_keys($e->getContext()));
                self::assertSame(Jalali::MIN_YEAR, $e->getContext()['min']);
                self::assertSame(Jalali::MAX_YEAR, $e->getContext()['max']);
            }
        }
    }

    public function test_years_before_the_hijri_epoch_return_fixed_holidays_only(): void
    {
        // Jalali -620..1 fall before AH 1 (622 CE) or outside the Umm al-Qura table: no Islamic titles, no exception.
        foreach ([Jalali::MIN_YEAR, -100, 0, 1, 600] as $year) {
            $titles = IranHolidays::allTitles($year);

            self::assertCount(10, $titles, "year $year");
            self::assertSame(['جشن نوروز'], array_values($titles)[0], "year $year");
            self::assertSame(IranHolidays::getTitle($year, 1, 1), 'جشن نوروز');
            self::assertCount(10, IranHolidays::all($year), "year $year");
        }
    }

    public function test_the_last_supported_year_lists_all_its_days_without_overflow(): void
    {
        $titles = IranHolidays::allTitles(Jalali::MAX_YEAR);

        self::assertCount(10, $titles);
        self::assertSame('9377/01/01', array_key_first($titles));
        self::assertSame('9377/12/29', array_key_last($titles));
    }

    public function test_boundary_years_work(): void
    {
        self::assertNotEmpty(IranHolidays::allFixed(Jalali::MAX_YEAR));
        self::assertArrayHasKey('1404/01/01', IranHolidays::all(1404));
    }
}
