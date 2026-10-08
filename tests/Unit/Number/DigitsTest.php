<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\TestCase;
use RtlyKit\Number\Digits;

final class DigitsTest extends TestCase
{
    public function test_arabic_to_persian(): void
    {
        $this->assertSame('۱۲۳ 456', Digits::arabicToPersian('١٢٣ 456'));
    }

    /* ---------------- Digits::convert ---------------- */

    public function test_digits_convert_targets(): void
    {
        $this->assertSame('۱۲۳', Digits::convert('123', 'persian'));
        $this->assertSame('۱۲۳', Digits::convert('123', 'FA'));
        $this->assertSame('١٢٣', Digits::convert('۱۲۳', 'ar'));
        $this->assertSame('123', Digits::convert('۱۲۳', 'english'));
        $this->assertSame('123', Digits::convert('١٢٣', 'unknown-target'), 'unknown targets fall back to English');
    }

    public function test_digits_conversion(): void
    {
        $this->assertSame('۱۲۳', Digits::toPersian(123));
        $this->assertSame('١٢٣', Digits::toArabic(123));
        $this->assertSame('123', Digits::toEnglish('۱۲۳'));
        $this->assertSame('123', Digits::toEnglish('١٢٣'));
        $this->assertSame('۱۲۳', Digits::convert('123', 'persian'));
    }
}
