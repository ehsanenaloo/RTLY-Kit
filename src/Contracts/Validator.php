<?php

declare(strict_types=1);

namespace RtlyKit\Contracts;

use RtlyKit\Validation\Result;

/**
 * Uniform contract of every RTLY-Kit validator (national code, Sheba, bank
 * card, mobile, postal code, vehicle plate).
 *
 * Validators are stateless, so the contract is static: call
 * `NationalCode::validate($value)` directly, or through a class-string
 * (`$class::validate($value)`) when the validator is chosen at runtime.
 *
 * Neither method ever throws for bad input: any value (null, arrays, objects,
 * oversized strings, invalid UTF-8) yields an invalid {@see Result} with a
 * stable error code such as `invalid_type`, `input_too_long` or `invalid_format`.
 */
interface Validator
{
    /**
     * Validate with structured, machine-readable errors.
     */
    public static function validate(mixed $value): Result;

    /**
     * Shortcut for `validate($value)->isValid()`.
     */
    public static function isValid(mixed $value): bool;
}
