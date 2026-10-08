<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;
use RtlyKit\Number\Digits;

/**
 * Iranian Sheba (IBAN) validator: `IR` + 2 check digits + 22-digit BBAN,
 * ISO 7064 mod-97-10.
 */
final class Sheba implements Validator
{
    /**
     * Bank identifier to bank name, loaded lazily from resources/data/sheba-banks.php.
     *
     * @return array<string, string>
     */
    private static function banks(): array
    {
        /** @var array<string, string> $table */
        $table = DataTables::load('sheba-banks');

        return $table;
    }

    public static function isValid(mixed $value): bool
    {
        return self::validate($value)->isValid();
    }

    /**
     * Validate with structured errors. Error codes: invalid_format, invalid_checksum,
     * invalid_type, input_too_long.
     * Details: normalized, bank_code, bank_name (null when the code is unknown).
     * Accepts strings, ints and integral floats; any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     */
    public static function validate(mixed $value): Result
    {
        $details = ['normalized' => '', 'bank_code' => null, 'bank_name' => null];
        $input = Input::coerce($value, $details);

        if ($input instanceof Result) {
            return $input;
        }

        $sheba = self::normalize($input);
        $details['normalized'] = $sheba;

        if (preg_match('/^IR\d{24}$/', $sheba) !== 1) {
            return Result::invalid('invalid_format', $details);
        }

        $bankCode = substr($sheba, 4, 3);
        $details['bank_code'] = $bankCode;
        $details['bank_name'] = self::banks()[$bankCode] ?? null;

        // Move "IRkk" to the end with letters converted (I=18, R=27) and check mod 97.
        $rearranged = substr($sheba, 4).'1827'.substr($sheba, 2, 2);

        if (self::mod97($rearranged) !== 1) {
            return Result::invalid('invalid_checksum', $details);
        }

        return Result::valid($details);
    }

    public static function getBankName(mixed $value): ?string
    {
        $result = self::validate($value);
        $name = $result->details()['bank_name'] ?? null;

        return $result->isValid() && is_string($name) ? $name : null;
    }

    /**
     * Uppercase, strip spaces/hyphens, convert Persian/Arabic digits.
     * A bare 24-digit string (the common "without IR" form) gets the `IR`
     * prefix; any other input is left as typed so it fails validation.
     */
    public static function normalize(string $sheba): string
    {
        $sheba = strtoupper(Digits::toEnglish(trim($sheba)));
        $sheba = str_replace([' ', '-', "\u{200C}"], '', $sheba);

        if (preg_match('/^\d{24}$/', $sheba) === 1) {
            return 'IR'.$sheba;
        }

        return $sheba;
    }

    private static function mod97(string $number): int
    {
        $checksum = 0;
        $len = strlen($number);

        for ($i = 0; $i < $len; $i++) {
            $checksum = ($checksum * 10 + (int) $number[$i]) % 97;
        }

        return $checksum;
    }
}
