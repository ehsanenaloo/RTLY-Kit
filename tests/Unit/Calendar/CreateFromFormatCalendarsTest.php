<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Hijri::createFromFormat() and Hebrew::createFromFormat() follow the policy of
 * Jalali::createFromFormat(): own-calendar values, a fixed token set, `U` alone
 * is a timestamp.
 */
final class CreateFromFormatCalendarsTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    public function test_hijri_reads_hijri_values(): void
    {
        $date = Hijri::createFromFormat('Y/m/d H:i:s', '1446/09/01 10:20:30', $this->utc);

        self::assertSame('1446/09/01 10:20:30', $date->toDateTimeString());
        self::assertSame('2025-03-01', $date->toGregorian()->format('Y-m-d'));
        self::assertSame(HijriVariant::UmmAlQura, $date->getVariant());

        self::assertSame('1446/09/01 22:30:00', Hijri::createFromFormat('d/m/Y h:i A', '01/09/1446 10:30 PM', $this->utc)->toDateTimeString());
        self::assertSame('1446/09/05', Hijri::createFromFormat('Y-n-j', '1446-9-5', $this->utc)->toDateString());
        self::assertSame('1446/09/01', Hijri::createFromFormat('Y/m/d', '۱۴۴۶/۰۹/۰۱', $this->utc)->toDateString());
        self::assertSame('1446/09/01', Hijri::createFromFormat('Y/m/d \\z', '1446/09/01 z', $this->utc)->toDateString());
    }

    public function test_hijri_variant_is_the_last_argument(): void
    {
        $date = Hijri::createFromFormat('Y/m/d', '1446/09/01', $this->utc, HijriVariant::Tabular);

        self::assertSame(HijriVariant::Tabular, $date->getVariant());
        self::assertSame('1446/09/01', $date->toDateString());
        self::assertSame(HijriVariant::Tabular, Hijri::createFromFormat('U', '1700000000', $this->utc, HijriVariant::Tabular)->getVariant());
    }

    public function test_hebrew_reads_hebrew_values_with_the_ordinal_month(): void
    {
        $date = Hebrew::createFromFormat('Y/m/d H:i', '5785/01/01 08:15', $this->utc);

        self::assertSame('5785/01/01 08:15:00', $date->toDateTimeString());
        self::assertSame('2024-10-03', $date->toGregorian()->format('Y-m-d'));

        // 5784 is a leap year, so month 13 (Elul) exists; 5785 is a regular year.
        self::assertSame('2024-10-02', Hebrew::createFromFormat('Y-n-j', '5784-13-29', $this->utc)->toGregorian()->format('Y-m-d'));
        self::assertSame('5784/13/29', Hebrew::createFromFormat('Y-n-j', '5784-13-29', $this->utc)->toDateString());
        self::assertSame('5785/12/29', Hebrew::createFromFormat('Y-n-j', '5785-12-29', $this->utc)->toDateString());

        $this->expectException(InvalidDateException::class);
        Hebrew::createFromFormat('Y-n-j', '5785-13-29', $this->utc);
    }

    /** @return iterable<string, array{class-string<Hijri|Hebrew>, string, string, string}> */
    public static function rejected(): iterable
    {
        foreach ([Hijri::class, Hebrew::class] as $class) {
            $y = $class === Hijri::class ? '1446' : '5785';
            yield "$class U with others" => [$class, 'U Y', "1700000000 {$y}", 'U'];
            yield "$class day of year" => [$class, 'Y z', "{$y} 5", 'z'];
            yield "$class zone identifier" => [$class, 'Y/m/d e', "{$y}/01/01 UTC", 'e'];
            yield "$class offset" => [$class, 'Y/m/d P', "{$y}/01/01 +03:30", 'P'];
            yield "$class microseconds" => [$class, 'Y/m/d H:i:s.u', "{$y}/01/01 10:00:00.123456", 'u'];
            yield "$class two-digit year" => [$class, 'y/m/d', '03/01/01', 'y'];
            yield "$class english month" => [$class, 'F j, Y', "March 5, {$y}", 'F'];
            yield "$class english day" => [$class, 'l Y/m/d', "Monday {$y}/01/01", 'l'];
            yield "$class ordinal suffix" => [$class, 'jS Y/m', "1st {$y}/01", 'S'];
        }
    }

    /** @param class-string<Hijri|Hebrew> $class */
    #[DataProvider('rejected')]
    public function test_unsupported_tokens_are_rejected_and_named(string $class, string $format, string $text, string $token): void
    {
        try {
            $class::createFromFormat($format, $text);
            self::fail('should throw');
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
            self::assertStringContainsString("'{$token}'", $e->getMessage());
        }
    }

    public function test_the_u_format_alone_is_a_timestamp(): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');

        foreach ([Hijri::class, Hebrew::class] as $class) {
            self::assertSame(1700000000, $class::createFromFormat('U', '1700000000', $this->utc)->getTimestamp());
            self::assertSame(1700000000, $class::createFromFormat(' U ', ' ۱۷۰۰۰۰۰۰۰۰ ', $tehran)->getTimestamp());
            self::assertSame('Asia/Tehran', $class::createFromFormat('U', '1700000000', $tehran)->getTimezone()->getName());
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function broken(): iterable
    {
        yield 'text' => ['Y/m/d', 'x'];
        yield 'empty' => ['Y/m/d', ''];
        yield 'missing day' => ['Y/m', '1446/09'];
        yield 'extra text' => ['Y/m/d', '1446/09/01 extra'];
        yield 'timestamp text' => ['U', 'abc'];
        yield 'timestamp out of range' => ['U', '99999999999999'];
        yield 'timestamp too long' => ['U', '99999999999999999999'];
        yield 'month 13 in a 12-month calendar' => ['Y-n-j', '1446-13-01'];
        yield 'year out of range' => ['Y-m-d', '0000-01-01'];
        yield 'NUL in the text' => ['Y-m-d', "1446-01-01\0"];
        yield 'NUL in the format' => ["Y-m-d\0", '1446-01-01'];
    }

    #[DataProvider('broken')]
    public function test_bad_input_throws_only_invalid_date_exception(string $format, string $text): void
    {
        foreach ([Hijri::class, Hebrew::class] as $class) {
            try {
                $class::createFromFormat($format, $text);
                self::fail("{$class} should throw");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_jalali_behaves_the_same_after_sharing_the_parser(): void
    {
        self::assertSame('1403/02/31', Jalali::createFromFormat('Y/m/d', '1403/02/31')->toDateString());
        self::assertSame(1700000000, Jalali::createFromFormat('U', '1700000000')->getTimestamp());
    }
}
