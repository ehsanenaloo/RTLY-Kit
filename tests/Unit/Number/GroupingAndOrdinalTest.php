<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;
use RtlyKit\Validation\Input;
use RtlyKit\Validation\Result;

final class GroupingAndOrdinalTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function properGrouping(): array
    {
        return [
            'spaces' => ['1 234 567', 'یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت'],
            'commas' => ['1,234,567', 'یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت'],
            'arabic thousands separator' => ['1٬234٬567', 'یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت'],
            'persian digits' => ['۱ ۲۳۴', 'یک هزار و دویست و سی و چهار'],
            'no-break space' => ["12\u{00A0}345", 'دوازده هزار و سیصد و چهل و پنج'],
            'signed' => ['-1 000', 'منفی یک هزار'],
            'outer spaces' => ['  1 000 ', 'یک هزار'],
            'mixed separators' => ['1,000 000', 'یک میلیون'],
            'plain' => ['1234567', 'یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت'],
        ];
    }

    #[DataProvider('properGrouping')]
    public function test_convert_accepts_proper_grouping(string $input, string $words): void
    {
        self::assertSame($words, NumberToWords::convert($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badGrouping(): array
    {
        return [
            'one one' => ['1 2'],
            'two and two' => ['12 34'],
            'first group too long' => ['1234 567'],
            'last group short' => ['1,23'],
            'last group long' => ['1 2345'],
            'leading separator after sign' => ['1,,000'],
            'trailing separator' => ['1,000,'],
            'persian short group' => ['۱ ۲'],
            'tab' => ["1\t000"],
            'decimal point' => ['1 000.5'],
        ];
    }

    #[DataProvider('badGrouping')]
    public function test_convert_rejects_improper_grouping(string $input): void
    {
        try {
            NumberToWords::convert($input);
            self::fail("'$input' must be rejected.");
        } catch (InvalidNumberException $e) {
            self::assertSame(ErrorCode::InvalidNumber, $e->getErrorCode());
        }
    }

    public function test_arabic_convert_uses_the_same_rule(): void
    {
        self::assertSame('ألف ومئتان وأربعة وثلاثون', NumberToWords::convert('1 234', 'ar'));
        $this->expectException(InvalidNumberException::class);
        NumberToWords::convert('1 2', 'ar');
    }

    public function test_format_with_separator_uses_the_same_rule(): void
    {
        self::assertSame('۱٬۲۳۴٬۵۶۷', Format::withSeparator('1 234 567'));
        self::assertSame('۱٬۲۳۴٬۵۶۷', Format::withSeparator('1,234,567'));
        self::assertSame('۱٬۲۳۴٬۵۶۷', Format::withSeparator('۱٬۲۳۴٬۵۶۷'));
        self::assertSame('۱٬۲۳۴٫۵', Format::withSeparator('1,234.5'));
        self::assertSame('۱٬۲۳۴٫۵', Format::withSeparator('۱٬۲۳۴٫۵'));
        self::assertSame('۱۲', Format::withSeparator('12'));
        self::assertSame('۱۲', Format::withSeparator(' 12 '));
        self::assertSame('-۱٬۰۰۰', Format::withSeparator('-1 000'));

        foreach (['1 2', '12 34', '1234 567', '1,23', '1.000,5', '1.2 3', '1 000.5 5'] as $bad) {
            try {
                Format::withSeparator($bad);
                self::fail("'$bad' must be rejected.");
            } catch (InvalidNumberException $e) {
                self::assertSame(ErrorCode::InvalidNumber, $e->getErrorCode(), $bad);
            }
        }
    }

    public function test_empty_options_array_means_no_options_for_both_locales(): void
    {
        self::assertSame(NumberToWords::convert(12), NumberToWords::convert(12, 'fa', []));
        self::assertSame(NumberToWords::convert(12, 'ar'), NumberToWords::convert(12, 'ar', []));
        self::assertSame(NumberToWords::ordinal(3), NumberToWords::ordinal(3, 'ar', []));
    }

    public function test_non_empty_options_with_persian_still_throw(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::convert(12, 'fa', ['gender' => 'f']);
    }

    public function test_ordinal_accepts_whole_floats(): void
    {
        self::assertSame(NumberToWords::ordinal(3), NumberToWords::ordinal(3.0));
        self::assertSame('الأول', NumberToWords::ordinal(1.0));
        self::assertSame('الحادي والعشرون', NumberToWords::ordinal(21.0));
    }

    /**
     * @return array<string, array{float, ErrorCode}>
     */
    public static function badFloats(): array
    {
        return [
            'fraction' => [1.5, ErrorCode::InvalidNumber],
            'tiny fraction' => [0.1, ErrorCode::InvalidNumber],
            'nan' => [NAN, ErrorCode::NonFiniteNumber],
            'inf' => [INF, ErrorCode::NonFiniteNumber],
            'minus inf' => [-INF, ErrorCode::NonFiniteNumber],
        ];
    }

    #[DataProvider('badFloats')]
    public function test_ordinal_rejects_fractional_and_non_finite_floats(float $value, ErrorCode $code): void
    {
        try {
            NumberToWords::ordinal($value);
            self::fail('Must throw.');
        } catch (InvalidNumberException $e) {
            self::assertSame($code, $e->getErrorCode());
        }
    }

    public function test_ordinal_float_out_of_range_keeps_the_existing_errors(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::ordinal(100.0);
    }

    /**
     * Whole floats from 1e14 up to (not including) 1e15 are written as plain integers, never as 1.0E+14.
     */
    public function test_validator_input_float_below_1e15_is_a_plain_integer(): void
    {
        self::assertSame('100000000000000', Input::coerce(1.0E+14));
        self::assertSame('123456789012345', Input::coerce(123456789012345.0));
        self::assertSame('999999999999999', Input::coerce(999999999999999.0));
        self::assertSame('-100000000000000', Input::coerce(-1.0E+14));
        self::assertSame('12', Input::coerce(12.0));
        self::assertSame('0', Input::coerce(-0.0));
        self::assertSame('7', Input::coerce(7));

        foreach ([1.0E+15, 1.0E+20, -1.0E+15, 1.5, NAN, INF] as $bad) {
            $result = Input::coerce($bad);
            self::assertInstanceOf(Result::class, $result);
            self::assertSame(['invalid_type'], $result->errors());
        }
    }
}
