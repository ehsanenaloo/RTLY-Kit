<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;
use RtlyKit\Number\Digits;

/**
 * Iranian Postal Code validator.
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
     * Iranian postal codes are 10 digits and do not start with 0
     * (no region-range check is performed).
     */
    public static function validate(mixed $value): Result
    {
        $input = Input::coerce($value, ['normalized' => '']);

        if ($input instanceof Result) {
            return $input;
        }

        $normalized = self::normalize($input);
        $details = ['normalized' => $normalized];

        if (strlen($normalized) !== 10) {
            return Result::invalid('invalid_length', $details);
        }

        if ($normalized[0] === '0') {
            return Result::invalid('invalid_format', $details);
        }

        return Result::valid($details);
    }

    public static function normalize(string $code): string
    {
        return preg_replace('/\D/', '', Digits::toEnglish($code)) ?? '';
    }
}
