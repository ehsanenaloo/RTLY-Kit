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

    /**
     * Wherever the running PHP knows a lower-case form (its Unicode data depends on the PHP version),
     * the package gives the same one. Characters that only the package's newer Unicode data knows are
     * all at U+1C89 or above and there are at most 75 of them.
     */
    public function test_lower_agrees_with_mbstring_where_mbstring_knows_a_mapping(): void
    {
        if (! function_exists('mb_strtolower')) {
            self::markTestSkipped('mbstring is needed as the reference.');
        }

        $different = [];
        $newer = [];
        for ($cp = 0x80; $cp <= 0x10FFFF; $cp++) {
            if (($cp >= 0xD800 && $cp <= 0xDFFF) || $cp === 0x130) {
                continue; // surrogates; U+0130 is "i" + U+0307 in both
            }
            $char = mb_chr($cp, 'UTF-8');
            $reference = mb_strtolower($char, 'UTF-8');
            $ours = Utf8::lower($char);
            if ($reference !== $char) {
                if ($ours !== $reference) {
                    $different[] = dechex($cp);
                }
            } elseif ($ours !== $char) {
                $newer[] = $cp;
            }
        }

        self::assertSame([], $different);
        self::assertLessThanOrEqual(75, count($newer));
        foreach ($newer as $cp) {
            self::assertGreaterThanOrEqual(0x1C89, $cp);
        }
    }

    public function test_characters_added_in_recent_unicode_versions_are_lowered(): void
    {
        foreach ([
            [0x1C89, 0x1C8A],
            [0xA7CB, 0x0264],
            [0xA7CC, 0xA7CD],
            [0xA7DC, 0x019B],
            [0xAB6C, 0xAB4B],
            [0x10D50, 0x10D70],
            [0x10D65, 0x10D85],
            [0x16EA0, 0x16EBB],
            [0x0041, 0x0061],
            [0x00C9, 0x00E9],
            [0x0416, 0x0436],
            [0x03A3, 0x03C3],
        ] as [$upper, $lower]) {
            self::assertSame(self::chr($lower), Utf8::lower(self::chr($upper)), sprintf('U+%04X', $upper));
            self::assertSame(self::chr($lower), Utf8::lower(self::chr($lower)), sprintf('U+%04X', $lower));
        }
        self::assertSame("i\u{0307}", Utf8::lower("\u{0130}"));
    }

    public function test_the_lower_table_is_sorted_and_does_not_overlap(): void
    {
        /** @var list<array{int, int, int, int}> $table */
        $table = (new \ReflectionClassConstant(Utf8::class, 'LOWER'))->getValue();
        $previousLast = 0x7F;
        foreach ($table as [$first, $last, $step, $delta]) {
            self::assertGreaterThan($previousLast, $first);
            self::assertGreaterThanOrEqual($first, $last);
            self::assertContains($step, [1, 2]);
            self::assertNotSame(0, $delta);
            $previousLast = $last;
        }
        self::assertGreaterThan(1400, array_sum(array_map(static fn (array $r): int => intdiv($r[1] - $r[0], $r[2]) + 1, $table)));
    }

    private static function chr(int $cp): string
    {
        return html_entity_decode('&#'.$cp.';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
