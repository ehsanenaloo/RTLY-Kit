<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Text\Slugify;
use RtlyKit\Text\Utf8;

final class Utf8ExcerptAndLowerTest extends TestCase
{
    public function test_excerpt_cuts_cleans_and_marks(): void
    {
        self::assertSame('abc', Utf8::excerpt('abc'));
        self::assertSame('ab...', Utf8::excerpt('abcdef', 2));
        self::assertSame("a\u{FFFD}b", Utf8::excerpt("a\xffb"));
        self::assertSame('a?b?c', Utf8::excerpt("a\nb\x1bc"));
        self::assertSame('a?b', Utf8::excerpt("a\u{2028}b"));
        self::assertSame('', Utf8::excerpt(''));
        self::assertSame(str_repeat('é', 40).'...', Utf8::excerpt(str_repeat('é', 41)));
        self::assertSame(str_repeat('é', 40), Utf8::excerpt(str_repeat('é', 40)));
    }

    public function test_the_ranges_skipped_by_lower_have_no_case(): void
    {
        if (! function_exists('mb_strtolower')) {
            self::markTestSkipped('mbstring is needed as the reference.');
        }

        $cased = [];
        foreach ([[0x0590, 0x08FF], [0xFB1D, 0xFDFF], [0xFE70, 0xFEFF]] as [$from, $to]) {
            for ($cp = $from; $cp <= $to; $cp++) {
                $char = mb_chr($cp, 'UTF-8');
                if (mb_strtolower($char, 'UTF-8') !== $char || mb_strtoupper($char, 'UTF-8') !== $char) {
                    $cased[] = dechex($cp);
                }
            }
        }

        self::assertSame([], $cased);
    }

    public function test_lower_matches_the_unicode_mapping_for_every_code_point(): void
    {
        if (! function_exists('mb_strtolower')) {
            self::markTestSkipped('mbstring is needed as the reference.');
        }
        if (PHP_VERSION_ID >= 80400) {
            self::markTestSkipped('PHP 8.4 and later ship newer Unicode data than the package table.');
        }

        $different = [];
        for ($cp = 0x80; $cp <= 0x10FFFF; $cp++) {
            if ($cp >= 0xD800 && $cp <= 0xDFFF) {
                continue;
            }
            $char = mb_chr($cp, 'UTF-8');
            $expected = mb_strtolower($char, 'UTF-8');
            // The package uses the simple mapping: it differs from mbstring's full mapping only for U+0130.
            if ($cp === 0x130) {
                continue;
            }
            if (Utf8::lower($char) !== $expected) {
                $different[] = dechex($cp);
            }
        }

        self::assertSame([], $different);
    }

    public function test_persian_text_is_fast_and_unchanged(): void
    {
        $text = str_repeat('سلام دنیا ۱۲۳ ABC é', 20_000);
        $start = microtime(true);
        $lower = Utf8::lower($text);

        self::assertSame(str_repeat('سلام دنیا ۱۲۳ abc é', 20_000), $lower);
        self::assertLessThan(5.0, microtime(true) - $start);
    }

    public function test_slugify_caps_the_text(): void
    {
        self::assertSame('a-b', Slugify::make('a b'));
        self::assertNotSame('', Slugify::make(str_repeat('a', Slugify::MAX_TEXT_BYTES)));

        try {
            Slugify::make(str_repeat('a', Slugify::MAX_TEXT_BYTES + 1));
            self::fail('Expected an exception.');
        } catch (RtlyKitException $e) {
            self::assertSame('input_too_long', $e->getErrorCode()->value);
        }
    }
}
