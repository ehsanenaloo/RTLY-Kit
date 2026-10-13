<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\RtlyKitThrowable;

final class CalendarParsingHardeningTest extends TestCase
{
    /** @return iterable<string, array{class-string<Jalali|Hijri|Hebrew>}> */
    public static function calendars(): iterable
    {
        yield 'jalali' => [Jalali::class];
        yield 'hijri' => [Hijri::class];
        yield 'hebrew' => [Hebrew::class];
    }

    /** @param class-string<Jalali|Hijri|Hebrew> $class */
    #[DataProvider('calendars')]
    public function test_overflowing_relative_numbers_throw(string $class): void
    {
        $utc = new DateTimeZone('UTC');
        foreach ([
            '9223372036854775807 sec',
            '9223372036854775807 days',
            '-9223372036854775808 sec',
            '+9223372036854775807 sec',
            '99999999999 years',
            '999999999999 sec',
            '123456789012345',
        ] as $text) {
            try {
                $class::make($text, $utc);
                self::fail("{$class} accepted '{$text}'");
            } catch (RtlyKitThrowable $e) {
                self::assertInstanceOf(InvalidDateException::class, $e, $text);
            }
        }
    }

    public function test_ordinary_relative_and_compact_forms_still_work(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('2024-01-01 12:00:00', Jalali::make('20240101120000', $utc)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('2024-01-01 12:00:00', Jalali::make('20240101T120000', $utc)->toGregorian()->format('Y-m-d H:i:s'));
        self::assertTrue(Jalali::make('+1 day', $utc)->isFuture());
        self::assertTrue(Jalali::make('2024-01-01 00:00:00.123456789', $utc)->isPast());
    }

    /** @param class-string<Jalali|Hijri|Hebrew> $class */
    #[DataProvider('calendars')]
    public function test_error_messages_are_safe_text(string $class): void
    {
        foreach (["abc\xff\xfe", "line one\nline two\x1b[31m", "x\u{2028}y", str_repeat("\xff", 200)] as $text) {
            try {
                $class::make($text);
                self::fail('Expected an exception.');
            } catch (InvalidDateException $e) {
                $message = $e->getMessage();
                self::assertTrue(mb_check_encoding($message, 'UTF-8'), 'valid UTF-8');
                self::assertNotFalse(json_encode($message));
                self::assertSame(0, preg_match('/[\x00-\x08\x0A-\x1F\x7F]|\x{2028}/u', $message), 'no control characters');
                self::assertLessThan(400, strlen($message));
            }
        }

        try {
            $class::createFromFormat("Y\n\xff", "1403\n\xff");
            self::fail('Expected an exception.');
        } catch (InvalidDateException $e) {
            self::assertTrue(mb_check_encoding($e->getMessage(), 'UTF-8'));
            self::assertSame(0, preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $e->getMessage()));
        }
    }

    public function test_hebrew_reads_back_what_it_formats(): void
    {
        foreach ([[12000, 1, 1], [13759, 12, 29], [10000, 6, 15], [5785, 1, 1]] as [$y, $m, $d]) {
            try {
                $date = Hebrew::create($y, $m, $d);
            } catch (InvalidDateException) {
                continue;
            }
            $text = $date->format('Y/m/d H:i:s');
            $back = Hebrew::createFromFormat('Y/m/d H:i:s', $text);

            self::assertSame($date->format('U'), $back->format('U'), $text);
            self::assertSame($text, $back->format('Y/m/d H:i:s'));
        }

        self::assertSame('12000/01/01', Hebrew::createFromFormat('d.m.Y', '01.01.12000')->format('Y/m/d'));
        self::assertSame('12000/01/01', Hebrew::createFromFormat('Y/m/d', '۱۲۰۰۰/۰۱/۰۱')->format('Y/m/d'));
    }

    public function test_the_five_digit_year_is_tied_to_the_place_of_y(): void
    {
        $utc = new DateTimeZone('UTC');

        $a = Hebrew::createFromFormat('Y-m-d H:i:s.u', '5784-01-01 12:30:00.12345', $utc);
        self::assertSame('5784/01/01 12:30:00.123450', $a->format('Y/m/d H:i:s.u'));

        $b = Hebrew::createFromFormat('Y-m-d H:i:s.u', '10000-01-01 12:30:00.12345', $utc);
        self::assertSame('10000/01/01 12:30:00.123450', $b->format('Y/m/d H:i:s.u'));

        $c = Hebrew::createFromFormat('H:i d.m.Y', '08:05 02.03.12000', $utc);
        self::assertSame('12000/03/02 08:05:00', $c->format('Y/m/d H:i:s'));

        $this->expectException(InvalidDateException::class);
        Hebrew::createFromFormat('Y-m-d', '05784-01-01', $utc);
    }

    public function test_the_five_digit_year_works_with_escapes_and_am_pm(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('12000/01/01', Hebrew::createFromFormat('\Y: Y/m/d', 'Y: 12000/01/01', $utc)->format('Y/m/d'));
        self::assertSame('12000/01/01 22:05:00', Hebrew::createFromFormat('Y/m/d h:i A', '12000/01/01 10:05 PM', $utc)->format('Y/m/d H:i:s'));
        self::assertSame('12000/01/01 22:05:00', Hebrew::createFromFormat('Y/m/d g:i a', '12000/01/01 10:05 pm', $utc)->format('Y/m/d H:i:s'));
        $this->expectException(InvalidDateException::class);
        Hebrew::createFromFormat('Y\\', '12000\\', $utc);
    }

    public function test_the_parser_rejects_a_nul_byte_on_its_own(): void
    {
        $this->expectException(InvalidDateException::class);
        \RtlyKit\Calendar\CalendarLimits::parseGregorian("2025-01-01\0", null);
    }

    public function test_a_year_next_to_another_digit_token_is_not_split(): void
    {
        $this->expectException(InvalidDateException::class);
        Hebrew::createFromFormat('Ymd', '120000101', new DateTimeZone('UTC'));
    }

    public function test_a_long_fraction_after_the_dot_is_not_a_long_number(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('2025-01-15', Jalali::make('2025-01-15 10:00:00.123456789012345', $utc)->toGregorian()->format('Y-m-d'));
        $this->expectException(InvalidDateException::class);
        Jalali::make('2025-01-15 123456789012345', $utc);
    }

    public function test_hebrew_five_digit_years_are_range_checked(): void
    {
        $this->expectException(InvalidDateException::class);
        Hebrew::createFromFormat('Y/m/d', '99999/01/01');
    }

    /** @param class-string<Jalali|Hijri|Hebrew> $class */
    #[DataProvider('calendars')]
    public function test_the_fraction_tokens_read_microseconds_and_milliseconds(string $class): void
    {
        $y = ['Jalali' => 1403, 'Hijri' => 1446, 'Hebrew' => 5785][(new \ReflectionClass($class))->getShortName()];
        $utc = new DateTimeZone('UTC');

        $micro = $class::createFromFormat('Y/m/d H:i:s.u', "{$y}/02/03 10:20:30.123456", $utc);
        self::assertSame('123456', $micro->format('u'));
        self::assertSame("{$y}/02/03 10:20:30.123456", $micro->format('Y/m/d H:i:s.u'));

        $milli = $class::createFromFormat('Y/m/d H:i:s.v', "{$y}/02/03 10:20:30.987", $utc);
        self::assertSame('987000', $milli->format('u'));

        $short = $class::createFromFormat('Y/m/d H:i:s.u', "{$y}/02/03 10:20:30.5", $utc);
        self::assertSame('500000', $short->format('u'));

        self::assertSame('000000', $class::createFromFormat('Y/m/d H:i:s', "{$y}/02/03 10:20:30", $utc)->format('u'));
        self::assertSame('000000', $class::createFromFormat('Y/m/d', "{$y}/02/03", $utc)->format('u'));
    }

    public function test_other_calendars_still_read_four_digit_years_only(): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::createFromFormat('Y/m/d', '10000/01/01');
    }
}
