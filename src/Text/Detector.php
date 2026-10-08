<?php

declare(strict_types=1);

namespace RtlyKit\Text;

/**
 * Detect language and text direction.
 */
final class Detector
{
    /**
     * Code-point ranges of right-to-left scripts (Hebrew, Arabic and its
     * supplements/extensions, Syriac, Thaana, N'Ko, Samaritan, Mandaic,
     * Adlam, Arabic presentation forms) plus the RLM mark.
     */
    private const RTL_CLASS = '\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}\x{10800}-\x{10FFF}\x{1E800}-\x{1EFFF}\x{200F}';

    /** Script-level RTL (letters only; used for first-strong detection). */
    private const RTL_LETTER = '[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}\p{Nko}\p{Samaritan}\p{Mandaic}\p{Adlam}]';

    /** Language subtags written right-to-left by default. */
    private const RTL_LANGUAGES = [
        'fa', 'prs', 'ar', 'he', 'iw', 'ur', 'yi', 'ps', 'sd', 'ug', 'dv', 'ckb', 'ks', 'azb',
        'syr', 'arc', 'nqo', 'pnb', 'lrc', 'mzn', 'glk', 'bal', 'rhg', 'ota',
    ];

    /** Script subtags that force RTL regardless of language. */
    private const RTL_SCRIPTS = ['arab', 'hebr', 'syrc', 'thaa', 'nkoo', 'adlm', 'samr', 'mand', 'rohg'];

    /**
     * Persian-specific letters: پ چ ژ گ ک (U+06A9) ی (U+06CC) and Persian digits ۰-۹.
     * ه (U+0647) is shared by Persian and Arabic and is deliberately ignored.
     */
    private const PERSIAN_ONLY = '/[\x{067E}\x{0686}\x{0698}\x{06AF}\x{06A9}\x{06CC}\x{06F0}-\x{06F9}]/u';

    /**
     * Arabic-only letters: ي ك ى ة and Arabic-Indic digits ٠-٩.
     * (أ إ ؤ ئ ء occur in Persian loanwords, so they are not counted.)
     */
    private const ARABIC_ONLY = '/[\x{064A}\x{0643}\x{0649}\x{0629}\x{0660}-\x{0669}]/u';

    private const ARABIC_SCRIPT = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    /**
     * Heuristic: Arabic-script text is Persian when Persian-specific letters
     * are at least as frequent as Arabic-only ones. Text with no distinguishing
     * letters (e.g. «سلام») is treated as Persian by default.
     */
    public static function isPersian(string $text): bool
    {
        if (preg_match(self::ARABIC_SCRIPT, $text) !== 1) {
            return false;
        }

        return self::count(self::PERSIAN_ONLY, $text) >= self::count(self::ARABIC_ONLY, $text);
    }

    /**
     * Heuristic: Arabic-script text with more Arabic-only letters than Persian-specific ones.
     */
    public static function isArabic(string $text): bool
    {
        if (preg_match(self::ARABIC_SCRIPT, $text) !== 1) {
            return false;
        }

        return self::count(self::ARABIC_ONLY, $text) > self::count(self::PERSIAN_ONLY, $text);
    }

    public static function isHebrew(string $text): bool
    {
        return preg_match('/[\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}]/u', $text) === 1;
    }

    public static function containsRtl(string $text): bool
    {
        return preg_match('/['.self::RTL_CLASS.']/u', $text) === 1;
    }

    /**
     * Base direction by the first strong character (Unicode UBA rule P2/P3):
     * the first letter decides; digits, punctuation and spaces are neutral.
     *
     * @return 'rtl'|'ltr'
     */
    public static function direction(string $text): string
    {
        if (preg_match('/\p{L}/u', $text, $m) !== 1) {
            return 'ltr';
        }

        return preg_match('/^'.self::RTL_LETTER.'$/u', $m[0]) === 1 ? 'rtl' : 'ltr';
    }

    /**
     * Whether a locale tag (fa, ar_SA, ckb-IQ, ku-Arab, ug, dv ...) is written right-to-left.
     */
    public static function isRtlLocale(string $locale): bool
    {
        $parts = preg_split('/[-_.@]/', strtolower(trim($locale)), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return false;
        }

        foreach (array_slice($parts, 1) as $subtag) {
            if (in_array($subtag, self::RTL_SCRIPTS, true)) {
                return true;
            }
        }

        return in_array($parts[0], self::RTL_LANGUAGES, true);
    }

    private static function count(string $pattern, string $text): int
    {
        $n = preg_match_all($pattern, $text);

        return $n === false ? 0 : $n;
    }
}
