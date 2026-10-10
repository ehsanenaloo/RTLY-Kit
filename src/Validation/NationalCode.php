<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;
use RtlyKit\Number\Digits;

/**
 * Iranian National Code (کد ملی) validator & utilities.
 */
final class NationalCode implements Validator
{
    /**
     * Prefix to province/city, loaded lazily from resources/data/national-code-locations.php (data provenance is documented there).
     *
     * CAVEAT: the prefix identifies the place of ISSUANCE of the original
     * birth certificate/card, NOT necessarily the place of birth or residence.
     * Treat the result as a hint, never as proof of identity or origin.
     *
     * @return array<int, array{province: string, city: string}>
     */
    private static function locations(): array
    {
        /** @var array<int, array{province: string, city: string}> $table */
        $table = DataTables::load('national-code-locations');

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
     * Accepts strings, ints and integral floats; any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     */
    public static function validate(mixed $value): Result
    {
        $details = ['normalized' => '', 'location' => null];
        $input = Input::coerce($value, $details);

        if ($input instanceof Result) {
            return $input;
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

        $details['location'] = self::locations()[(int) substr($code, 0, 3)] ?? null;

        return Result::valid($details);
    }

    /**
     * Get province and city information from the National Code, if known.
     *
     * Returns null for prefixes without a cross-verified entry (see $locations).
     *
     * @return array{province: string, city: string}|null
     */
    public static function getLocation(mixed $value): ?array
    {
        $location = self::validate($value)->details()['location'] ?? null;

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
