<?php

declare(strict_types=1);

namespace RtlyKit\Text;

/**
 * Detect language and text direction.
 *
 * Invalid UTF-8: every method first replaces each byte that is not part of a well-formed sequence by
 * U+FFFD (a neutral character, neither a letter nor RTL), so the answer depends only on the valid
 * parts of the text. No method throws and mbstring is not required.
 */
final class Detector
{
    /**
     * Code-point ranges of right-to-left scripts (Hebrew, Arabic and its
     * supplements/extensions, Syriac, Thaana, N'Ko, Samaritan, Mandaic,
     * Adlam, Arabic presentation forms) plus the explicit right-to-left marks and embeddings: RLM (U+200F),
     * RLE (U+202B), RLO (U+202E) and RLI (U+2067). The Arabic letter mark ALM (U+061C) lies in the Arabic block.
     */
    private const RTL_CLASS = '\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFC}\x{10800}-\x{10FFF}\x{1E800}-\x{1EFFF}\x{200F}\x{202B}\x{202E}\x{2067}';

    /**
     * The same RTL blocks as {@see self::RTL_CLASS} without the RLM mark. direction() tests its first
     * letter against these ranges, so it agrees with containsRtl() for every RTL script (also the
     * historic ones: Phoenician, Lydian, Imperial Aramaic, Avestan, Old Turkic, Nabataean, Yezidi ...).
     */
    private const RTL_LETTER_RANGES = '\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFC}\x{10800}-\x{10FFF}\x{1E800}-\x{1EFFF}';

    /** Language subtags written right-to-left by default. */
    private const RTL_LANGUAGES = [
        'fa', 'prs', 'ar', 'he', 'iw', 'ur', 'yi', 'ps', 'sd', 'ug', 'dv', 'ckb', 'ks', 'azb',
        'syr', 'arc', 'nqo', 'pnb', 'lrc', 'mzn', 'glk', 'bal', 'rhg', 'ota',
    ];

    /** Script subtags that force RTL regardless of language. */
    private const RTL_SCRIPTS = [
        'arab', 'hebr', 'syrc', 'thaa', 'nkoo', 'adlm', 'samr', 'mand', 'rohg',
        'phnx', 'lydi', 'armi', 'avst', 'sarb', 'narb', 'nbat', 'palm', 'hatr', 'phlp', 'phli',
        'prti', 'orkh', 'hung', 'mani', 'yezi', 'chrs', 'elym', 'mend', 'cprt', 'khar', 'mero',
        'merc', 'sogd', 'sogo',
    ];

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

    private const ARABIC_SCRIPT = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFC}]/u';

    /**
     * Heuristic: Arabic-script text is Persian when Persian-specific letters
     * are at least as frequent as Arabic-only ones. Text with no distinguishing
     * letters (e.g. «سلام») is treated as Persian by default.
     */
    public static function isPersian(string $text): bool
    {
        $text = Utf8::scrub($text);

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
        $text = Utf8::scrub($text);

        if (preg_match(self::ARABIC_SCRIPT, $text) !== 1) {
            return false;
        }

        return self::count(self::ARABIC_ONLY, $text) > self::count(self::PERSIAN_ONLY, $text);
    }

    public static function isHebrew(string $text): bool
    {
        $text = Utf8::scrub($text);

        return preg_match('/[\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}]/u', $text) === 1;
    }

    public static function containsRtl(string $text): bool
    {
        $text = Utf8::scrub($text);

        return preg_match('/['.self::RTL_CLASS.']/u', $text) === 1;
    }

    /**
     * Base direction by the first strong character (Unicode UBA rule P2/P3):
     * the first letter decides; digits, punctuation and spaces are neutral.
     * Explicit controls (RLE, RLO, RLI, RLM) are not letters, so they do not decide the
     * direction here, although {@see containsRtl()} counts them.
     *
     * @return 'rtl'|'ltr'
     */
    public static function direction(string $text): string
    {
        $text = Utf8::scrub($text);

        if (preg_match('/\p{L}/u', $text, $m) !== 1) {
            return 'ltr';
        }

        return preg_match('/^['.self::RTL_LETTER_RANGES.']$/Du', $m[0]) === 1 ? 'rtl' : 'ltr';
    }

    /**
     * Whether a locale tag (fa, ar_SA, ckb-IQ, ku-Arab, ug, dv ...) is written right-to-left.
     *
     * An explicit four-letter script subtag decides: `ku-Arab` is RTL, but `fa-Latn`, `ar-Latn`
     * and `sd-Deva` are not. Without a script subtag the language decides.
     */
    public static function isRtlLocale(string $locale): bool
    {
        // POSIX suffixes (".UTF-8", "@euro") are not part of the language tag.
        $tag = strtolower(trim($locale));
        $parts = preg_split('/[-_]/', substr($tag, 0, strcspn($tag, '.@')), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return false;
        }

        foreach (array_slice($parts, 1) as $subtag) {
            if (strlen($subtag) === 1) {
                break; // an extension or private-use singleton (-u-, -x-) ends the language/script/region part
            }

            if (preg_match('/^[a-z]{4}$/D', $subtag) === 1) {
                return in_array($subtag, self::RTL_SCRIPTS, true);
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
