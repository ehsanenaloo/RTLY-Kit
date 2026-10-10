<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;

/**
 * Iranian Postal Code validator.
 *
 * Checks the shape (10 digits) and one structural rule: the digits 0 and 2 do not occur in the first
 * five digits (the routing part), so a code cannot start with 0. Three public sources state this rule
 * (see resources/data/SOURCES.md). It does not check that the code is assigned to an address, and it
 * applies no other rule: the fifth digit, the digit 2 in the last five digits and codes made of one
 * repeated digit (`1111111111` passes) are not checked, because only one reliable source states them.
 */
final class PostalCode implements Validator
{
    public static function isValid(mixed $value): bool
    {
        return self::validate($value)->isValid();
    }

    /**
     * Validate with structured errors. Error codes: invalid_length, invalid_format,
     * invalid_type, input_too_long. Details: normalized (digits only).
     * Accepts strings, ints and integral floats; any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     *
     *
     * Iranian postal codes are 10 digits and the first five digits contain neither 0 nor 2 (a violation
     * is `invalid_format`). Nothing else is checked: there is no region-range check and the code does
     * not have to be assigned. Between the digits only spaces, NBSP, ZWNJ, LRM/RLM,
     * hyphens, parentheses and dots are accepted; any other character is `invalid_format`.
     */
    public static function validate(mixed $value): Result
    {
        $input = Input::coerce($value, ['normalized' => '']);

        if ($input instanceof Result) {
            return $input;
        }

        $normalized = self::normalize($input);
        $details = ['normalized' => $normalized];

        if (Input::strictDigits($input) === null) {
            return Result::invalid('invalid_format', $details);
        }

        if (strlen($normalized) !== 10) {
            return Result::invalid('invalid_length', $details);
        }

        if (preg_match('/[02]/', substr($normalized, 0, 5)) === 1) {
            return Result::invalid('invalid_format', $details);
        }

        return Result::valid($details);
    }

    /**
     * Digits only. Persian/Arabic digits are converted; the input may hold only spaces, NBSP, ZWNJ,
     * LRM/RLM, hyphens, parentheses and dots between the digits. Anything else (letters, other
     * punctuation, control characters, a leading hyphen) gives an empty string.
     */
    public static function normalize(string $code): string
    {
        return Input::strictDigits($code) ?? '';
    }
}
