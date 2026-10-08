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
     */
    public static function normalize(string $text, bool $removeDiacritics = true): string
    {
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
     */
    public static function fixHalfSpace(string $text): string
    {
        $text = str_replace("\u{00AD}", '', $text);
        $text = preg_replace('/\x{200C}+/u', self::ZWNJ, $text) ?? $text;
        $text = preg_replace('/\x{200C}(?=\s)|(?<=\s)\x{200C}/u', '', $text) ?? $text;

        return trim($text, self::ZWNJ);
    }

    /**
     * Full clean for storage / search.
     */
    public static function clean(string $text): string
    {
        $text = self::normalize($text);
        $text = self::fixHalfSpace($text);

        // Remove zero-width characters except ZWNJ (ZWSP, ZWJ, BOM)
        $text = preg_replace('/[\x{200B}\x{200D}\x{FEFF}]/u', '', $text) ?? $text;

        return trim($text);
    }
}
