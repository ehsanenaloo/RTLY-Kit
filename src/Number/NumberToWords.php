<?php

declare(strict_types=1);

namespace RtlyKit\Number;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Exceptions\UnsupportedLocaleException;
use RtlyKit\Text\Normalizer;

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
     * Standard Arabic; |n| below 10^9, tested for 0 to 999,999,999 and
     * negatives). Arabic limitations: output is masculine/abstract counting
     * form only (no gender agreement with the counted noun, no case endings or
     * tanwin); groups such as 102 in 102,000 use the plain compound form
     * without the special construct-state rules.
     *
     * @throws InvalidNumberException when the value is not an integer or is too large
     * Input caps: a string longer than 4096 bytes is rejected before any
     * processing; floats must be finite and integral (3.0 is accepted, 1.5 is
     * rejected instead of being silently truncated).
     *
     * @throws UnsupportedLocaleException when the locale is not supported
     */
    public static function convert(int|float|string $number, string $locale = 'fa'): string
    {
        $lang = strtolower(substr($locale, 0, 2));

        return match ($lang) {
            'fa' => self::convertFa($number),
            'ar' => self::convertAr($number),
            default => throw new UnsupportedLocaleException(sprintf("Unsupported locale '%s' (use 'fa' or 'ar').", $locale), context: ['locale' => mb_substr($locale, 0, 40)]),
        };
    }

    private static function convertAr(int|float|string $number): string
    {
        [$negative, $digits] = self::parse($number);

        if (strlen($digits) > 9) {
            throw new InvalidNumberException('Arabic conversion supports numbers below 10^9.', errorCode: ErrorCode::NumberTooLarge, context: ['limit' => '10^9']);
        }

        if ($digits === '0') {
            return 'صفر';
        }

        $n = (int) $digits;
        $parts = [];
        $scales = [
            2 => ['مليون', 'مليونان', 'ملايين'],
            1 => ['ألف', 'ألفان', 'آلاف'],
        ];

        foreach ($scales as $scale => [$singular, $dual, $plural]) {
            $group = intdiv($n, 1000 ** $scale) % 1000;
            if ($group === 0) {
                continue;
            }
            $parts[] = match (true) {
                $group === 1 => $singular,
                $group === 2 => $dual,
                $group === 200 => 'مئتا '.$singular,
                $group >= 3 && $group <= 10 => self::arBelowThousand($group).' '.$plural,
                default => self::arBelowThousand($group).' '.$singular,
            };
        }

        $rest = $n % 1000;
        if ($rest > 0) {
            $parts[] = self::arBelowThousand($rest);
        }

        $words = implode(' و', $parts);

        return $negative ? 'سالب '.$words : $words;
    }

    private static function arBelowThousand(int $number): string
    {
        $units = [1 => 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
        $teens = [10 => 'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $tens = [2 => 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $hundreds = [1 => 'مئة', 'مئتان', 'ثلاثمئة', 'أربعمئة', 'خمسمئة', 'ستمئة', 'سبعمئة', 'ثمانمئة', 'تسعمئة'];

        $parts = [];
        if ($number >= 100) {
            $parts[] = $hundreds[intdiv($number, 100)];
            $number %= 100;
        }

        if ($number >= 20) {
            if ($number % 10 > 0) {
                $parts[] = $units[$number % 10];
            }
            $parts[] = $tens[intdiv($number, 10)];
        } elseif ($number >= 10) {
            $parts[] = $teens[$number];
        } elseif ($number > 0) {
            $parts[] = $units[$number];
        }

        return implode(' و', $parts);
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

        $small = array_flip(self::UNITS) + array_flip(self::TENS) + array_flip(self::HUNDREDS);
        // array_flip(TENS) maps word => tens digit; scale to its value.
        foreach (self::TENS as $d => $w) {
            $small[$w] = $d * 10;
        }
        foreach (self::HUNDREDS as $d => $w) {
            $small[$w] = $d * 100;
        }
        $scales = array_flip(self::SCALES);

        $groups = [];
        $current = 0;
        $lastScale = PHP_INT_MAX;
        $seen = false;

        foreach ($tokens as $token) {
            if ($token === 'و') {
                continue;
            }
            if (isset($small[$token])) {
                $current += $small[$token];
                $seen = true;
            } elseif (isset($scales[$token])) {
                $scale = $scales[$token];
                if ($scale >= $lastScale) {
                    throw new InvalidNumberException("Scales out of order near '{$token}'.", errorCode: ErrorCode::InvalidNumberWords);
                }
                $groups[$scale] = $current === 0 ? 1 : $current;
                $current = 0;
                $lastScale = $scale;
                $seen = true;
            } else {
                throw new InvalidNumberException("Unknown number word '{$token}'.", errorCode: ErrorCode::InvalidNumberWords);
            }
            if ($current > 999) {
                throw new InvalidNumberException('Group value exceeds 999.', errorCode: ErrorCode::InvalidNumberWords);
            }
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
    private static function parse(int|float|string $number): array
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
            throw new InvalidNumberException(sprintf("'%s' is not a valid integer.", is_int($number) ? (string) $number : mb_substr($number, 0, 40)));
        }

        $digits = ltrim($m[2], '0');
        if ($digits === '') {
            return [false, '0'];
        }
        if (strlen($digits) > self::MAX_DIGITS) {
            throw new InvalidNumberException('Number is too large (limit is 10^21 - 1).', errorCode: ErrorCode::NumberTooLarge, context: ['limit' => '10^21 - 1']);
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
