<?php

declare(strict_types=1);

namespace RtlyKit\Number;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;

/**
 * Number formatting utilities for Persian locale.
 */
final class Format
{
    private const ZWNJ = "\u{200C}";

    /** Maximum characters of a normalised number accepted by withSeparator(). */
    private const MAX_NUMBER_CHARS = 1000;

    /** Maximum bytes of a raw string input (Persian/Arabic digits are 2 bytes each). */
    private const MAX_INPUT_BYTES = 4096;

    /**
     * Format a number with Persian digits and thousand separators, preserving decimals.
     *
     * Accepts English/Persian/Arabic digits and common separators in strings
     * ("1,234.5", "۱٬۲۳۴٫۵"). Never rounds.
     *
     * Size caps: a string input longer than 4096 bytes, or a normalised number
     * with more than 1000 characters (integer and fraction digits together), is
     * rejected.
     *
     * @throws InvalidNumberException when the value is not numeric or exceeds the size caps
     */
    public static function withSeparator(
        int|float|string $number,
        string $separator = "\u{066C}",
        string $decimalSeparator = "\u{066B}",
    ): string {
        $plain = self::toPlainDecimal($number);

        if (strlen($plain) > self::MAX_NUMBER_CHARS) {
            throw new InvalidNumberException(sprintf('Number exceeds the %d character limit.', self::MAX_NUMBER_CHARS), errorCode: ErrorCode::InputTooLong, context: ['limit' => self::MAX_NUMBER_CHARS]);
        }

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $plain, $m) !== 1) {
            throw new InvalidNumberException(sprintf("'%s' is not a valid number.", mb_substr((string) (is_string($number) ? $number : $plain), 0, 40)));
        }

        $int = ltrim($m[2], '0');
        $int = $int === '' ? '0' : $int;
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', "\x01", $int) ?? $int;
        $int = str_replace("\x01", $separator, $int);

        $sign = $m[1] === '-' ? '-' : '';
        $out = $sign.$int.(isset($m[3]) ? $decimalSeparator.$m[3] : '');

        return Digits::toPersian($out);
    }

    /**
     * Persian ordinal (اول، دوم، سوم، بیست و سوم، سی‌ام، ...).
     *
     * Integral floats (3.0) are accepted; fractional ones are rejected.
     *
     * @throws InvalidNumberException for negative or non-integral numbers
     */
    public static function ordinal(int|float $number): string
    {
        if (is_float($number) && ! is_finite($number)) {
            throw new InvalidNumberException('Ordinals are defined for non-negative integers only.', errorCode: ErrorCode::NonFiniteNumber);
        }

        if ($number < 0 || (is_float($number) && floor($number) !== $number)) {
            throw new InvalidNumberException('Ordinals are defined for non-negative integers only.');
        }

        if ($number == 1) {
            return 'اول';
        }

        $words = NumberToWords::convert($number);

        if (mb_substr($words, -2) === 'سه') {
            // سه → سوم (also 23 → بیست و سوم)
            return mb_substr($words, 0, mb_strlen($words) - 2).'سوم';
        }

        if (mb_substr($words, -1) === 'ی') {
            // سی → سی‌ام (ends in a non-joining vowel letter)
            return $words.self::ZWNJ.'ام';
        }

        return $words.'م';
    }

    private static function toPlainDecimal(int|float|string $number): string
    {
        if (is_int($number)) {
            return (string) $number;
        }

        if (is_float($number)) {
            if (! is_finite($number)) {
                throw new InvalidNumberException('Non-finite numbers cannot be formatted.', errorCode: ErrorCode::NonFiniteNumber);
            }
            $s = rtrim(sprintf('%.15F', $number), '0');

            return rtrim($s, '.');
        }

        if (strlen($number) > self::MAX_INPUT_BYTES) {
            throw new InvalidNumberException(sprintf('Input exceeds the %d byte limit.', self::MAX_INPUT_BYTES), errorCode: ErrorCode::InputTooLong, context: ['limit' => self::MAX_INPUT_BYTES]);
        }

        $s = Digits::toEnglish(trim($number));

        return str_replace([',', "\u{066C}", ' ', "\u{00A0}", "\u{066B}"], ['', '', '', '', '.'], $s);
    }
}
