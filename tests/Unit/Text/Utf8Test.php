<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Text\Utf8;

final class Utf8Test extends TestCase
{
    public function test_is_valid(): void
    {
        self::assertTrue(Utf8::isValid(''));
        self::assertTrue(Utf8::isValid('سلام hello 😀'));
        self::assertFalse(Utf8::isValid("a\xFF"));
        self::assertFalse(Utf8::isValid("\xC0\xAF"));         // overlong
        self::assertFalse(Utf8::isValid("\xED\xA0\x80"));     // surrogate
        self::assertFalse(Utf8::isValid("\xF4\x90\x80\x80")); // above U+10FFFF
        self::assertFalse(Utf8::isValid("\xC3"));             // truncated
    }

    public function test_lower_known_answers(): void
    {
        self::assertSame('hello world', Utf8::lower('HeLLo WORLD'));
        self::assertSame('école', Utf8::lower('ÉCOLE'));
        self::assertSame('привет мир', Utf8::lower('ПРИВЕТ МИР'));
        self::assertSame('αβγ σ', Utf8::lower('ΑΒΓ Σ'));
        self::assertSame("i\u{0307}", Utf8::lower("\u{0130}"));
        self::assertSame('ǆ', Utf8::lower('Ǆ'));
        self::assertSame('ⱥ', Utf8::lower('Ⱥ'));
        self::assertSame("\u{10428}", Utf8::lower("\u{10400}")); // Deseret, 4 bytes
        self::assertSame('سلام ۱۲۳', Utf8::lower('سلام ۱۲۳'));
        self::assertSame('ß', Utf8::lower('ß'));
    }

    public function test_truncate_counts_characters_and_scrubs_invalid_bytes(): void
    {
        self::assertSame('سلا', Utf8::truncate('سلام دنیا', 3));
        self::assertSame('abc', Utf8::truncate('abc', 40));
        self::assertSame('', Utf8::truncate('abc', 0));
        self::assertSame("ab\u{FFFD}c", Utf8::truncate("ab\xFFcd\xC3", 4));
        self::assertSame("a\u{FFFD}", Utf8::truncate("a\xC3", 40));
        self::assertTrue(Utf8::isValid(Utf8::truncate("\xFF\xFE\xC0\xAF", 40)));
    }
}
