<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Number\Digits;

/**
 * Turns an arbitrary value into the string a validator works on.
 *
 * @internal
 */
final class Input
{
    /** Maximum bytes of a string handed to a validator. */
    public const MAX_BYTES = 4096;

    /**
     * Strings pass through; ints and integral finite floats are stringified;
     * everything else (null, bool, arrays, objects, NaN, INF, fractions) is
     * rejected as `invalid_type`, and strings above the cap as `input_too_long`,
     * so such values never reach a string function.
     *
     * @param  array<string, mixed>  $details  the details of the invalid Result (the validator's usual keys, empty)
     * @return string|Result  the usable string, or the invalid Result to return as is
     */
    public static function coerce(mixed $value, array $details = []): string|Result
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value) || floor($value) !== $value || abs($value) >= 1.0E+15) {
                return Result::invalid('invalid_type', $details);
            }

            return sprintf('%.0F', $value);
        }

        if (! is_string($value)) {
            return Result::invalid('invalid_type', $details);
        }

        if (strlen($value) > self::MAX_BYTES) {
            return Result::invalid('input_too_long', $details);
        }

        return $value;
    }

    /**
     * The characters a user may type between the digits of a number: space, NBSP, ZWNJ, LRM and RLM.
     * Tabs, newlines, NUL and other control characters are NOT separators.
     */
    private const SPACES = [' ', "\u{00A0}", "\u{200C}", "\u{200E}", "\u{200F}"];

    /**
     * Remove only the allowed spacing characters (see {@see self::SPACES}). Anything else stays, so a
     * strict anchored pattern (with the D modifier) rejects it.
     */
    public static function stripSpaces(string $value): string
    {
        return str_replace(self::SPACES, '', $value);
    }

    /**
     * Strict digits-only reading of a number typed by a user.
     *
     * After Persian/Arabic digits become English, the text may hold only digits plus spaces, NBSP,
     * ZWNJ, LRM/RLM, hyphens, parentheses and dots, and (when $allowPlus is true) one leading `+`.
     * A hyphen cannot come first (so a negative number is rejected). Letters, other punctuation and
     * control characters (including a trailing newline or NUL) make the whole input invalid.
     *
     * @return string|null  the digits, or null when the text holds anything else
     */
    public static function strictDigits(string $value, bool $allowPlus = false): ?string
    {
        $text = self::stripSpaces(Digits::toEnglish($value));

        if ($allowPlus && str_starts_with($text, '+')) {
            $text = substr($text, 1);
        }

        if (preg_match('/^[0-9()\-.]*$/D', $text) !== 1 || str_starts_with($text, '-')) {
            return null;
        }

        return preg_replace('/\D/', '', $text) ?? '';
    }
}
