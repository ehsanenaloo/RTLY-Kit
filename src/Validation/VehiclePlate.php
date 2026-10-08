<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;
use RtlyKit\Number\Digits;
use RtlyKit\Text\Normalizer;

/**
 * Iranian Vehicle Plate (پلاک خودرو) validator & parser.
 *
 * Supports the common format: 12ب345 ایران 67
 * (2 digits, one letter, 3 digits, 2-digit region code). The word «ایران»,
 * spaces, hyphens and underscores are ignored.
 */
final class VehiclePlate implements Validator
{
    /**
     * Letters that appear on Iranian plates (the long form «الف» counts as one letter).
     * D and S (Latin) mark diplomatic / special plates.
     */
    private const VALID_LETTERS = [
        'الف', 'ب', 'پ', 'ت', 'ث', 'ج', 'د', 'ز', 'س', 'ش', 'ص', 'ط',
        'ع', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی', 'ژ',
        'D', 'S',
    ];

    public static function isValid(mixed $value): bool
    {
        return self::validate($value)->isValid();
    }

    /**
     * Validate with structured errors. Error codes: invalid_format, invalid_region,
     * invalid_type, input_too_long.
     * Details: normalized, plus the parse() fields when the shape matched.
     * Accepts strings, ints and integral floats; any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     */
    public static function validate(mixed $value): Result
    {
        $input = Input::coerce($value, ['normalized' => '']);

        if ($input instanceof Result) {
            return $input;
        }

        $normalized = self::normalize($input);
        $parts = self::match($normalized);
        $details = ['normalized' => $normalized];

        if ($parts === null) {
            return Result::invalid('invalid_format', $details);
        }

        $details += $parts;

        if ($parts['region'] === '00' || $parts['two_digit'] === '00' || $parts['three_digit'] === '000') {
            return Result::invalid('invalid_region', $details);
        }

        return Result::valid($details);
    }

    /**
     * Parse plate into parts (null when the shape does not match or the numbers are zero).
     *
     * @return array{two_digit: string, letter: string, three_digit: string, region: string}|null
     */
    public static function parse(string $plate): ?array
    {
        $parts = self::match(self::normalize($plate));

        if ($parts === null || $parts['region'] === '00' || $parts['two_digit'] === '00' || $parts['three_digit'] === '000') {
            return null;
        }

        return $parts;
    }

    public static function normalize(string $plate): string
    {
        $plate = Normalizer::normalize(Digits::toEnglish($plate));
        $plate = str_replace('ایران', '', $plate);
        $plate = str_replace([' ', '-', '_', "\u{200C}"], '', $plate);

        return strtoupper(trim($plate));
    }

    /**
     * @return array{two_digit: string, letter: string, three_digit: string, region: string}|null
     */
    private static function match(string $normalized): ?array
    {
        $letters = implode('|', array_map(static fn (string $l): string => preg_quote($l, '/'), self::VALID_LETTERS));

        if (preg_match('/^(\d{2})('.$letters.')(\d{3})(\d{2})$/u', $normalized, $m) !== 1) {
            return null;
        }

        return [
            'two_digit' => $m[1],
            'letter' => $m[2],
            'three_digit' => $m[3],
            'region' => $m[4],
        ];
    }
}
