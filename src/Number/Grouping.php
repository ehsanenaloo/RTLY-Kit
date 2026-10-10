<?php

declare(strict_types=1);

namespace RtlyKit\Number;

/**
 * Reads a number typed by a user, where thousands separators are allowed only in proper grouping.
 *
 * Linear in the length of the text (no nested quantifiers), so a 4096-byte input cannot hit the PCRE backtrack limit.
 *
 * @internal Not part of the public API.
 */
final class Grouping
{
    private const NBSP = "\u{00A0}";

    /**
     * The plain decimal text of a number whose digits were already converted to English.
     *
     * Spaces and no-break spaces around the number (and after the sign) are ignored. In the integer part
     * separators are accepted only in proper grouping: a first group of 1 to 3 digits, then groups of
     * exactly 3 digits (`1 234 567`, `1,234,567`, `1٬234٬567`). `1 2` or `12 34` give null. The Arabic
     * decimal separator (U+066B) is read as a dot. The fraction (only when $fraction is true) holds
     * digits only.
     *
     * @return string|null  `[sign]digits[.digits]` or null when the text is not such a number
     */
    public static function plain(string $english, bool $fraction): ?string
    {
        $text = self::trimSpaces(str_replace("\u{066B}", '.', $english));

        $sign = '';
        if ($text !== '' && ($text[0] === '+' || $text[0] === '-')) {
            $sign = $text[0];
            $text = self::trimSpaces(substr($text, 1));
        }

        $dot = strpos($text, '.');
        $int = $dot === false ? $text : substr($text, 0, $dot);
        $frac = $dot === false ? null : substr($text, $dot + 1);

        if ($frac !== null && (! $fraction || preg_match('/^\d+$/D', $frac) !== 1)) {
            return null;
        }

        if (preg_match('/^\d+$/D', $int) !== 1) {
            if (preg_match('/^\d{1,3}(?:(?:,|\xD9\xAC| |\xC2\xA0)\d{3})+$/D', $int) !== 1) {
                return null;
            }

            $int = str_replace([',', "\u{066C}", ' ', self::NBSP], '', $int);
        }

        return $sign.$int.($frac !== null ? '.'.$frac : '');
    }

    /** Remove spaces and no-break spaces from both ends (not tabs, newlines or NUL). */
    private static function trimSpaces(string $text): string
    {
        do {
            $length = strlen($text);
            $text = ltrim($text, ' ');
            $text = rtrim($text, ' ');

            if (str_starts_with($text, self::NBSP)) {
                $text = substr($text, 2);
            }

            if (str_ends_with($text, self::NBSP)) {
                $text = substr($text, 0, -2);
            }
        } while (strlen($text) !== $length);

        return $text;
    }
}
