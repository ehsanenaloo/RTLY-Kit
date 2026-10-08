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
 * 0990-0994 are listed as Hamrah-e Aval (0994 is its "Anarestan" youth SIM
 * line); public tables agree on this (see resources/data/SOURCES.md).
 *
 * Every prefix below lies inside a block that the Communications Regulatory
 * Authority (CRA) lists as "Mobile services" in the national numbering plan it
 * communicated to the ITU on 24.VIII.2026. That plan does not name operators;
 * the operator names come from the public tables in SOURCES.md. Deliberately
 * omitted: 0940-0949 (CRA lists the 94 block as non-geographical fixed numbers,
 * not mobile), the other 0995x-0999x blocks and 09983x/09988x (allocated, but
 * the holder is not confirmed by two sources).
 */
final class Mobile implements Validator
{
    /**
     * Longest-prefix table (digits after the leading 0): [prefix => operator].
     * Keys are 3-digit blocks, plus the 4-digit Shatel Mobile (9981, 9982) and Aptel (9991) blocks.
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
        931 => 'اسپادان',
        932 => 'تالیا',
        933 => 'ایرانسل',
        934 => 'تله‌کیش',
        935 => 'ایرانسل',
        936 => 'ایرانسل',
        937 => 'ایرانسل',
        938 => 'ایرانسل',
        939 => 'ایرانسل',
        920 => 'رایتل',
        921 => 'رایتل',
        922 => 'رایتل',
        923 => 'رایتل',
        9981 => 'شاتل موبایل',
        9982 => 'شاتل موبایل',
        9991 => 'آپتل',
    ];

    /**
     * National destination codes (digits after the leading 0) that the CRA numbering plan
     * (communicated to the ITU on 24.VIII.2026) lists as "Mobile services". A prefix of a
     * number is allocated when its digits start with one of these entries.
     *
     * @var list<string>
     */
    private const ALLOCATED_NDC = [
        '900', '901', '902', '903', '904', '905', '91', '920', '921', '922', '923', '93',
        '990', '991', '992', '993', '994', '99510', '99550', '996', '9981', '9982',
        '99830', '99831', '99832', '99888', '99900', '99901', '99902', '99903', '9991',
        '99921', '99930', '99931', '99932', '99933', '99934', '9995', '99969', '99977',
        '9998', '9999',
    ];

    public static function isValid(mixed $value): bool
    {
        return self::validate($value)->isValid();
    }

    /**
     * True when the number is well-formed AND its prefix lies in a block that the published
     * national numbering plan lists for mobile services.
     *
     * `false` for a well-formed number means "not in the published plan" (the prefix may be
     * newer than the data), not "invalid"; use {@see self::isValid()} for the shape check.
     */
    public static function isAllocated(mixed $value): bool
    {
        return (self::validate($value)->details()['allocated'] ?? false) === true;
    }

    /**
     * Validate with structured errors. Error codes: invalid_format, invalid_type, input_too_long.
     * Details: normalized, operator (null when unknown), allocated (bool).
     *
     * `allocated` is true when the prefix lies in the mobile blocks of the published national
     * numbering plan. `allocated=false` on a valid result means the prefix is not in that plan
     * (it may be new), not that the number is invalid: validity is still the shape check only.
     *
     * Accepts strings, ints and integral floats; any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     */
    public static function validate(mixed $value): Result
    {
        $details = ['normalized' => '', 'operator' => null, 'allocated' => false];
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
        $details['allocated'] = self::inAllocatedBlock($mobile);

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

    private static function inAllocatedBlock(string $normalized): bool
    {
        $ndc = substr($normalized, 1);
        foreach (self::ALLOCATED_NDC as $allocated) {
            if (str_starts_with($ndc, $allocated)) {
                return true;
            }
        }

        return false;
    }

    private static function lookup(string $normalized): ?string
    {
        return self::$operators[(int) substr($normalized, 1, 4)]
            ?? self::$operators[(int) substr($normalized, 1, 3)]
            ?? null;
    }
}
