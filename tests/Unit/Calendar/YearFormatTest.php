<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * `Y` is at least 4 digits, zero-padded, with a leading minus for negative
 * years (like PHP); `y` is the last two digits by floor modulo.
 */
final class YearFormatTest extends TestCase
{
    /** @return iterable<string, array{int, string, string}> */
    public static function jalaliYears(): iterable
    {
        yield 'smallest year' => [-620, '-0620', '80'];
        yield 'minus 100' => [-100, '-0100', '00'];
        yield 'minus 5' => [-5, '-0005', '95'];
        yield 'minus 1' => [-1, '-0001', '99'];
        yield 'zero' => [0, '0000', '00'];
        yield 'five' => [5, '0005', '05'];
        yield 'three digits' => [622, '0622', '22'];
        yield 'four digits' => [1403, '1403', '03'];
        yield 'largest year' => [9377, '9377', '77'];
    }

    #[DataProvider('jalaliYears')]
    public function test_jalali_year_tokens(int $year, string $long, string $short): void
    {
        $date = Jalali::create($year, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));

        self::assertSame($long, $date->format('Y'));
        self::assertSame($short, $date->format('y'));
        self::assertSame("{$long}/01/01", $date->toDateString());
        self::assertSame("{$long}/01/01 00:00:00", (string) $date);
        self::assertSame("{$long}/01/01 00:00:00", $date->jsonSerialize());
        self::assertSame("{$long}-01-01T00:00:00+00:00", $date->format('c'));
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function hijriYears(): iterable
    {
        yield 'first year' => [1, '0001', '01'];
        yield 'three digits' => [622, '0622', '22'];
        yield 'current' => [1446, '1446', '46'];
        yield 'largest' => [9665, '9665', '65'];
    }

    #[DataProvider('hijriYears')]
    public function test_hijri_year_tokens(int $year, string $long, string $short): void
    {
        $date = Hijri::create($year, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));

        self::assertSame($long, $date->format('Y'));
        self::assertSame($short, $date->format('y'));
        self::assertSame("{$long}-01-01T00:00:00+00:00", $date->format('c'));
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function hebrewYears(): iterable
    {
        yield 'smallest' => [3762, '3762', '62'];
        yield 'current' => [5785, '5785', '85'];
        yield 'five digits' => [10000, '10000', '00'];
        yield 'largest' => [13759, '13759', '59'];
    }

    #[DataProvider('hebrewYears')]
    public function test_hebrew_year_tokens(int $year, string $long, string $short): void
    {
        $date = Hebrew::create($year, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));

        self::assertSame($long, $date->format('Y'));
        self::assertSame($short, $date->format('y'));
        self::assertSame("{$long}-01-01T00:00:00+00:00", $date->format('c'));
    }

    public function test_make_reads_back_what_to_date_string_prints_within_the_own_window(): void
    {
        $utc = new DateTimeZone('UTC');

        foreach ([0, 5, 99, 622, 1403, 1699] as $year) {
            $date = Jalali::create($year, 2, 3, 4, 5, 6, $utc);
            self::assertSame($date->getTimestamp(), Jalali::make($date->toDateTimeString(), $utc)->getTimestamp(), "Jalali {$year}");
        }
        foreach ([1, 5, 622, 1446, 1699] as $year) {
            $date = Hijri::create($year, 2, 3, 4, 5, 6, $utc);
            self::assertSame($date->getTimestamp(), Hijri::make($date->toDateTimeString(), $utc)->getTimestamp(), "Hijri {$year}");
        }
        foreach ([3762, 5785, 9999, 10000, 13759] as $year) {
            $date = Hebrew::create($year, 2, 3, 4, 5, 6, $utc);
            self::assertSame($date->getTimestamp(), Hebrew::make($date->toDateTimeString(), $utc)->getTimestamp(), "Hebrew {$year}");
        }
    }

    public function test_negative_jalali_years_are_not_readable_from_text_and_fail_cleanly(): void
    {
        foreach ([-100, -5, -620] as $year) {
            $text = Jalali::create($year, 1, 1)->toDateString();
            try {
                Jalali::make($text);
                self::fail("make('{$text}') should throw");
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InvalidDate, $e->getErrorCode());
                self::assertStringContainsString('negative years', $e->getMessage());
            }
        }

        // The instant still round-trips through a timestamp.
        $date = Jalali::create(-100, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));
        self::assertSame('-0100/01/01', Jalali::make($date->getTimestamp(), new DateTimeZone('UTC'))->toDateString());
    }
}
