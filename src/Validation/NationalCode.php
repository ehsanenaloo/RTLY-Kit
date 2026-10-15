<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Number\Digits;

/**
 * Iranian National Code (کد ملی) validator & utilities.
 */
final class NationalCode implements Validator
{
    /**
     * CAVEAT: the prefix identifies the place of ISSUANCE of the original
     * birth certificate/card, NOT necessarily the place of birth or residence.
     * Treat the result as a hint, never as proof of identity or origin.
     */
    private static function table(?ReferenceTable $table): ReferenceTable
    {
        if ($table === null) {
            return ReferenceTable::nationalCodeLocations();
        }
        if ($table->kind !== TableKind::NationalCodePrefix) {
            throw RtlyKitException::because(ErrorCode::InvalidArgument, 'NationalCode needs a table of prefixes (ReferenceTable::nationalCodeLocations())', ['table' => $table->kind->value]);
        }

        return $table;
    }

    /**
     * Validate Iranian National Code (10 digits, mod-11 check digit).
     */
    public static function isValid(mixed $value): bool
    {
        return self::validate($value)->isValid();
    }

    /**
     * Validate with structured errors. Error codes: invalid_format,
     * repeated_digits, invalid_checksum, invalid_type, input_too_long.
     * Details: normalized, location.
     * Accepts strings, ints and integral floats (a number of fewer than 10 digits is padded with leading
     * zeros, because a code such as 0013542419 loses them as a number; a string is never padded); any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     *
     * $places replaces the bundled prefix table for the location only; validity never depends on it.
     *
     * @throws RtlyKitException when $places is not a table of national-code prefixes (a programming error, not bad input)
     */
    public static function validate(mixed $value, ?ReferenceTable $places = null): Result
    {
        $places = self::table($places);
        $details = ['normalized' => '', 'location' => null];
        $input = Input::coerce($value, $details);

        if ($input instanceof Result) {
            return $input;
        }

        // A number has lost the leading zeros of a code such as 0013542419: put them back. A string is never padded.
        if (! is_string($value) && preg_match('/^\d{1,9}$/D', $input) === 1) {
            $input = str_pad($input, 10, '0', STR_PAD_LEFT);
        }

        $code = self::normalize($input);
        $details['normalized'] = $code;

        if (preg_match('/^\d{10}$/D', $code) !== 1) {
            return Result::invalid('invalid_format', $details);
        }

        if (preg_match('/^(\d)\1{9}$/D', $code) === 1) {
            return Result::invalid('repeated_digits', $details);
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $checkDigit = (int) $code[9];
        $expected = $remainder < 2 ? $remainder : 11 - $remainder;

        if ($checkDigit !== $expected) {
            return Result::invalid('invalid_checksum', $details);
        }

        $place = $places->get((int) substr($code, 0, 3));
        $details['location'] = is_array($place) ? $place : null;

        return Result::valid($details);
    }

    /**
     * Get province and city information from the National Code, if known.
     *
     * Returns null for prefixes without a cross-verified entry (see $locations).
     *
     * @return array{province: string, city: string}|null
     */
    public static function getLocation(mixed $value, ?ReferenceTable $places = null): ?array
    {
        $location = self::validate($value, $places)->details()['location'] ?? null;

        /** @var array{province: string, city: string}|null $location */
        return is_array($location) ? $location : null;
    }

    /**
     * Normalize the code (convert Persian/Arabic digits, drop spaces, NBSP, ZWNJ, LRM/RLM and hyphens).
     * Nothing else is removed: a tab, newline, NUL or any other character stays in the result,
     * so {@see self::validate()} rejects it as `invalid_format`.
     */
    public static function normalize(string $code): string
    {
        return str_replace('-', '', Input::stripSpaces(Digits::toEnglish($code)));
    }
}
