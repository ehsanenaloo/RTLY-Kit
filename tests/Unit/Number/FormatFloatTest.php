<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\TestCase;
use RtlyKit\Number\Format;

use function RtlyKit\format_number;

final class FormatFloatTest extends TestCase
{
    public function test_floats_use_the_shortest_round_trip_decimal(): void
    {
        self::assertSame('-۱٬۲۳۴٬۵۶۷٫۸۹۱', Format::withSeparator(-1234567.891));
        self::assertSame('-۱٬۲۳۴٬۵۶۷٫۸۹۱', format_number(-1234567.891));
        self::assertSame('۰٫۱', Format::withSeparator(0.1));
        self::assertSame('۱۲٫۳۴۵۶۷۸۹', Format::withSeparator(12.3456789));
        self::assertSame('۰٫۳', Format::withSeparator(0.1 + 0.2));
        self::assertSame('۵', Format::withSeparator(5.0));
        self::assertSame('۱٬۰۰۰٬۰۰۰٬۰۰۰', Format::withSeparator(1.0e9));
    }

    public function test_floats_are_limited_to_fifteen_significant_digits(): void
    {
        self::assertSame('۰٫۳۳۳۳۳۳۳۳۳۳۳۳۳۳۳', Format::withSeparator(1 / 3));
        self::assertSame('۰٫۶۶۶۶۶۶۶۶۶۶۶۶۶۶۷', Format::withSeparator(2 / 3));
        self::assertSame('۱۲۳٬۴۵۶٬۷۸۹٫۱۲۳۴۵۷', Format::withSeparator(123456789.123456789));
        self::assertSame('۱۲۳٬۴۵۶٬۷۸۹٫۱۲۳۴۵۶۷۸۹', Format::withSeparator('123456789.123456789'));
        self::assertSame('۰٫۱۵', Format::withSeparator(0.1 + 0.05));
    }

    public function test_exponent_forms_are_written_in_full(): void
    {
        self::assertSame('۰٫۰۰۰۰۰۰۱۵', Format::withSeparator(1.5e-7));
        self::assertSame('۱۰'.str_repeat('٬۰۰۰', 8), Format::withSeparator(1.0e25));
        self::assertSame('-۰٫۰۰۰۰۰۰۱', Format::withSeparator(-1.0e-7));
    }

    public function test_negative_zero_is_zero(): void
    {
        self::assertSame('۰', Format::withSeparator(-0.0));
        self::assertSame('۰', Format::withSeparator(0.0));
    }

    public function test_independent_of_serialize_precision_ini(): void
    {
        $old = ini_set('serialize_precision', '17');
        try {
            self::assertSame('۰٫۱', Format::withSeparator(0.1));
            self::assertSame('-۱٬۲۳۴٬۵۶۷٫۸۹۱', Format::withSeparator(-1234567.891));
        } finally {
            ini_set('serialize_precision', $old === false ? '-1' : $old);
        }
    }

    public function test_ints_and_strings_are_unchanged(): void
    {
        self::assertSame('۱٬۲۳۴', Format::withSeparator(1234));
        self::assertSame('۱٬۲۳۴٫۵', Format::withSeparator('1,234.5'));
        self::assertSame('-۱٬۰۰۰', Format::withSeparator(-1000));
    }
}
