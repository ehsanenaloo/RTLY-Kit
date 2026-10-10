<?php

declare(strict_types=1);

namespace RtlyKit\Number;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Text\Utf8;

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
     * ("1,234.5", "۱٬۲۳۴٫۵"). Separators (comma, ٬, space, no-break space) are accepted in the
     * integer part only in proper grouping: a first group of 1 to 3 digits, then groups of exactly 3
     * ("1 234 567"); "1 2" or "12 34" is rejected. The fraction holds digits only. Never rounds a string. A float is written with its
     * shortest round-trip decimal form (-1234567.891 gives "-۱٬۲۳۴٬۵۶۷٫۸۹۱", not
     * binary noise), limited to 15 significant digits so that 0.1 + 0.2 reads
     * "۰٫۳", and without an exponent; negative zero is "۰". Pass a string when
     * you need more digits than a float holds.
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

        // The D modifier: without it `$` also matches before a trailing newline.
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/D', $plain, $m) !== 1) {
            throw new InvalidNumberException(sprintf("'%s' is not a valid number.", Utf8::truncate(is_string($number) ? $number : $plain, 40)));
        }

        $int = ltrim($m[2], '0');
        $int = $int === '' ? '0' : $int;
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', "\x01", $int) ?? $int;
        $int = str_replace("\x01", $separator, $int);

        // Zero has no sign: "-0", "-0.0" and -0.0 all read "۰".
        $isZero = trim($m[2], '0') === '' && trim($m[3] ?? '', '0') === '';
        $sign = $m[1] === '-' && ! $isZero ? '-' : '';
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

        if (str_ends_with($words, 'سه')) {
            // سه → سوم (also 23 → بیست و سوم)
            return substr($words, 0, -strlen('سه')).'سوم';
        }

        if (str_ends_with($words, 'ی')) {
            // سی → سی‌ام (ends in a non-joining vowel letter)
            return $words.self::ZWNJ.'ام';
        }

        return $words.'م';
    }

    /**
     * Shortest decimal text that parses back to the same float (like
     * serialize_precision = -1) but never more than 15 significant digits (the
     * most a float holds reliably), written without an exponent.
     * Negative zero is "0".
     */
    private static function floatToPlain(float $number): string
    {
        if ($number == 0.0) {
            return '0';
        }

        // Fewest significant digits (at most 15) that parse back to the same float (independent of the serialize_precision ini).
        $repr = '';
        for ($p = 0; $p <= 14; $p++) {
            $repr = sprintf('%.'.$p.'e', $number); // e.g. -1.234567891e+6
            if ((float) $repr === $number) {
                break;
            }
        }
        $sign = '';
        if ($repr[0] === '-') {
            $sign = '-';
            $repr = substr($repr, 1);
        }

        $exponent = 0;
        if (preg_match('/^([0-9.]+)e([+-]\d+)$/', $repr, $m) === 1) {
            $repr = $m[1];
            $exponent = (int) $m[2];
        }

        [$int, $frac] = array_pad(explode('.', $repr, 2), 2, '');
        $digits = $int.$frac;
        $point = strlen($int) + $exponent; // position of the decimal point within $digits

        if ($point <= 0) {
            $digits = str_repeat('0', 1 - $point).$digits;
            $point = 1;
        } elseif ($point > strlen($digits)) {
            $digits = str_pad($digits, $point, '0');
        }

        $whole = substr($digits, 0, $point);
        $fraction = rtrim(substr($digits, $point), '0');

        return $sign.$whole.($fraction === '' ? '' : '.'.$fraction);
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

            return self::floatToPlain($number);
        }

        if (strlen($number) > self::MAX_INPUT_BYTES) {
            throw new InvalidNumberException(sprintf('Input exceeds the %d byte limit.', self::MAX_INPUT_BYTES), errorCode: ErrorCode::InputTooLong, context: ['limit' => self::MAX_INPUT_BYTES]);
        }

        // No trim(): it would also strip "\n", "\0" and tabs. Only spaces and NBSP around the number are ignored,
        // and separators inside it must be proper thousands grouping ("1 234", not "1 2").
        $plain = Grouping::plain(Digits::toEnglish($number), true);

        if ($plain === null) {
            throw new InvalidNumberException(sprintf("'%s' is not a valid number.", Utf8::truncate($number, 40)));
        }

        return $plain;
    }
}
