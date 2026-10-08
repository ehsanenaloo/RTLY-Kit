<?php

declare(strict_types=1);

namespace RtlyKit\Number;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Exceptions\UnsupportedLocaleException;
use RtlyKit\Number\Arabic\ArabicCardinal;
use RtlyKit\Number\Arabic\ArabicOrdinal;
use RtlyKit\Text\Normalizer;
use RtlyKit\Text\Utf8;

/**
 * Convert integers to Persian words and back.
 *
 * Supports every integer below 10^21 (up to «کوینتیلیون»), including the full
 * PHP int range, and arbitrarily long digit strings within that bound.
 */
final class NumberToWords
{
    private const UNITS = [
        0 => 'صفر',
        1 => 'یک',
        2 => 'دو',
        3 => 'سه',
        4 => 'چهار',
        5 => 'پنج',
        6 => 'شش',
        7 => 'هفت',
        8 => 'هشت',
        9 => 'نه',
        10 => 'ده',
        11 => 'یازده',
        12 => 'دوازده',
        13 => 'سیزده',
        14 => 'چهارده',
        15 => 'پانزده',
        16 => 'شانزده',
        17 => 'هفده',
        18 => 'هجده',
        19 => 'نوزده',
    ];

    private const TENS = [
        2 => 'بیست',
        3 => 'سی',
        4 => 'چهل',
        5 => 'پنجاه',
        6 => 'شصت',
        7 => 'هفتاد',
        8 => 'هشتاد',
        9 => 'نود',
    ];

    private const HUNDREDS = [
        1 => 'صد',
        2 => 'دویست',
        3 => 'سیصد',
        4 => 'چهارصد',
        5 => 'پانصد',
        6 => 'ششصد',
        7 => 'هفتصد',
        8 => 'هشتصد',
        9 => 'نهصد',
    ];

    /**
     * Scale names indexed by group position (group 1 = thousands).
     */
    private const SCALES = [
        1 => 'هزار',
        2 => 'میلیون',
        3 => 'میلیارد',
        4 => 'تریلیون',
        5 => 'کوادریلیون',
        6 => 'کوینتیلیون',
    ];

    private const MAX_DIGITS = 21;

    /** Upper bound (bytes) for any string input, checked before parsing. */
    private const MAX_INPUT_BYTES = 4096;

    /**
     * Convert an integer to cardinal words.
     *
     * Locales: `fa` (default; every integer below 10^21) and `ar` (Modern
     * Standard Arabic; every integer below 10^27, see {@see ArabicOptions}).
     * Without options the Arabic output is the plain "bare counting" form
     * (masculine, nominative, no vowel marks); options select the gender of a
     * counted noun, the case, vowel marks, hundreds spelling and more. Passing
     * options together with a non-Arabic locale is an error.
     *
     * Input caps: a string longer than 4096 bytes is rejected before any
     * processing; floats must be finite and integral (3.0 is accepted, 1.5 is
     * rejected instead of being silently truncated).
     *
     * @param ArabicOptions|array<array-key, mixed>|null $options Arabic only
     *
     * @throws InvalidNumberException when the value is not an integer, is too large or an option is invalid
     * @throws UnsupportedLocaleException when the locale is not supported
     */
    public static function convert(int|float|string $number, string $locale = 'fa', ArabicOptions|array|null $options = null): string
    {
        $lang = self::language($locale);

        if ($lang === 'ar') {
            $resolved = ArabicOptions::resolve($options);
            [$negative, $digits] = self::parse($number, ArabicCardinal::MAX_DIGITS);

            return ArabicCardinal::words($digits, $negative, $resolved);
        }

        self::rejectOptions($options, $locale);

        return self::convertFa($number);
    }

    /**
     * Convert a positive integer from 1 to 99 to an Arabic ordinal word
     * (الأول، الثانية عشرة، الحادي والعشرون).
     *
     * Only the `gender`, `case` and `definite` options apply. Ordinals above
     * 99 are not supported. The default locale is `ar`; Persian ordinals are
     * produced by {@see Format::ordinal()}.
     *
     * @param ArabicOptions|array<array-key, mixed>|null $options
     *
     * @throws InvalidNumberException when the value is not an integer from 1 to 99 or an option is invalid
     * @throws UnsupportedLocaleException when the locale is not `ar`
     */
    public static function ordinal(int|string $number, string $locale = 'ar', ArabicOptions|array|null $options = null): string
    {
        $lang = self::language($locale);
        if ($lang !== 'ar') {
            throw new UnsupportedLocaleException(sprintf("Ordinal words support only the Arabic locale 'ar' (got '%s'); use Format::ordinal() for Persian.", Utf8::truncate($locale, 40)), context: ['locale' => Utf8::truncate($locale, 40)]);
        }

        $resolved = ArabicOptions::resolve($options);
        if ($resolved->diacritics !== ArabicOptions::DIACRITICS_NONE) {
            throw new InvalidNumberException('Vowel marks are not supported for ordinals.', errorCode: ErrorCode::InvalidArgument, context: ['option' => 'diacritics']);
        }

        [$negative, $digits] = self::parse($number, ArabicCardinal::MAX_DIGITS);

        if ($negative || $digits === '0') {
            throw new InvalidNumberException('Ordinals are defined for positive integers only.', errorCode: ErrorCode::InvalidNumber);
        }

        if (strlen($digits) > 2) {
            throw new InvalidNumberException('Arabic ordinals are supported from 1 to 99.', errorCode: ErrorCode::NumberTooLarge, context: ['limit' => ArabicOrdinal::MAX]);
        }

        return ArabicOrdinal::words((int) $digits, $resolved);
    }

    private static function language(string $locale): string
    {
        $lang = strtolower(substr($locale, 0, 2));

        if ($lang !== 'fa' && $lang !== 'ar') {
            throw new UnsupportedLocaleException(sprintf("Unsupported locale '%s' (use 'fa' or 'ar').", Utf8::truncate($locale, 40)), context: ['locale' => Utf8::truncate($locale, 40)]);
        }

        return $lang;
    }

    /**
     * @param ArabicOptions|array<array-key, mixed>|null $options
     */
    private static function rejectOptions(ArabicOptions|array|null $options, string $locale): void
    {
        if ($options !== null) {
            throw new InvalidNumberException(sprintf("Options are only supported for the Arabic locale 'ar' (got '%s').", Utf8::truncate($locale, 40)), errorCode: ErrorCode::InvalidArgument, context: ['locale' => Utf8::truncate($locale, 40)]);
        }
    }

    private static function convertFa(int|float|string $number): string
    {
        [$negative, $digits] = self::parse($number);

        if ($digits === '0') {
            return self::UNITS[0];
        }

        $groups = str_split(str_pad($digits, (int) (ceil(strlen($digits) / 3) * 3), '0', STR_PAD_LEFT), 3);
        $count = count($groups);
        $parts = [];

        foreach ($groups as $i => $group) {
            $value = (int) $group;
            if ($value === 0) {
                continue;
            }
            $scale = $count - 1 - $i;
            $parts[] = $scale === 0
                ? self::convertBelowThousand($value)
                : self::convertBelowThousand($value).' '.(self::SCALES[$scale] ?? '');
        }

        $words = implode(' و ', $parts);

        return $negative ? 'منفی '.$words : $words;
    }

    /**
     * Inverse of {@see convert()} for the forms it produces (also accepts
     * Arabic letter variants and ZWNJ-joined words).
     *
     * @return int|string int when it fits in a PHP int, otherwise a digit string
     *
     * @throws InvalidNumberException on unknown words or an ill-formed sequence
     */
    public static function fromWords(string $words): int|string
    {
        if (strlen($words) > self::MAX_INPUT_BYTES) {
            throw new InvalidNumberException('Input is too long.', errorCode: ErrorCode::InputTooLong, context: ['limit' => self::MAX_INPUT_BYTES]);
        }

        $text = Normalizer::normalize(str_replace("\u{200C}", ' ', $words));
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false || $tokens === []) {
            throw new InvalidNumberException('Empty number words.', errorCode: ErrorCode::InvalidNumberWords);
        }

        $negative = false;
        if ($tokens[0] === 'منفی') {
            $negative = true;
            array_shift($tokens);
        }

        // word => [value, class]; class 1 hundreds, 2 tens, 3 units and teens
        $small = [];
        foreach (self::UNITS as $v => $w) {
            $small[$w] = [$v, 3];
        }
        foreach (self::TENS as $d => $w) {
            $small[$w] = [$d * 10, 2];
        }
        foreach (self::HUNDREDS as $d => $w) {
            $small[$w] = [$d * 100, 1];
        }
        $scales = array_flip(self::SCALES);

        $groups = [];
        $current = 0;
        $stage = 0;          // highest word class used in the current group (0 = none)
        $lastScale = PHP_INT_MAX;
        $previous = 'start'; // start | small | scale | and
        $seen = false;

        foreach ($tokens as $token) {
            if ($token === 'و') {
                if ($previous !== 'small' && $previous !== 'scale') {
                    throw new InvalidNumberException("Misplaced 'و' in number words.", errorCode: ErrorCode::InvalidNumberWords);
                }
                $previous = 'and';

                continue;
            }

            if (isset($small[$token])) {
                [$value, $class] = $small[$token];

                if ($previous === 'small' || $previous === 'scale') {
                    throw new InvalidNumberException(sprintf("Missing 'و' before '%s'.", Utf8::truncate($token, 40)), errorCode: ErrorCode::InvalidNumberWords);
                }
                // Canonical order inside a group: hundreds, then tens, then units (or a 10-19 word alone).
                $teen = $class === 3 && $value >= 10;
                if ($value === 0 && ($seen || count($tokens) > 1)) {
                    throw new InvalidNumberException("'صفر' cannot be combined with other number words.", errorCode: ErrorCode::InvalidNumberWords);
                }
                if ($class <= $stage || ($teen && $stage > 1)) {
                    throw new InvalidNumberException(sprintf("Number words out of order near '%s'.", Utf8::truncate($token, 40)), errorCode: ErrorCode::InvalidNumberWords);
                }
                $current += $value;
                $stage = $teen ? 3 : $class;
                $previous = 'small';
                $seen = true;
            } elseif (isset($scales[$token])) {
                $scale = $scales[$token];
                if ($previous === 'and' || ($previous === 'scale')) {
                    throw new InvalidNumberException(sprintf("Misplaced scale word '%s'.", Utf8::truncate($token, 40)), errorCode: ErrorCode::InvalidNumberWords);
                }
                if ($scale >= $lastScale) {
                    throw new InvalidNumberException("Scales out of order near '{$token}'.", errorCode: ErrorCode::InvalidNumberWords);
                }
                $groups[$scale] = $current === 0 ? 1 : $current;
                $current = 0;
                $stage = 0;
                $lastScale = $scale;
                $previous = 'scale';
                $seen = true;
            } else {
                throw new InvalidNumberException(sprintf("Unknown number word '%s'.", Utf8::truncate($token, 40)), errorCode: ErrorCode::InvalidNumberWords);
            }
        }

        if ($previous === 'and') {
            throw new InvalidNumberException("Number words cannot end with 'و'.", errorCode: ErrorCode::InvalidNumberWords);
        }

        if (! $seen) {
            throw new InvalidNumberException('No number words found.', errorCode: ErrorCode::InvalidNumberWords);
        }

        $groups[0] = $current;
        $digits = '';
        for ($g = max(array_keys($groups)); $g >= 0; $g--) {
            $digits .= str_pad((string) ($groups[$g] ?? 0), 3, '0', STR_PAD_LEFT);
        }
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }
        if ($negative && $digits !== '0') {
            $digits = '-'.$digits;
        }

        $int = (int) $digits;

        return (string) $int === $digits ? $int : $digits;
    }

    /**
     * @return array{0: bool, 1: string} [negative, unsigned digits without leading zeros]
     */
    private static function parse(int|float|string $number, int $maxDigits = self::MAX_DIGITS): array
    {
        if (is_float($number)) {
            if (! is_finite($number)) {
                throw new InvalidNumberException('Only integral numbers can be converted to words.', errorCode: ErrorCode::NonFiniteNumber);
            }

            if (floor($number) !== $number) {
                throw new InvalidNumberException('Only integral numbers can be converted to words.');
            }
            // Exact decimal expansion (also for integral floats beyond PHP_INT_MAX).
            $number = sprintf('%.0F', $number);
        }

        if (is_string($number) && strlen($number) > self::MAX_INPUT_BYTES) {
            throw new InvalidNumberException('Input is too long.', errorCode: ErrorCode::InputTooLong, context: ['limit' => self::MAX_INPUT_BYTES]);
        }

        $raw = is_int($number)
            ? (string) $number
            : str_replace([',', '٬', ' ', "\u{00A0}"], '', Digits::toEnglish(trim($number)));

        if (preg_match('/^([+-]?)(\d+)$/', $raw, $m) !== 1) {
            throw new InvalidNumberException(sprintf("'%s' is not a valid integer.", is_int($number) ? (string) $number : Utf8::truncate($number, 40)));
        }

        $digits = ltrim($m[2], '0');
        if ($digits === '') {
            return [false, '0'];
        }
        if (strlen($digits) > $maxDigits) {
            $limit = '10^'.$maxDigits.' - 1';

            throw new InvalidNumberException(sprintf('Number is too large (limit is %s).', $limit), errorCode: ErrorCode::NumberTooLarge, context: ['limit' => $limit]);
        }

        return [$m[1] === '-', $digits];
    }

    private static function convertBelowThousand(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        $parts = [];

        if ($number >= 100) {
            $parts[] = self::HUNDREDS[intdiv($number, 100)];
            $number %= 100;
        }

        if ($number >= 20) {
            $parts[] = self::TENS[intdiv($number, 10)];
            $number %= 10;
        }

        if ($number > 0) {
            $parts[] = self::UNITS[$number];
        }

        return implode(' و ', $parts);
    }
}
