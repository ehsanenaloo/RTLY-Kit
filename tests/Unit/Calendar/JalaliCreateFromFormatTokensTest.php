<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * createFromFormat() reads the year, month and day as Jalali values, so
 * tokens that mean something Gregorian (or carry a zone) are rejected, and
 * the format `U` alone is a Unix timestamp.
 */
final class JalaliCreateFromFormatTokensTest extends TestCase
{
    public function test_the_u_format_alone_is_a_unix_timestamp(): void
    {
        $utc = new DateTimeZone('UTC');

        $date = Jalali::createFromFormat('U', '1700000000', $utc);
        self::assertSame(1700000000, $date->getTimestamp());
        self::assertSame('1402/08/23 22:13:20', $date->format('Y/m/d H:i:s'));

        self::assertSame(1700000000, Jalali::createFromFormat(' U ', ' ۱۷۰۰۰۰۰۰۰۰ ', $utc)->getTimestamp());
        self::assertSame(-1, Jalali::createFromFormat('U', '-1', $utc)->getTimestamp());

        $tehran = Jalali::createFromFormat('U', '1700000000', new DateTimeZone('Asia/Tehran'));
        self::assertSame(1700000000, $tehran->getTimestamp());
        self::assertSame('1402/08/24 01:43:20', $tehran->format('Y/m/d H:i:s'));
    }

    /** @return iterable<string, array{string}> */
    public static function badTimestamps(): iterable
    {
        yield 'text' => ['abc'];
        yield 'fraction' => ['1700000000.5'];
        yield 'empty' => [''];
        yield 'too many digits' => ['99999999999999999999'];
        yield 'out of range' => ['99999999999999'];
    }

    #[DataProvider('badTimestamps')]
    public function test_the_u_format_rejects_non_timestamps(string $text): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::createFromFormat('U', $text);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function rejected(): iterable
    {
        yield 'U with others' => ['U Y', '1700000000 1403', 'U'];
        yield 'day of year' => ['Y z', '1403 5', 'z'];
        yield 'zone identifier' => ['Y/m/d e', '1403/01/01 UTC', 'e'];
        yield 'zone abbreviation' => ['Y/m/d T', '1403/01/01 UTC', 'T'];
        yield 'offset with colon' => ['Y/m/d P', '1403/01/01 +03:30', 'P'];
        yield 'offset' => ['Y/m/d O', '1403/01/01 +0330', 'O'];
        yield 'offset or Z' => ['Y/m/d p', '1403/01/01 Z', 'p'];
        yield 'microseconds' => ['Y/m/d H:i:s.u', '1403/01/01 10:00:00.123456', 'u'];
        yield 'milliseconds' => ['Y/m/d H:i:s.v', '1403/01/01 10:00:00.123', 'v'];
        yield 'two-digit year' => ['y/m/d', '03/01/01', 'y'];
        yield 'english month name' => ['F j, Y', 'March 5, 1403', 'F'];
        yield 'english month abbreviation' => ['M j Y', 'Mar 5 1403', 'M'];
        yield 'english day abbreviation' => ['D Y/m/d', 'Mon 1403/01/01', 'D'];
        yield 'english day name' => ['l Y/m/d', 'Monday 1403/01/01', 'l'];
        yield 'ordinal suffix' => ['jS Y/m', '1st 1403/01', 'S'];
    }

    #[DataProvider('rejected')]
    public function test_gregorian_and_zone_tokens_are_rejected_and_named(string $format, string $text, string $token): void
    {
        try {
            Jalali::createFromFormat($format, $text);
            self::fail("createFromFormat('{$format}') should throw");
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
            self::assertStringContainsString("'{$token}'", $e->getMessage());
        }
    }

    public function test_escaped_characters_are_literal_text_and_not_tokens(): void
    {
        $date = Jalali::createFromFormat('Y/m/d \\z\\e\\T', '1403/01/01 zeT', new DateTimeZone('UTC'));

        self::assertSame('1403/01/01', $date->toDateString());
    }

    public function test_the_working_tokens_still_work(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('1403/01/01 22:05:09', Jalali::createFromFormat('Y/m/d H:i:s', '1403/01/01 22:05:09', $utc)->toDateTimeString());
        self::assertSame('1403/02/05 22:05:09', Jalali::createFromFormat('Y-n-j G:i:s', '1403-2-5 22:05:09', $utc)->toDateTimeString());
        self::assertSame('1403/02/05 22:05:00', Jalali::createFromFormat('d/m/Y h:i A', '05/02/1403 10:05 PM', $utc)->toDateTimeString());
        self::assertSame('1403/02/05 10:05:00', Jalali::createFromFormat('Y/m/d g:i a', '1403/02/05 10:05 am', $utc)->toDateTimeString());
        self::assertSame('1403/01/01', Jalali::createFromFormat('Y/m/d', '۱۴۰۳/۰۱/۰۱', $utc)->toDateString());
    }

    public function test_a_nul_byte_is_an_invalid_date_not_a_value_error(): void
    {
        foreach ([['Y-m-d', "1403-01-01\0"], ["Y-m-d\0", '1403-01-01'], ["\0", "\0"], ['U', "1700000000\0"]] as [$format, $text]) {
            try {
                Jalali::createFromFormat($format, $text);
                self::fail('expected an exception');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
            }
        }
    }
}
