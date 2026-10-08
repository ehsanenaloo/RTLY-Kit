<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Number\Digits;
use RtlyKit\Number\Format;

/**
 * Known-answer boundary cases for Digits and Format.
 */
final class DigitsFormatBoundaryTest extends TestCase
{
    public function test_non_finite_floats_stringify_to_their_names(): void
    {
        $this->assertSame('INF', Digits::toPersian(INF));
        $this->assertSame('-INF', Digits::toPersian(-INF));
        $this->assertSame('NAN', Digits::toPersian(NAN));
        $this->assertSame('INF', Digits::toArabic(INF));
        $this->assertSame('-INF', Digits::toArabic(-INF));
        $this->assertSame('NAN', Digits::toArabic(NAN));
        $this->assertSame('۱.۵', Digits::toPersian(1.5));
        $this->assertSame('١.٥', Digits::toArabic(1.5));
    }

    public function test_convert_target_aliases(): void
    {
        $this->assertSame('۱۲۳', Digits::convert('123', 'persian'));
        $this->assertSame('۱۲۳', Digits::convert('123', 'fa'));
        $this->assertSame('۱۲۳', Digits::convert('123', 'farsi'));
        $this->assertSame('۱۲۳', Digits::convert('123', 'FARSI'));
        $this->assertSame('١٢٣', Digits::convert('123', 'arabic'));
        $this->assertSame('١٢٣', Digits::convert('۱۲۳', 'ar'));
        $this->assertSame('123', Digits::convert('۱۲۳', 'english'));
        $this->assertSame('123', Digits::convert('١٢٣', 'unknown'));
    }

    public function test_with_separator_rejects_trailing_and_leading_garbage(): void
    {
        foreach (['12abc', 'abc12', '1,2x', 'x1.5', '1.5.2', '--1', '1e5', ''] as $bad) {
            try {
                Format::withSeparator($bad);
                $this->fail("expected an exception for '{$bad}'");
            } catch (InvalidNumberException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_with_separator_message_quotes_at_most_forty_characters(): void
    {
        try {
            Format::withSeparator(str_repeat('x', 50));
            $this->fail('expected an exception');
        } catch (InvalidNumberException $e) {
            $this->assertSame("'".str_repeat('x', 40)."' is not a valid number.", $e->getMessage());
        }

        try {
            Format::withSeparator('1.2.3');
            $this->fail('expected an exception');
        } catch (InvalidNumberException $e) {
            $this->assertSame("'1.2.3' is not a valid number.", $e->getMessage());
        }
    }

    public function test_with_separator_strips_leading_zeros_and_trims_whitespace(): void
    {
        $this->assertSame('۷', Format::withSeparator('007'));
        $this->assertSame('۰', Format::withSeparator('000'));
        $this->assertSame('۰٫۵', Format::withSeparator('00.5'));
        $this->assertSame('۱٬۲۳۴', Format::withSeparator("\n1234\t"));
        $this->assertSame('۱٬۲۳۴', Format::withSeparator(' 1 234 '));
        $this->assertSame('-۱٬۲۳۴٬۵۶۷', Format::withSeparator('-1234567'));
    }

    public function test_with_separator_float_forms(): void
    {
        $this->assertSame('۰', Format::withSeparator(0.0));
        $this->assertSame('۰', Format::withSeparator(-0.0));
        $this->assertSame('۱', Format::withSeparator(1.0));
        $this->assertSame('۰٫۱', Format::withSeparator(0.1));
        $this->assertSame('۰٫۲', Format::withSeparator(0.2));
        $this->assertSame('۰٫۳', Format::withSeparator(0.3));
        $this->assertSame('۱۰۰', Format::withSeparator(100.0));
        $this->assertSame('۱٬۰۰۰٬۰۰۰', Format::withSeparator(1.0E6));
        $this->assertSame('۱۲۳٫۴۵', Format::withSeparator(123.45));
        $this->assertSame('-۱٬۲۳۴٬۵۶۷٫۸۹۱', Format::withSeparator(-1234567.891));
        $this->assertSame('۰٫۰۰۰۰۰۱', Format::withSeparator(0.000001));
        $this->assertSame('۱٬۰۰۰٬۰۰۰٬۰۰۰٬۰۰۰٬۰۰۰', Format::withSeparator(1.0E15));
    }

    public function test_with_separator_input_caps_are_exact(): void
    {
        // 4096 bytes is allowed by the byte cap, then fails the 1000-character cap.
        try {
            Format::withSeparator(str_repeat('1', 4096));
            $this->fail('expected an exception');
        } catch (InvalidNumberException $e) {
            $this->assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
            $this->assertSame(['limit' => 1000], $e->getContext());
        }

        try {
            Format::withSeparator(str_repeat('1', 4097));
            $this->fail('expected an exception');
        } catch (InvalidNumberException $e) {
            $this->assertSame(['limit' => 4096], $e->getContext());
        }

        $this->assertSame(1000 + 333, mb_strlen(Format::withSeparator(str_repeat('1', 1000))));
        $this->expectException(InvalidNumberException::class);
        Format::withSeparator(str_repeat('1', 1001));
    }

    public function test_ordinal_boundaries(): void
    {
        $this->assertSame('سوم', Format::ordinal(3.0));
        $this->assertSame('اول', Format::ordinal(1));
        $this->assertSame('صفرم', Format::ordinal(0));

        foreach ([2.5, -1, -0.5, INF, NAN] as $bad) {
            try {
                Format::ordinal($bad);
                $this->fail('expected an exception for '.var_export($bad, true));
            } catch (InvalidNumberException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
