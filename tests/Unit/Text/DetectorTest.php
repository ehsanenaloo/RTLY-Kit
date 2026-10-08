<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Text\Detector;

final class DetectorTest extends TestCase
{
    public function test_detector(): void
    {
        $this->assertTrue(Detector::containsRtl('سلام'));
        $this->assertFalse(Detector::containsRtl('Hello'));
        $this->assertSame('rtl', Detector::direction('سلام'));
        $this->assertSame('ltr', Detector::direction('Hello'));
        $this->assertTrue(Detector::isRtlLocale('fa'));
        $this->assertTrue(Detector::isRtlLocale('ar_SA'));
        $this->assertFalse(Detector::isRtlLocale('en'));
    }

    public function test_is_persian(): void
    {
        $this->assertTrue(Detector::isPersian('پارسی'));
        $this->assertTrue(Detector::isPersian('خانه'));          // ه is valid Persian
        $this->assertTrue(Detector::isPersian('گل و چای ۱۲۳'));
        $this->assertFalse(Detector::isPersian('كتاب'));
        $this->assertFalse(Detector::isPersian('مدرسة'));
        $this->assertFalse(Detector::isPersian('Hello'));
        $this->assertFalse(Detector::isPersian(''));
    }

    public function test_is_arabic(): void
    {
        $this->assertTrue(Detector::isArabic('كتاب'));
        $this->assertTrue(Detector::isArabic('اللغة العربية'));
        $this->assertFalse(Detector::isArabic('پارسی'));
        $this->assertFalse(Detector::isArabic('خانه'));
        $this->assertFalse(Detector::isArabic('Hello'));
    }

    public function test_hebrew(): void
    {
        $this->assertTrue(Detector::isHebrew('שלום'));
        $this->assertTrue(Detector::containsRtl('שלום'));
        $this->assertFalse(Detector::isHebrew('سلام'));
    }

    public function test_direction_first_strong(): void
    {
        $this->assertSame('rtl', Detector::direction('سلام Hello'));
        $this->assertSame('ltr', Detector::direction('Hello سلام'));
        $this->assertSame('rtl', Detector::direction('123 ... سلام'));
        $this->assertSame('rtl', Detector::direction('שלום'));
        $this->assertSame('ltr', Detector::direction('12345'));
        $this->assertSame('ltr', Detector::direction(''));
    }

    public function test_contains_rtl_extended_ranges(): void
    {
        $this->assertTrue(Detector::containsRtl("a \u{0780}"));  // Thaana
        $this->assertTrue(Detector::containsRtl("\u{0710}"));    // Syriac
        $this->assertTrue(Detector::containsRtl("\u{0750}"));    // Arabic Supplement
        $this->assertTrue(Detector::containsRtl("\u{08A0}"));    // Arabic Extended-A
        $this->assertFalse(Detector::containsRtl('abc 123'));
    }

    public function test_rtl_locales(): void
    {
        foreach (['fa', 'fa_IR', 'ar-EG', 'he', 'ur', 'ps', 'sd', 'ckb', 'ckb-IQ', 'ug', 'ug-CN', 'dv', 'yi', 'ku-Arab', 'pa-Arab-PK'] as $l) {
            $this->assertTrue(Detector::isRtlLocale($l), $l);
        }
        foreach (['en', 'en_US', 'de', 'ku', 'ku-Latn', 'pa', 'cs', ''] as $l) {
            $this->assertFalse(Detector::isRtlLocale($l), $l);
        }
    }
}
