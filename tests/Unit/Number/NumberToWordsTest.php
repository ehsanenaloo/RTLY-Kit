<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Number\NumberToWords;

use function RtlyKit\number_to_words;

final class NumberToWordsTest extends TestCase
{
    public function test_fractional_float_is_rejected_not_truncated(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::convert(1.5);
    }

    public function test_nan_and_infinity_are_rejected(): void
    {
        foreach ([NAN, INF, -INF] as $bad) {
            try {
                NumberToWords::convert($bad);
                self::fail('Expected InvalidNumberException');
            } catch (InvalidNumberException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_integral_float_is_accepted(): void
    {
        self::assertSame('سه', NumberToWords::convert(3.0));
        self::assertSame('منفی دو', NumberToWords::convert(-2.0));
        self::assertSame('صفر', NumberToWords::convert(-0.0));
        self::assertSame('ده', NumberToWords::convert(10.0, 'fa'));
        self::assertSame('ثلاثة', NumberToWords::convert(3.0, 'ar'));
    }

    public function test_number_to_words_rejects_oversized_string(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::convert(str_repeat('0', 5000).'1');
    }

    public function test_from_words_rejects_oversized_input(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::fromWords(str_repeat('یک ', 3000));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function arabic(): array
    {
        return [
            '0' => [0, 'صفر'],
            '1' => [1, 'واحد'],
            '2' => [2, 'اثنان'],
            '10' => [10, 'عشرة'],
            '11' => [11, 'أحد عشر'],
            '12' => [12, 'اثنا عشر'],
            '13' => [13, 'ثلاثة عشر'],
            '19' => [19, 'تسعة عشر'],
            '20' => [20, 'عشرون'],
            '21' => [21, 'واحد وعشرون'],
            '99' => [99, 'تسعة وتسعون'],
            '100' => [100, 'مئة'],
            '101' => [101, 'مئة وواحد'],
            '200' => [200, 'مئتان'],
            '300' => [300, 'ثلاثمئة'],
            '999' => [999, 'تسعمئة وتسعة وتسعون'],
            '1000' => [1000, 'ألف'],
            '1234' => [1234, 'ألف ومئتان وأربعة وثلاثون'],
            '2000' => [2000, 'ألفان'],
            '3000' => [3000, 'ثلاثة آلاف'],
            '10000' => [10000, 'عشرة آلاف'],
            '11000' => [11000, 'أحد عشر ألف'],
            '21000' => [21000, 'واحد وعشرون ألف'],
            '100000' => [100000, 'مئة ألف'],
            '200000' => [200000, 'مئتا ألف'],
            '999999' => [999999, 'تسعمئة وتسعة وتسعون ألف وتسعمئة وتسعة وتسعون'],
            '1000000' => [1000000, 'مليون'],
            '2000000' => [2000000, 'مليونان'],
            '5000000' => [5000000, 'خمسة ملايين'],
            '1000001' => [1000001, 'مليون وواحد'],
            '999999999' => [999999999, 'تسعمئة وتسعة وتسعون مليون وتسعمئة وتسعة وتسعون ألف وتسعمئة وتسعة وتسعون'],
            '-5' => [-5, 'سالب خمسة'],
            '-1234' => [-1234, 'سالب ألف ومئتان وأربعة وثلاثون'],
        ];
    }

    #[DataProvider('arabic')]
    public function test_arabic(int $n, string $words): void
    {
        self::assertSame($words, NumberToWords::convert($n, 'ar'));
        self::assertSame($words, number_to_words($n, 'ar'));
    }

    public function test_arabic_accepts_digit_strings_and_locale_variants(): void
    {
        self::assertSame('واحد وعشرون', NumberToWords::convert('٢١', 'ar'));
        self::assertSame('واحد وعشرون', NumberToWords::convert(21, 'ar_SA'));
    }

    public function test_persian_default_unchanged(): void
    {
        self::assertSame('بیست و یک', NumberToWords::convert(21));
        self::assertSame('بیست و یک', number_to_words(21));
        self::assertSame('بیست و یک', number_to_words(21, 'fa'));
    }

    public function test_arabic_too_large(): void
    {
        // The former limit of 10^9 was lifted: Arabic now covers every integer below 10^27.
        self::assertSame('مليار', NumberToWords::convert(1_000_000_000, 'ar'));

        $this->expectException(InvalidNumberException::class);
        NumberToWords::convert('1'.str_repeat('0', 27), 'ar');
    }

    public function test_unsupported_locale(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumberToWords::convert(1, 'xx');
    }

    public function test_large_numbers(): void
    {
        $this->assertSame('یک میلیون', NumberToWords::convert(1_000_000));
        $this->assertSame('یک تریلیون', NumberToWords::convert(1_000_000_000_000));
        $this->assertSame('یک کوادریلیون', NumberToWords::convert(1_000_000_000_000_000));
        $this->assertSame('یک کوینتیلیون', NumberToWords::convert(1_000_000_000_000_000_000));
        $this->assertSame(
            'نه کوینتیلیون و دویست و بیست و سه کوادریلیون و سیصد و هفتاد و دو تریلیون و سی و شش میلیارد و هشتصد و پنجاه و چهار میلیون و هفتصد و هفتاد و پنج هزار و هشتصد و هفت',
            NumberToWords::convert(PHP_INT_MAX),
        );
        $this->assertSame('یک میلیون و یک', NumberToWords::convert('1000001'));
    }

    public function test_negative_and_int_min(): void
    {
        $this->assertSame('منفی پنج', NumberToWords::convert(-5));
        $this->assertStringStartsWith('منفی نه کوینتیلیون و دویست و بیست و سه', NumberToWords::convert(PHP_INT_MIN));
        $this->assertSame('منفی صد', NumberToWords::convert('-100'));
        $this->assertSame('صفر', NumberToWords::convert('-0'));
    }

    public function test_string_input(): void
    {
        $this->assertSame('یک هزار و دویست و سی و چهار', NumberToWords::convert('۱۲۳۴'));
        $this->assertSame('یک هزار و دویست و سی و چهار', NumberToWords::convert('1,234'));
        $this->assertSame('پنج', NumberToWords::convert('005'));
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_input_throws(string $input): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::convert($input);
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'letters' => ['abc'],
            'decimal' => ['12.5'],
            'empty' => [''],
            'mixed' => ['12a'],
            'too large' => [str_repeat('9', 22)],
        ];
    }

    public function test_from_words(): void
    {
        $this->assertSame(0, NumberToWords::fromWords('صفر'));
        $this->assertSame(21, NumberToWords::fromWords('بیست و یک'));
        $this->assertSame(1000, NumberToWords::fromWords('هزار'));
        $this->assertSame(1234, NumberToWords::fromWords('یک هزار و دویست و سی و چهار'));
        $this->assertSame(-5, NumberToWords::fromWords('منفی پنج'));
        $this->assertSame(1234, NumberToWords::fromWords('يك هزار و دويست و سي و چهار'));
        $this->assertSame('99999999999999999999', NumberToWords::fromWords(NumberToWords::convert('99999999999999999999')));
    }

    public function test_from_words_round_trip(): void
    {
        $samples = [0, 1, 9, 10, 11, 19, 20, 99, 100, 101, 110, 999, 1000, 1001, 12345, 100000, 1000000, 2_000_300, 123_456_789, 10 ** 12 + 5, PHP_INT_MAX, PHP_INT_MIN + 1, -42];
        foreach ($samples as $n) {
            $this->assertSame($n, NumberToWords::fromWords(NumberToWords::convert($n)), (string) $n);
        }
        $this->assertSame(PHP_INT_MIN, NumberToWords::fromWords(NumberToWords::convert(PHP_INT_MIN)));
    }

    public function test_from_words_invalid(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::fromWords('سلام');
    }

    public function test_from_words_scale_order(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::fromWords('یک میلیون و دو میلیارد');
    }

    /* ---------------- NumberToWords::fromWords ---------------- */

    public function test_from_words_rejects_empty_and_whitespace_input(): void
    {
        foreach (['', '   ', "\u{200C}"] as $in) {
            try {
                NumberToWords::fromWords($in);
                $this->fail('empty words must be rejected');
            } catch (InvalidNumberException $e) {
                $this->assertSame('Empty number words.', $e->getMessage());
            }
        }
    }

    public function test_from_words_rejects_sign_only_input(): void
    {
        $this->expectException(InvalidNumberException::class);
        $this->expectExceptionMessage('No number words found.');
        NumberToWords::fromWords('منفی');
    }

    public function test_from_words_rejects_group_above_999(): void
    {
        $this->expectException(InvalidNumberException::class);
        NumberToWords::fromWords('نهصد و نود و نه یک');
    }

    public function test_from_words_round_trips_known_numbers(): void
    {
        $this->assertSame(1234567, NumberToWords::fromWords('یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت'));
        $this->assertSame(-5, NumberToWords::fromWords('منفی پنج'));
    }

    public function test_number_to_words(): void
    {
        $this->assertSame('صفر', NumberToWords::convert(0));
        $this->assertSame('یک', NumberToWords::convert(1));
        $this->assertSame('ده', NumberToWords::convert(10));
        $this->assertSame('یازده', NumberToWords::convert(11));
        $this->assertSame('بیست', NumberToWords::convert(20));
        $this->assertSame('بیست و یک', NumberToWords::convert(21));
        $this->assertSame('صد', NumberToWords::convert(100));
        $this->assertSame('یک هزار', NumberToWords::convert(1000));
        $this->assertSame('یک هزار و دویست و سی و چهار', NumberToWords::convert(1234));
    }
}
