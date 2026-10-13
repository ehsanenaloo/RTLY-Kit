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
 * The text and the context of the errors that the date parsers throw are part of
 * the contract: they tell the caller what was wrong without echoing the whole input.
 */
final class CalendarErrorContractTest extends TestCase
{
    private function messageOf(callable $call): string
    {
        try {
            $call();
        } catch (InvalidDateException $e) {
            return $e->getMessage();
        }
        self::fail('Expected an InvalidDateException.');
    }

    /** @return iterable<string, array{string, string}> */
    public static function makeMessages(): iterable
    {
        $own = 'it starts like a date of this calendar but is not exactly Y/m/d or Y/m/d H:i[:s[.u]]. Use that exact form, or pass a DateTimeImmutable.';

        yield 'negative year' => ['-0100/01/01', "Cannot read '-0100/01/01' as a date: text dates have no negative years. Use create() or pass a DateTimeImmutable."];
        yield 'starts like a date' => ['1403/01/01x', "Cannot read '1403/01/01' as a date: {$own}"];
        yield 'starts like a date, short' => ['1403/01', "Cannot read '1403/01' as a date: {$own}"];
        yield 'negative year after a date' => ['1403/01/01 -5/02/03', "Cannot read '1403/01/01' as a date: {$own}"];
        yield 'bare digits' => ['1403', "Cannot read '1403' as a date: write it as Y/m/d, pass a Unix timestamp as an int, or pass a DateTimeImmutable."];
        yield 'bare eight digits' => ['14030101', "Cannot read '14030101' as a date: write it as Y/m/d, pass a Unix timestamp as an int, or pass a DateTimeImmutable."];
        yield 'not a real day' => ['2024-02-30', 'Unable to parse date: 2024-02-30 is not a real date.'];
        yield 'not a date' => ['foo bar baz', 'Unable to parse date: foo bar baz'];
        yield 'a bare zone' => ['EST', 'Unable to parse date: EST'];
        yield 'a number that is too long' => ['123456789012345', 'Unable to parse date: 123456789012345 has a number that is too long.'];
        yield 'too many years' => ['+20001 years', 'Unable to parse date: +20001 years is out of the supported range.'];
        yield 'too many years back' => ['-20001 years', 'Unable to parse date: -20001 years is out of the supported range.'];
        yield 'too many months' => ['+240001 months', 'Unable to parse date: +240001 months is out of the supported range.'];
        yield 'too many days' => ['+3700001 days', 'Unable to parse date: +3700001 days is out of the supported range.'];
        yield 'too many hours' => ['+88800001 hours', 'Unable to parse date: +88800001 hours is out of the supported range.'];
        yield 'too many minutes' => ['+5328000001 minutes', 'Unable to parse date: +5328000001 minutes is out of the supported range.'];
        yield 'too many seconds' => ['+319680000001 seconds', 'Unable to parse date: +319680000001 seconds is out of the supported range.'];
        yield 'text before a date' => ['x 1403/01', 'Unable to parse date: x 1403/01'];
    }

    #[DataProvider('makeMessages')]
    public function test_make_error_messages(string $text, string $expected): void
    {
        self::assertSame($expected, $this->messageOf(fn () => Jalali::make($text, new DateTimeZone('UTC'))));
    }

    public function test_the_limits_are_exact(): void
    {
        $utc = new DateTimeZone('UTC');
        $ours = 'is out of the supported range.';

        foreach (['+20000 years', '+240000 months', '+3700000 days', '+88800000 hours', '+5328000000 minutes', '+319680000000 seconds'] as $text) {
            self::assertStringNotContainsString($ours, $this->messageOf(fn () => Jalali::make($text, $utc)), $text);
        }
        foreach (['+20001 years', '+240001 months', '+3700001 days', '+88800001 hours', '+5328000001 minutes', '+319680000001 seconds'] as $text) {
            self::assertStringContainsString($ours, $this->messageOf(fn () => Jalali::make($text, $utc)), $text);
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function formatMessages(): iterable
    {
        yield 'text' => ['Y/m/d', 'garbage', "Unable to parse 'garbage' with format 'Y/m/d'"];
        yield 'day out of the month' => ['Y/m/d', '1403/13/45 x', "Unable to parse '1403/13/45 x' with format 'Y/m/d'"];
        yield 'timestamp' => ['U', 'abc', "Unable to parse 'abc' with format 'U'"];
    }

    #[DataProvider('formatMessages')]
    public function test_create_from_format_messages(string $format, string $text, string $expected): void
    {
        self::assertSame($expected, $this->messageOf(fn () => Jalali::createFromFormat($format, $text)));
    }

    public function test_the_length_errors_carry_the_argument_and_the_limit(): void
    {
        foreach ([
            fn () => Jalali::make(str_repeat('1', 257)),
            fn () => Jalali::createFromFormat('Y', str_repeat('1', 257)),
        ] as $call) {
            try {
                $call();
                self::fail('Expected an InvalidDateException.');
            } catch (InvalidDateException $e) {
                self::assertSame('Input too long (257 bytes, max 256).', $e->getMessage());
                self::assertSame(['argument' => 'time', 'limit' => 256], $e->getContext());
                self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
            }
        }

        try {
            Jalali::createFromFormat(str_repeat('Y', 257), '1');
            self::fail('Expected an InvalidDateException.');
        } catch (InvalidDateException $e) {
            self::assertSame(['argument' => 'format', 'limit' => 256], $e->getContext());
        }
    }

    public function test_the_parser_checks_the_length_on_its_own(): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('Input too long (257 bytes, max 256).');
        CalendarLimits::parseGregorian(str_repeat('a', 257), null);
    }

    public function test_the_format_reader_does_not_read_five_digit_years_unless_asked(): void
    {
        $this->expectException(InvalidDateException::class);
        CalendarLimits::readFormat('Y/m/d', '12000/01/01');
    }

    public function test_the_format_reader_checks_both_lengths(): void
    {
        foreach ([[str_repeat('Y', 257), '1'], ['Y', str_repeat('1', 257)]] as [$format, $time]) {
            try {
                CalendarLimits::readFormat($format, $time);
                self::fail('Expected an InvalidDateException.');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
            }
        }
    }

    /** @return iterable<string, array{string, int}> */
    public static function fractions(): iterable
    {
        yield '123456' => ['123456', 123456];
        yield '000001' => ['000001', 1];
        yield '999999' => ['999999', 999999];
        yield '5' => ['5', 500000];
        yield '7' => ['7', 700000];
        yield '1' => ['1', 100000];
        yield '33' => ['33', 330000];
        yield '000999' => ['000999', 999];
    }

    #[DataProvider('fractions')]
    public function test_fractions_are_exact_microseconds(string $digits, int $micro): void
    {
        $read = CalendarLimits::readFormat('Y/m/d H:i:s.u', "1403/01/01 10:20:30.{$digits}");

        self::assertIsArray($read);
        self::assertSame($micro, $read[6]);
        self::assertSame(sprintf('%06d', $micro), Jalali::createFromFormat('Y/m/d H:i:s.u', "1403/01/01 10:20:00.{$digits}", new DateTimeZone('UTC'))->format('u'));
    }

    public function test_milliseconds_and_no_fraction(): void
    {
        self::assertSame(120000, CalendarLimits::readFormat('Y/m/d H:i:s.v', '1403/01/01 10:20:30.120')[6] ?? null);
        self::assertSame(0, CalendarLimits::readFormat('Y/m/d H:i:s', '1403/01/01 10:20:30')[6] ?? null);
        self::assertSame(0, CalendarLimits::readFormat('Y/m/d', '1403/01/01')[6] ?? null);
    }

    public function test_a_fraction_with_a_zero_second_is_kept_in_every_calendar(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame('500000', Jalali::createFromFormat('Y/m/d H:i:s.u', '1403/01/01 10:20:00.5', $utc)->format('u'));
        self::assertSame('500000', Hijri::createFromFormat('Y/m/d H:i:s.u', '1446/01/01 10:20:00.5', $utc)->format('u'));
        self::assertSame('500000', Hebrew::createFromFormat('Y/m/d H:i:s.u', '5785/01/01 10:20:00.5', $utc)->format('u'));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function fiveDigitYearFormats(): iterable
    {
        yield 'escaped dashes' => ['Y\-m\-d', '12000-01-01', '12000/01/01'];
        yield 'escape before the year' => ['d\-m\-Y', '01-01-12000', '12000/01/01'];
        yield 'j and n' => ['j.n.Y', '1.2.12000', '12000/02/01'];
        yield 'year then n and j' => ['Y n/j', '12000 2/1', '12000/02/01'];
        yield 'G' => ['Y/m/d G:i', '12000/01/01 8:05', '12000/01/01'];
        yield 'g with a' => ['Y/m/d g:i a', '12000/01/01 8:05 pm', '12000/01/01'];
        yield 'milliseconds' => ['Y/m/d H:i:s.v', '12000/01/01 10:00:00.123', '12000/01/01'];
    }

    #[DataProvider('fiveDigitYearFormats')]
    public function test_five_digit_hebrew_years_with_each_token(string $format, string $text, string $expectedDate): void
    {
        self::assertSame($expectedDate, Hebrew::createFromFormat($format, $text, new DateTimeZone('UTC'))->format('Y/m/d'), $format);
    }

    public function test_a_year_right_before_a_digit_token_is_not_split(): void
    {
        $this->expectException(InvalidDateException::class);
        Hebrew::createFromFormat('dY', '0112000');
    }

    public function test_an_escaped_character_does_not_hide_a_later_rejected_token(): void
    {
        try {
            Jalali::createFromFormat('\Y Y z', 'Y 1403 5');
            self::fail('Expected an InvalidDateException.');
        } catch (InvalidDateException $e) {
            self::assertStringContainsString("'z'", $e->getMessage());
        }
    }

    /** @return iterable<string, array{string, bool, ?int}> */
    public static function designators(): iterable
    {
        yield '+14:00' => ['+14:00', true, 50400];
        yield '+13:30' => ['+13:30', true, 48600];
        yield '+00:59' => ['+00:59', true, 3540];
        yield '+0030' => ['+0030', true, 1800];
        yield '-14:00' => ['-14:00', true, -50400];
        yield '+14:01' => ['+14:01', false, null];
        yield '+15:00' => ['+15:00', false, null];
        yield '+00:60' => ['+00:60', false, null];
    }

    #[DataProvider('designators')]
    public function test_zone_designator_limits(string $designator, bool $accepted, ?int $offset): void
    {
        if ($accepted) {
            self::assertSame($offset, Jalali::make("1403/01/01 10:00{$designator}")->toGregorian()->getOffset());

            return;
        }
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage("Invalid UTC offset: {$designator}");
        Jalali::make("1403/01/01 10:00{$designator}");
    }

    public function test_a_designator_sets_the_zone_and_an_argument_converts_to_it(): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');

        foreach ([[Jalali::class, '1403/01/01'], [Hijri::class, '1446/01/01']] as [$class, $day]) {
            $kept = $class::make("{$day} 10:00:00+05:00");
            self::assertSame(18000, $kept->toGregorian()->getOffset());

            $converted = $class::make("{$day} 10:00:00+05:00", $tehran);
            self::assertSame('Asia/Tehran', $converted->getTimezone()->getName());
            self::assertSame($kept->getTimestamp(), $converted->getTimestamp());

            $plain = $class::make("{$day} 10:00:00", $tehran);
            self::assertSame('Asia/Tehran', $plain->getTimezone()->getName());
            self::assertSame('10:00', $plain->format('H:i'));
        }
    }
}
