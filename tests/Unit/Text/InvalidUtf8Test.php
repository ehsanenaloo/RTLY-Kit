<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Text\Detector;
use RtlyKit\Text\Normalizer;
use RtlyKit\Text\Utf8;

/**
 * Invalid UTF-8 is scrubbed to U+FFFD (one per bad byte) and then every step runs.
 */
final class InvalidUtf8Test extends TestCase
{
    public function test_scrub_known_answers(): void
    {
        self::assertSame("a\u{FFFD}b", Utf8::scrub("a\xFFb"));
        self::assertSame("\u{FFFD}\u{FFFD}", Utf8::scrub("\xC3\x28") === "\u{FFFD}(" ? "\u{FFFD}\u{FFFD}" : '?');
        self::assertSame("\u{FFFD}(", Utf8::scrub("\xC3\x28"));
        self::assertSame("سلام \u{FFFD}", Utf8::scrub("سلام \x80"));
        self::assertSame('سلام', Utf8::scrub('سلام'));
        // A truncated sequence: ی is DB 8C, the lone DB is one bad byte.
        self::assertSame("\u{FFFD}", Utf8::scrub("\xDB"));
    }

    public function test_normalize_runs_all_steps_around_a_bad_byte(): void
    {
        // Arabic letters, a bad byte, tatweel, harakat and extra spaces: all steps apply.
        self::assertSame("علی \u{FFFD} کتاب ۱۲۳", Normalizer::normalize("علي  \xFF  كــتاب ١٢٣"));
        self::assertSame("علی\u{FFFD}", Normalizer::normalize("  عَلِي\xFF  "));
        self::assertSame("a\u{FFFD}b", Normalizer::normalize("a\xFFb"));
    }

    public function test_fix_half_space_and_clean_run_all_steps_around_a_bad_byte(): void
    {
        self::assertSame("می\u{200C}روم \u{FFFD}", Normalizer::fixHalfSpace("می\u{200C}\u{200C}روم \xFF\u{200C}"));
        self::assertSame("کتاب \u{FFFD} می\u{200C}روم", Normalizer::clean("\u{200B}كتاب \xFF  می\u{200C}\u{200C}روم\u{200C}"));
    }

    public function test_results_are_valid_utf8_and_idempotent(): void
    {
        foreach (["\xFF", "\xC3", "a\xE0\x80b", "\xF0\x9F", "ی\xDB", "\xED\xA0\x80"] as $input) {
            foreach ([Normalizer::normalize($input), Normalizer::fixHalfSpace($input), Normalizer::clean($input)] as $out) {
                self::assertTrue(Utf8::isValid($out), bin2hex($input));
            }
            self::assertSame(Normalizer::clean($input), Normalizer::clean(Normalizer::clean($input)), bin2hex($input));
        }
    }

    public function test_detector_ignores_the_bad_bytes_instead_of_failing(): void
    {
        self::assertTrue(Detector::containsRtl("a\xFF سلام"));
        self::assertTrue(Detector::containsRtl("\xFF\xFEسلام"));
        self::assertSame('rtl', Detector::direction("\xFF سلام hello"));
        self::assertSame('ltr', Detector::direction("hello \xFF سلام"));
        self::assertTrue(Detector::isPersian("\xFF گچپژ"));
        self::assertTrue(Detector::isArabic("\xFF كتاب"));
        self::assertTrue(Detector::isHebrew("\xFF שלום"));
        self::assertFalse(Detector::containsRtl("abc\xFF"));
        self::assertSame('ltr', Detector::direction("\xFF"));
    }

    public function test_contains_rtl_counts_explicit_rtl_controls(): void
    {
        self::assertTrue(Detector::containsRtl("abc\u{202B}def"));  // RLE
        self::assertTrue(Detector::containsRtl("abc\u{202E}def"));  // RLO
        self::assertTrue(Detector::containsRtl("abc\u{2067}def"));  // RLI
        self::assertTrue(Detector::containsRtl("abc\u{200F}def"));  // RLM
        self::assertTrue(Detector::containsRtl("abc\u{061C}def"));  // ALM
        // Left-to-right controls and the LRM mark are not RTL.
        self::assertFalse(Detector::containsRtl("abc\u{202A}\u{202D}\u{2066}\u{200E}def"));
        // direction() keeps first-strong-letter semantics: a control is not a letter.
        self::assertSame('ltr', Detector::direction("\u{202B}hello"));
        self::assertSame('rtl', Detector::direction("\u{202B}سلام"));
    }
}
