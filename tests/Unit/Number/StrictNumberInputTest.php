<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Exceptions\UnsupportedLocaleException;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;

final class StrictNumberInputTest extends TestCase
{
    public function test_with_separator_rejects_control_characters_hidden_behind_separators(): void
    {
        foreach (["1234\n\u{00A0}", "12\n,", "1234\n", "1234\0", "\t1234", "12\n34"] as $bad) {
            try {
                Format::withSeparator($bad);
                self::fail('expected InvalidNumberException for '.bin2hex($bad));
            } catch (InvalidNumberException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_with_separator_negative_zero_has_no_sign(): void
    {
        self::assertSame('۰', Format::withSeparator('-0'));
        self::assertSame('۰', Format::withSeparator('-000'));
        self::assertSame('۰٫۰', Format::withSeparator('-0.0'));
        self::assertSame('۰', Format::withSeparator(-0.0));
        self::assertSame('۰', Format::withSeparator('+0'));
        // Not zero: the sign stays.
        self::assertSame('-۰٫۱', Format::withSeparator('-0.1'));
        self::assertSame('-۱', Format::withSeparator('-1'));
        self::assertSame('-۱٬۲۳۴', Format::withSeparator('-۱۲۳۴'));
    }

    public function test_convert_rejects_trailing_control_characters(): void
    {
        foreach (["12\n,", "12\n", "12\0", "12\n\u{00A0}", "\n12"] as $bad) {
            try {
                NumberToWords::convert($bad);
                self::fail('expected InvalidNumberException for '.bin2hex($bad));
            } catch (InvalidNumberException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame('دوازده', NumberToWords::convert('12'));
        // Outer spaces are ignored; a separator inside the digits must be proper thousands grouping ("1,2" is not).
        self::assertSame('دوازده', NumberToWords::convert(' 12 '));
        self::assertSame('یک هزار و دویست و سی و چهار', NumberToWords::convert('1٬234'));
    }

    public function test_from_words_on_invalid_utf8_reports_the_encoding(): void
    {
        foreach (["\xff", "دو\xC3\x28", "\x80\x81"] as $bad) {
            try {
                NumberToWords::fromWords($bad);
                self::fail('expected InvalidNumberException');
            } catch (InvalidNumberException $e) {
                self::assertSame(ErrorCode::InvalidNumberWords, $e->getErrorCode());
                self::assertStringContainsString('UTF-8', $e->getMessage());
                self::assertStringNotContainsString('Empty', $e->getMessage());
            }
        }
    }

    public function test_locale_matches_the_primary_language_subtag_exactly(): void
    {
        foreach (['fa', 'FA', 'fa_IR', 'FA_ir', 'fa-IR'] as $locale) {
            self::assertSame('بیست و یک', NumberToWords::convert(21, $locale), $locale);
        }
        foreach (['ar', 'AR', 'ar_SA', 'ar-EG', 'ar.UTF-8'] as $locale) {
            self::assertSame('واحد وعشرون', NumberToWords::convert(21, $locale), $locale);
        }
        foreach (['arn', 'arz', 'faa', 'farsi', 'fas', 'arabic', '-ar', '_fa', 'f', 'a', ''] as $locale) {
            try {
                NumberToWords::convert(21, $locale);
                self::fail("expected UnsupportedLocaleException for '{$locale}'");
            } catch (UnsupportedLocaleException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_arabic_ordinal_locale_is_also_exact(): void
    {
        self::assertSame('الأول', NumberToWords::ordinal(1, 'ar-SA'));
        $this->expectException(UnsupportedLocaleException::class);
        NumberToWords::ordinal(1, 'arn');
    }
}
