<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;
use RtlyKit\Number\Digits;

/**
 * Iranian mobile number validator (national form 09xxxxxxxxx).
 *
 * Accepted input forms: 09121234567, 9121234567, 989121234567, +989121234567,
 * 00989121234567, with Persian/Arabic digits, spaces, dashes or parentheses.
 *
 * Validity only checks the shape: 09 + 9 digits whose third digit is 0-4 or 9
 * (blocks 090x-094x and 099x are allocated to mobile networks). The operator
 * lookup is separate and conservative: it only knows the well-established
 * prefixes below and returns null for anything else — the number portability
 * scheme means a prefix never guarantees the current operator anyway.
 *
 * Uncertain / deliberately omitted: 0994x-0999x (other than 0998 and 09991),
 * 0940-0949 and 0931/0932/0934 (Irancell family allocations vary between
 * sources).
 */
final class Mobile implements Validator
{
    /**
     * Longest-prefix table (digits after the leading 0): [prefix => operator].
     * Keys are 3-digit blocks, plus the 5-digit Aptel block.
     *
     * @var array<int, string>
     */
    private static array $operators = [
        910 => 'همراه اول',
        911 => 'همراه اول',
        912 => 'همراه اول',
        913 => 'همراه اول',
        914 => 'همراه اول',
        915 => 'همراه اول',
        916 => 'همراه اول',
        917 => 'همراه اول',
        918 => 'همراه اول',
        919 => 'همراه اول',
        990 => 'همراه اول',
        991 => 'همراه اول',
        992 => 'همراه اول',
        993 => 'همراه اول',
        994 => 'همراه اول',
        900 => 'ایرانسل',
        901 => 'ایرانسل',
        902 => 'ایرانسل',
        903 => 'ایرانسل',
        904 => 'ایرانسل',
        905 => 'ایرانسل',
        930 => 'ایرانسل',
        933 => 'ایرانسل',
        935 => 'ایرانسل',
        936 => 'ایرانسل',
        937 => 'ایرانسل',
        938 => 'ایرانسل',
        939 => 'ایرانسل',
        920 => 'رایتل',
        921 => 'رایتل',
        922 => 'رایتل',
        998 => 'شاتل موبایل',
        99910 => 'آپتل',
    ];

    public static function isValid(mixed $value): bool
    {
        return self::validate($value)->isValid();
    }

    /**
     * Validate with structured errors. Error codes: invalid_format, invalid_type, input_too_long.
     * Details: normalized, operator (null when unknown).
     * Accepts strings, ints and integral floats; any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     */
    public static function validate(mixed $value): Result
    {
        $details = ['normalized' => '', 'operator' => null];
        $input = Input::coerce($value, $details);

        if ($input instanceof Result) {
            return $input;
        }

        $mobile = self::normalize($input);
        $details['normalized'] = $mobile;

        if (preg_match('/^09[0-49]\d{8}$/', $mobile) !== 1) {
            return Result::invalid('invalid_format', $details);
        }

        $details['operator'] = self::lookup($mobile);

        return Result::valid($details);
    }

    public static function getOperator(mixed $value): ?string
    {
        $operator = self::validate($value)->details()['operator'] ?? null;

        return is_string($operator) ? $operator : null;
    }

    /**
     * Normalize to the national form `09xxxxxxxxx` where possible.
     * Input that cannot be mapped is returned as plain digits.
     */
    public static function normalize(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', Digits::toEnglish($mobile)) ?? '';

        if (str_starts_with($digits, '0098') && strlen($digits) === 14) {
            return '0'.substr($digits, 4);
        }

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && $digits[0] === '9') {
            return '0'.$digits;
        }

        return $digits;
    }

    private static function lookup(string $normalized): ?string
    {
        return self::$operators[(int) substr($normalized, 1, 5)]
            ?? self::$operators[(int) substr($normalized, 1, 3)]
            ?? null;
    }
}
