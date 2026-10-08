<?php

declare(strict_types=1);

namespace RtlyKit\Validation;

use RtlyKit\Contracts\Validator;
use RtlyKit\Number\Digits;

/**
 * Iranian Bank Card (شماره کارت) validator: 16 digits, Luhn checksum.
 */
final class BankCard implements Validator
{
    /**
     * BIN to bank name, loaded lazily from resources/data/bank-bins.php.
     *
     * @return array<int, string>
     */
    private static function bins(): array
    {
        /** @var array<int, string> $table */
        $table = DataTables::load('bank-bins');

        return $table;
    }

    public static function isValid(mixed $value): bool
    {
        return self::validate($value)->isValid();
    }

    /**
     * Validate with structured errors. Error codes: invalid_format,
     * repeated_digits, invalid_checksum, invalid_type, input_too_long. Details: normalized, bin, bank_name.
     * Accepts strings, ints and integral floats; any other type or a string over 4096 bytes yields
     * `invalid_type` / `input_too_long`.
     */
    public static function validate(mixed $value): Result
    {
        $details = ['normalized' => '', 'bin' => null, 'bank_name' => null];
        $input = Input::coerce($value, $details);

        if ($input instanceof Result) {
            return $input;
        }

        $card = self::normalize($input);
        $details['normalized'] = $card;

        if (preg_match('/^\d{16}$/', $card) !== 1) {
            return Result::invalid('invalid_format', $details);
        }

        $bin = substr($card, 0, 6);
        $details['bin'] = $bin;
        $details['bank_name'] = self::bins()[(int) $bin] ?? null;

        if (preg_match('/^(\d)\1{15}$/', $card) === 1) {
            return Result::invalid('repeated_digits', $details);
        }

        if (! self::luhn($card)) {
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

    public static function normalize(string $card): string
    {
        return preg_replace('/\D/', '', Digits::toEnglish($card)) ?? '';
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $len = strlen($digits);

        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $digits[$len - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
