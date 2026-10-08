<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Number\Digits;
use RtlyKit\Number\Format;

use function RtlyKit\ordinal;

final class FormatTest extends TestCase
{
    public function test_ordinal_float_handling(): void
    {
        self::assertSame('اول', Format::ordinal(1.0));
        self::assertSame('سوم', Format::ordinal(3.0));
        $this->expectException(InvalidNumberException::class);
        Format::ordinal(2.5);
    }

    public function test_with_separator_digit_cap(): void
    {
        $ok = str_repeat('1', 1000);
        self::assertSame(1000 + 333, preg_match_all('/./u', Format::withSeparator($ok)));

        $this->expectException(InvalidNumberException::class);
        Format::withSeparator(str_repeat('1', 1001));
    }

    public function test_with_separator_rejects_oversized_raw_input(): void
    {
        $this->expectException(InvalidNumberException::class);
        Format::withSeparator(str_repeat('۱', 5000));
    }

    public function test_ordinals(): void
    {
        $expected = [
            0 => 'صفرم', 1 => 'اول', 2 => 'دوم', 3 => 'سوم', 4 => 'چهارم', 10 => 'دهم',
            11 => 'یازدهم', 13 => 'سیزدهم', 20 => 'بیستم', 21 => 'بیست و یکم',
            23 => 'بیست و سوم', 30 => "سی\u{200C}ام", 31 => 'سی و یکم', 33 => 'سی و سوم',
            100 => 'صدم', 103 => 'صد و سوم', 1000 => 'یک هزارم',
        ];
        foreach ($expected as $n => $word) {
            $this->assertSame($word, Format::ordinal($n), (string) $n);
        }
        $this->assertSame('بیست و سوم', ordinal(23));
    }

    public function test_negative_ordinal_throws(): void
    {
        $this->expectException(InvalidNumberException::class);
        Format::ordinal(-1);
    }

    public function test_with_separator(): void
    {
        $this->assertSame("۱\u{066C}۲۳۴\u{066C}۵۶۷", Format::withSeparator(1234567));
        $this->assertSame("۱\u{066C}۲۳۴", Format::withSeparator('1,234'));
        $this->assertSame("۱\u{066C}۲۳۴\u{066B}۵", Format::withSeparator(1234.5));
        $this->assertSame("-۱\u{066C}۲۳۴\u{066C}۵۶۷\u{066B}۸۹۱", Format::withSeparator('-1234567.891'));
        $this->assertSame("۱\u{066C}۲۳۴\u{066B}۵", Format::withSeparator("۱\u{066C}۲۳۴\u{066B}۵"));
        $this->assertSame('۰', Format::withSeparator(0));
        $this->assertSame("۰\u{066B}۵", Format::withSeparator(0.5));
        $this->assertSame('۹۹۹', Format::withSeparator(999));
        $this->assertSame('1,234', Digits::toEnglish(Format::withSeparator(1234, ',')));
        $this->assertSame('۱۲۳۴', Format::withSeparator(1234, ''));
    }

    public function test_with_separator_invalid(): void
    {
        $this->expectException(InvalidNumberException::class);
        Format::withSeparator('abc');
    }

    /* ---------------- Format ---------------- */

    public function test_format_rejects_non_finite_floats(): void
    {
        foreach ([NAN, INF, -INF] as $bad) {
            try {
                Format::withSeparator($bad);
                $this->fail('non-finite float must be rejected');
            } catch (InvalidNumberException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
