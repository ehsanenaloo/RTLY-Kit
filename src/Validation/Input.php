<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

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
}
