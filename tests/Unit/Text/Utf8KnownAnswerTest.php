<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Text\Utf8;

/**
 * Known-answer tests for the UTF-8 helpers: the complete simple lower-case
 * mapping (fixture), the exact byte-level definition of well-formed UTF-8 in
 * RFC 3629 (overlong forms, surrogates, the U+10FFFF ceiling, truncation) and
 * the character counting of truncate().
 */
final class Utf8KnownAnswerTest extends TestCase
{
    private const FFFD = "\u{FFFD}";

    /** @return array<int, string> code point => lower-cased UTF-8 */
    private static function mapping(): array
    {
        /** @var string $table */
        $table = require __DIR__.'/../../Fixtures/utf8-lowercase-mapping.php';
        $map = [];
        foreach (preg_split('/\s+/', trim($table)) ?: [] as $pair) {
            [$from, $to] = explode(':', $pair);
            $out = '';
            foreach (explode('+', $to) as $cp) {
                $out .= self::encode((int) hexdec($cp));
            }
            $map[(int) hexdec($from)] = $out;
        }

        return $map;
    }

    public function test_lower_matches_the_unicode_mapping_for_every_code_point_below_u20000(): void
    {
        $map = self::mapping();
        self::assertCount(1407, $map);

        $input = '';
        $expected = '';
        for ($cp = 0x80; $cp < 0x20000; $cp++) {
            if ($cp >= 0xD800 && $cp <= 0xDFFF) {
                continue;
            }
            $char = self::encode($cp);
            $input .= $char;
            $expected .= $map[$cp] ?? $char;
        }

        $actual = Utf8::lower($input);
        if ($actual !== $expected) {
            // Locate the first difference for a readable failure.
            $i = 0;
            while ($i < strlen($actual) && $i < strlen($expected) && $actual[$i] === $expected[$i]) {
                $i++;
            }
            self::fail('lower() differs from the Unicode mapping at byte '.$i.': got '.bin2hex(substr($actual, $i, 4)).', expected '.bin2hex(substr($expected, $i, 4)));
        }
        self::assertSame(strlen($expected), strlen($actual));
    }

    public function test_lower_leaves_code_points_above_the_mapping_alone(): void
    {
        foreach ([0x20000, 0x2FA1D, 0xE0001, 0xFFFFD, 0x10FFFF] as $cp) {
            $char = self::encode($cp);
            self::assertSame($char, Utf8::lower($char), dechex($cp));
        }
    }

    public function test_lower_does_not_confuse_high_planes_with_plane_one(): void
    {
        // U+10400 (Deseret capital long I) has a lower-case form; the same low 16 bits in
        // planes 4, 8 and 12 do not, and must not be read as plane 1 (lead bytes F1, F2, F3).
        self::assertSame("\u{10428}", Utf8::lower("\u{10400}"));
        foreach ([0x50400, 0x90400, 0xD0400, 0x41900, 0x81900, 0xC1900] as $cp) {
            $char = self::encode($cp);
            self::assertSame($char, Utf8::lower($char), dechex($cp));
        }
        self::assertSame("\u{1E922}\u{50400}\u{1E922}", Utf8::lower("\u{1E900}\u{50400}\u{1E900}"));
    }

    /** @return array<string, array{string, string}> */
    public static function lowerSamples(): array
    {
        return [
            'ascii only' => ['ABC xyz 123 @[`{', 'abc xyz 123 @[`{'],
            'last ascii letters' => ["Z\x7F", "z\x7F"],
            'two byte, first and last of Latin-1 capitals' => ["\u{C0}\u{DE}", "\u{E0}\u{FE}"],
            'multiplication sign is not a letter' => ["\u{D7}", "\u{D7}"],
            'three byte' => ["\u{1E9E}\u{2C00}\u{FF21}", "\u{DF}\u{2C30}\u{FF41}"],
            'four byte' => ["\u{10400}\u{1E900}", "\u{10428}\u{1E922}"],
            'dotted capital I' => ["\u{130}", "i\u{307}"],
            'Greek capital sigma is always medial' => ['ΟΔΥΣΣΕΥΣ', 'οδυσσευσ'],
            'Persian and Arabic are caseless' => ['سلام ۱۲۳ مرحبا', 'سلام ۱۲۳ مرحبا'],
            'mixed' => ['ÉCOLE Привет İSTANBUL', "école привет i\u{307}stanbul"],
            'step-2 ranges: only the even code point is a capital' => ["\u{100}\u{101}\u{102}\u{103}", "\u{101}\u{101}\u{103}\u{103}"],
        ];
    }

    #[DataProvider('lowerSamples')]
    public function test_lower_samples(string $input, string $expected): void
    {
        self::assertSame($expected, Utf8::lower($input));
    }

    public function test_lower_returns_input_unchanged_on_invalid_utf8(): void
    {
        // Documented precondition is valid input; invalid input must not be corrupted into something else.
        self::assertSame("abc\xFF", Utf8::lower("ABC\xFF"));
    }

    /** @return array<string, array{string}> */
    public static function wellFormed(): array
    {
        $cases = [
            "\u{0}", "\u{7F}",
            "\xC2\x80", "\xDF\xBF",
            "\xE0\xA0\x80", "\xE0\xBF\xBF",
            "\xE1\x80\x80", "\xEC\xBF\xBF",
            "\xED\x80\x80", "\xED\x9F\xBF",
            "\xEE\x80\x80", "\xEF\xBF\xBF",
            "\xF0\x90\x80\x80", "\xF0\xBF\xBF\xBF",
            "\xF1\x80\x80\x80", "\xF3\xBF\xBF\xBF",
            "\xF4\x80\x80\x80", "\xF4\x8F\xBF\xBF",
        ];

        $out = [];
        foreach ($cases as $case) {
            $out[bin2hex($case)] = [$case];
        }

        return $out;
    }

    /** @return array<string, array{string}> */
    public static function malformed(): array
    {
        $cases = [
            "\x80", "\xBF", "\xC0\x80", "\xC1\xBF", "\xF5\x80\x80\x80", "\xFF", "\xFE",
            "\xC2", "\xC2\x41", "\xDF\xC0",
            "\xE0\x80\x80", "\xE0\x9F\xBF", "\xE0\xA0", "\xE1\x80", "\xEF\xBF\x41",
            "\xED\xA0\x80", "\xED\xBF\xBF",
            "\xF0\x80\x80\x80", "\xF0\x8F\xBF\xBF", "\xF0\x90\x80", "\xF1\x80\x80", "\xF4\x90\x80\x80", "\xF4\x8F\xBF",
        ];

        $out = [];
        foreach ($cases as $case) {
            $out[bin2hex($case)] = [$case];
        }

        return $out;
    }

    #[DataProvider('wellFormed')]
    public function test_is_valid_accepts_every_boundary_sequence(string $sequence): void
    {
        self::assertTrue(Utf8::isValid($sequence));
        self::assertTrue(Utf8::isValid('a'.$sequence.'b'));
    }

    #[DataProvider('malformed')]
    public function test_is_valid_rejects_malformed_sequences(string $sequence): void
    {
        self::assertFalse(Utf8::isValid($sequence));
        self::assertFalse(Utf8::isValid('a'.$sequence.'b'));
    }

    #[DataProvider('wellFormed')]
    public function test_truncate_keeps_well_formed_sequences_next_to_invalid_bytes(string $sequence): void
    {
        // The stray byte makes the whole string invalid, so the scrubber runs; the valid sequence must survive.
        self::assertSame(self::FFFD.$sequence.self::FFFD, Utf8::truncate("\xFF".$sequence."\xFF", 10));
    }

    #[DataProvider('malformed')]
    public function test_truncate_replaces_every_byte_of_a_malformed_sequence(string $sequence): void
    {
        $result = Utf8::truncate('<'.$sequence.'>', 100);

        self::assertTrue(Utf8::isValid($result));
        self::assertStringStartsWith('<', $result);
        self::assertStringEndsWith('>', $result);
        // The bytes between the markers are replaced one for one, except that a trailing
        // well-formed ASCII byte after a lead byte (e.g. "\xC2A") stays untouched.
        $middle = substr($result, 1, -1);
        self::assertSame(
            preg_match_all('/\x{FFFD}/u', $middle),
            strlen($sequence) - (preg_match('/[\x00-\x7F]$/', $sequence) === 1 ? 1 : 0),
        );
    }

    public function test_truncate_exact_replacements(): void
    {
        self::assertSame(str_repeat(self::FFFD, 3), Utf8::truncate("\xE0\x80\x80", 10));
        self::assertSame(str_repeat(self::FFFD, 3), Utf8::truncate("\xED\xA0\x80", 10));
        self::assertSame(str_repeat(self::FFFD, 4), Utf8::truncate("\xF4\x90\x80\x80", 10));
        self::assertSame(str_repeat(self::FFFD, 4), Utf8::truncate("\xF0\x80\x80\x80", 10));
        self::assertSame(str_repeat(self::FFFD, 2), Utf8::truncate("\xC0\x80", 10));
        self::assertSame(self::FFFD.'A', Utf8::truncate("\xC2A", 10));
        self::assertSame(str_repeat(self::FFFD, 2), Utf8::truncate("\xE2\x82", 10));
        self::assertSame('ok'.str_repeat(self::FFFD, 3), Utf8::truncate("ok\xF0\x9F\x98", 10)); // 3 of the 4 bytes of U+1F600
    }

    public function test_truncate_counts_characters_not_bytes(): void
    {
        self::assertSame('', Utf8::truncate('😀😀', 0));
        self::assertSame('😀', Utf8::truncate('😀😀', 1));
        self::assertSame('😀😀', Utf8::truncate('😀😀', 2));
        self::assertSame('😀😀', Utf8::truncate('😀😀', 3));
        self::assertSame('aé', Utf8::truncate('aéb', 2));
        self::assertSame('aéb', Utf8::truncate('aéb', 3));
        self::assertSame('a€', Utf8::truncate('a€b', 2));
        self::assertSame('a', Utf8::truncate('abc', 1));
        self::assertSame('abc', Utf8::truncate('abc', 3));
        self::assertSame('', Utf8::truncate('', 5));
    }

    public function test_truncate_counts_newlines_as_characters(): void
    {
        self::assertSame("a\nb", Utf8::truncate("a\nb\nc", 3));
        self::assertSame("\n\n", Utf8::truncate("\n\n\n", 2));
        self::assertSame("a\r\nb", Utf8::truncate("a\r\nbc", 4));
    }

    public function test_truncate_treats_a_negative_length_as_zero(): void
    {
        self::assertSame('', Utf8::truncate('abc', -1));
        self::assertSame('', Utf8::truncate('abc', PHP_INT_MIN));
        // A negative length must not turn into a literal pattern that happens to match the text.
        self::assertSame('', Utf8::truncate('.{0,-3}x', -3));
        self::assertSame('', Utf8::truncate('.{0,-1}abc', -1));
        self::assertSame('', Utf8::truncate('.{0,-1}abc', -3));
        self::assertSame('', Utf8::truncate('.{0,0}abc', 0));
    }

    public function test_truncate_accepts_a_very_large_length(): void
    {
        self::assertSame('abc', Utf8::truncate('abc', 40));
        self::assertSame('abc', Utf8::truncate('abc', 65535));
    }

    public function test_truncate_scrubs_before_counting(): void
    {
        // Two stray bytes count as two characters (two U+FFFD), not as zero.
        self::assertSame(self::FFFD.self::FFFD, Utf8::truncate("\xFF\xFEabc", 2));
        self::assertSame(self::FFFD.'a', Utf8::truncate("\xFFabc", 2));
    }

    /** UTF-8 encoding of a code point, written out so the test needs no mbstring. */
    private static function encode(int $cp): string
    {
        return match (true) {
            $cp < 0x80 => chr($cp),
            $cp < 0x800 => chr(0xC0 | ($cp >> 6)).chr(0x80 | ($cp & 0x3F)),
            $cp < 0x10000 => chr(0xE0 | ($cp >> 12)).chr(0x80 | (($cp >> 6) & 0x3F)).chr(0x80 | ($cp & 0x3F)),
            default => chr(0xF0 | ($cp >> 18)).chr(0x80 | (($cp >> 12) & 0x3F)).chr(0x80 | (($cp >> 6) & 0x3F)).chr(0x80 | ($cp & 0x3F)),
        };
    }
}
