<?php

declare(strict_types=1);

namespace RtlyKit\Text;

use RtlyKit\Number\Digits;

/**
 * Persian / Arabic text normalization utilities.
 */
final class Normalizer
{
    private const ZWNJ = "\u{200C}";

    /**
     * Arabic letter variants → their Persian counterparts.
     * Hamza on its own (ء) and ئ are intentionally left untouched.
     */
    private const LETTER_MAP = [
        "\u{0643}" => "\u{06A9}", // ك → ک
        "\u{064A}" => "\u{06CC}", // ي → ی
        "\u{0649}" => "\u{06CC}", // ى → ی
        "\u{06D2}" => "\u{06CC}", // ے → ی
        "\u{0629}" => "\u{0647}", // ة → ه
        "\u{06C0}" => "\u{0647}", // ۀ → ه
        "\u{0624}" => "\u{0648}", // ؤ → و
        "\u{0625}" => "\u{0627}", // إ → ا
        "\u{0623}" => "\u{0627}", // أ → ا
        "\u{0671}" => "\u{0627}", // ٱ → ا
    ];

    /**
     * Normalize Arabic characters to Persian equivalents and clean text.
     *
     * - Arabic ك ي ى ة ؤ إ أ → Persian equivalents
     * - Arabic-Indic digits (٠-٩) → Persian digits (۰-۹); English digits are untouched
     * - tatweel (U+0640) removed; harakat (U+064B–U+065F, U+0670) removed unless disabled
     * - runs of whitespace collapsed and trimmed
     *
     * Invalid UTF-8: every byte that is not part of a well-formed sequence is first replaced by
     * U+FFFD (the replacement character), then all the steps above run. The result is always valid
     * UTF-8. This holds for {@see fixHalfSpace()} and {@see clean()} too. mbstring is not required.
     */
    public static function normalize(string $text, bool $removeDiacritics = true): string
    {
        $text = Utf8::scrub($text);
        $text = strtr($text, self::LETTER_MAP);
        $text = Digits::arabicToPersian($text);

        $remove = '\x{0640}'.($removeDiacritics ? '\x{064B}-\x{065F}\x{0670}' : '');
        $text = preg_replace('/['.$remove.']/u', '', $text) ?? $text;

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Clean ZWNJ (half-space) usage: collapse repeats, drop soft hyphens,
     * and remove ZWNJ next to whitespace or at the ends of the text.
     * Invalid UTF-8 bytes become U+FFFD first (see {@see normalize()}).
     */
    public static function fixHalfSpace(string $text): string
    {
        $text = Utf8::scrub($text);
        $text = str_replace("\u{00AD}", '', $text);
        $text = preg_replace('/\x{200C}+/u', self::ZWNJ, $text) ?? $text;
        $text = preg_replace('/\x{200C}(?=\s)|(?<=\s)\x{200C}/u', '', $text) ?? $text;

        // trim() works on bytes and would cut the last byte of a letter such as ی (UTF-8 DB 8C).
        return preg_replace('/^\x{200C}+|\x{200C}+$/Du', '', $text) ?? $text;
    }

    /**
     * Full clean for storage / search. Idempotent: clean(clean($x)) === clean($x).
     *
     * Order matters: zero-width characters except ZWNJ (ZWSP, ZWJ, BOM) go first, so the
     * whitespace and ZWNJ rules below see the text as it will finally read; whitespace is
     * collapsed once more after the half-space fix, which can leave a double space behind.
     */
    public static function clean(string $text): string
    {
        $text = Utf8::scrub($text);
        $text = preg_replace('/[\x{200B}\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        $text = self::normalize($text);
        $text = self::fixHalfSpace($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
