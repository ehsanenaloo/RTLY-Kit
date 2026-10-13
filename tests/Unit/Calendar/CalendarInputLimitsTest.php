<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\CalendarLimits;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

final class CalendarInputLimitsTest extends TestCase
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
    public function test_make_rejects_oversized_text_before_parsing(string $class): void
    {
        foreach ([str_repeat('1', 257), str_repeat('۱', 129), '1403/01/01 '.str_repeat(' ', 300), str_repeat('x', 5_000_000)] as $text) {
            try {
                $class::make($text);
                self::fail('Expected an exception.');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
                self::assertSame(CalendarLimits::MAX_TEXT_BYTES, $e->getContext()['limit']);
                self::assertLessThan(200, strlen($e->getMessage()));
            }
        }
    }

    /** @param class-string<Jalali|Hijri|Hebrew> $class */
    #[DataProvider('calendars')]
    public function test_create_from_format_rejects_oversized_text_and_format(string $class): void
    {
        foreach ([[str_repeat('Y', 257), '1403'], ['Y', str_repeat('1', 257)], ['Y/m/d', str_repeat('۱', 129)]] as [$format, $text]) {
            try {
                $class::createFromFormat($format, $text);
                self::fail('Expected an exception.');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
                self::assertLessThan(200, strlen($e->getMessage()));
            }
        }
    }

    public function test_texts_at_the_limit_still_parse(): void
    {
        $text = '1403/01/01'.str_repeat(' ', 246);
        self::assertSame(256, strlen($text));
        self::assertSame('1403/01/01', Jalali::make($text)->format('Y/m/d'));
        self::assertSame('1403/01/01', Jalali::make('۱۴۰۳/۰۱/۰۱')->format('Y/m/d'));
        self::assertSame('1403/01/01', Jalali::createFromFormat('Y/m/d', '1403/01/01')->format('Y/m/d'));
        self::assertSame('1403/01/01', Jalali::make('1403/01/01 00:00:00.000000+03:30')->format('Y/m/d'));
    }

    /** @param class-string<Jalali|Hijri|Hebrew> $class */
    #[DataProvider('calendars')]
    public function test_parse_errors_echo_a_short_excerpt(string $class): void
    {
        try {
            $class::make('2024-13-45 '.str_repeat('é', 120));
            self::fail('Expected an exception.');
        } catch (InvalidDateException $e) {
            self::assertLessThan(160, strlen($e->getMessage()));
            self::assertTrue(mb_check_encoding($e->getMessage(), 'UTF-8'));
        }

        try {
            $class::createFromFormat(str_repeat('Y', 100), str_repeat('é', 100));
            self::fail('Expected an exception.');
        } catch (InvalidDateException $e) {
            self::assertLessThan(260, strlen($e->getMessage()));
            self::assertTrue(mb_check_encoding($e->getMessage(), 'UTF-8'));
        }
    }
}
