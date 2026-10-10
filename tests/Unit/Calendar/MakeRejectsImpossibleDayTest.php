<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\CalendarLimits;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * A Gregorian string with an impossible day is an error (PHP would roll
 * 2024-02-30 over to 2024-03-01), as create() is.
 */
final class MakeRejectsImpossibleDayTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function impossible(): iterable
    {
        yield 'february 30 in a leap year' => ['2024-02-30'];
        yield 'february 29 in a common year' => ['2023-02-29'];
        yield 'april 31' => ['2024-04-31'];
        yield 'with a time' => ['2024-06-31 10:00:00'];
        yield 'slashes' => ['2024/11/31'];
        yield 'persian digits' => ['۲۰۲۴-۰۲-۳۰'];
    }

    #[DataProvider('impossible')]
    public function test_every_calendar_throws_for_an_impossible_gregorian_day(string $text): void
    {
        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            try {
                $class::make($text);
                self::fail("{$class}::make('{$text}') should throw");
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode(), $class);
                self::assertStringContainsString('not a real date', $e->getMessage());
            }
        }
    }

    public function test_the_message_names_the_string(): void
    {
        try {
            CalendarLimits::parseGregorian('2024-02-30', null);
            self::fail('expected an exception');
        } catch (InvalidDateException $e) {
            self::assertStringContainsString('2024-02-30', $e->getMessage());
        }
    }

    public function test_real_dates_and_relative_formats_still_work(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('1402/12/10', Jalali::make('2024-02-29', $utc)->toDateString());
        self::assertSame('1402/12/10', Jalali::make('2024-02-29 23:59:59', $utc)->toDateString());
        self::assertSame('1403/01/01', Jalali::make('2024-03-20', $utc)->toDateString());
        self::assertSame('1402/12/10', Jalali::make('last day of february 2024', $utc)->toDateString());
        self::assertSame('1403/01/01', Jalali::make('2024-03-20T00:00:00+00:00', $utc)->toDateString());

        $now = Jalali::now($utc);
        foreach (['now', 'today', 'tomorrow', 'yesterday', '+1 day', '-2 weeks', 'last monday', 'first day of next month', 'noon'] as $relative) {
            foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
                self::assertInstanceOf($class, $class::make($relative, $utc), "{$class} '{$relative}'");
            }
        }
        self::assertSame($now->toDateString(), Jalali::make('today', $utc)->toDateString());
        self::assertSame(1700000000, Jalali::make(1700000000, $utc)->getTimestamp());
    }

    public function test_create_is_still_strict_and_unaffected(): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::create(1404, 12, 30);
    }
}
