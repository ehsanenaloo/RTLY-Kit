<?php

declare(strict_types=1);

/*
 * Short GLOBAL aliases of the namespaced helpers in src/helpers.php.
 *
 * This file is NOT autoloaded. It is included only by \RtlyKit\Globals::register(),
 * which checks every name first, so a function is declared here only when no
 * function of that name exists yet. Do not require it directly.
 *
 * Generated from the signatures of the namespaced helpers; keep both in sync
 * (tests/Unit/GlobalsTest.php verifies that).
 */

// Exercised only in isolated processes (GlobalsTest), which pcov does not merge.
// @codeCoverageIgnoreStart
if (! function_exists('jdate')) {
    /**
     * Create a new Jalali instance.
     */
    function jdate(\DateTimeInterface|string|int|null $time = null, ?\DateTimeZone $timezone = null): \RtlyKit\Calendar\Jalali
    {
        return \RtlyKit\jdate($time, $timezone);
    }
}

if (! function_exists('hdate')) {
    /**
     * Create a new Hijri instance.
     */
    function hdate(\DateTimeInterface|string|int|null $time = null, ?\DateTimeZone $timezone = null): \RtlyKit\Calendar\Hijri
    {
        return \RtlyKit\hdate($time, $timezone);
    }
}

if (! function_exists('hebrew_date')) {
    /**
     * Create a new Hebrew calendar instance.
     */
    function hebrew_date(\DateTimeInterface|string|int|null $time = null, ?\DateTimeZone $timezone = null): \RtlyKit\Calendar\Hebrew
    {
        return \RtlyKit\hebrew_date($time, $timezone);
    }
}

if (! function_exists('to_persian_digits')) {
    /**
     * Convert English digits to Persian digits.
     */
    function to_persian_digits(string|int|float $value): string
    {
        return \RtlyKit\to_persian_digits($value);
    }
}

if (! function_exists('to_english_digits')) {
    /**
     * Convert Persian / Arabic digits to English digits.
     */
    function to_english_digits(string $value): string
    {
        return \RtlyKit\to_english_digits($value);
    }
}

if (! function_exists('to_persian')) {
    /**
     * Convert any digits to Persian digits (short alias of to_persian_digits).
     */
    function to_persian(string|int|float $value): string
    {
        return \RtlyKit\to_persian($value);
    }
}

if (! function_exists('to_english')) {
    /**
     * Convert Persian / Arabic digits to English digits (short alias of to_english_digits).
     */
    function to_english(string $value): string
    {
        return \RtlyKit\to_english($value);
    }
}

if (! function_exists('is_national_code')) {
    /**
     * Validate Iranian National Code.
     */
    function is_national_code(mixed $value): bool
    {
        return \RtlyKit\is_national_code($value);
    }
}

if (! function_exists('is_sheba')) {
    /**
     * Validate Iranian Sheba / IBAN.
     */
    function is_sheba(mixed $value): bool
    {
        return \RtlyKit\is_sheba($value);
    }
}

if (! function_exists('is_bank_card')) {
    /**
     * Validate Iranian Bank Card number.
     */
    function is_bank_card(mixed $value): bool
    {
        return \RtlyKit\is_bank_card($value);
    }
}

if (! function_exists('is_mobile')) {
    /**
     * Validate Iranian Mobile number.
     */
    function is_mobile(mixed $value): bool
    {
        return \RtlyKit\is_mobile($value);
    }
}

if (! function_exists('is_postal_code')) {
    /**
     * Validate Iranian Postal Code.
     */
    function is_postal_code(mixed $value): bool
    {
        return \RtlyKit\is_postal_code($value);
    }
}

if (! function_exists('is_vehicle_plate')) {
    /**
     * Validate Iranian Vehicle Plate.
     */
    function is_vehicle_plate(mixed $value): bool
    {
        return \RtlyKit\is_vehicle_plate($value);
    }
}

if (! function_exists('validate_national_code')) {
    /**
     * Validate an Iranian National Code with structured errors.
     */
    function validate_national_code(mixed $value): \RtlyKit\Validation\Result
    {
        return \RtlyKit\validate_national_code($value);
    }
}

if (! function_exists('validate_sheba')) {
    /**
     * Validate an Iranian Sheba / IBAN with structured errors.
     */
    function validate_sheba(mixed $value): \RtlyKit\Validation\Result
    {
        return \RtlyKit\validate_sheba($value);
    }
}

if (! function_exists('validate_bank_card')) {
    /**
     * Validate an Iranian bank card number with structured errors.
     */
    function validate_bank_card(mixed $value): \RtlyKit\Validation\Result
    {
        return \RtlyKit\validate_bank_card($value);
    }
}

if (! function_exists('validate_mobile')) {
    /**
     * Validate an Iranian mobile number with structured errors.
     */
    function validate_mobile(mixed $value): \RtlyKit\Validation\Result
    {
        return \RtlyKit\validate_mobile($value);
    }
}

if (! function_exists('validate_postal_code')) {
    /**
     * Validate an Iranian postal code with structured errors.
     */
    function validate_postal_code(mixed $value): \RtlyKit\Validation\Result
    {
        return \RtlyKit\validate_postal_code($value);
    }
}

if (! function_exists('validate_vehicle_plate')) {
    /**
     * Validate an Iranian vehicle plate with structured errors.
     */
    function validate_vehicle_plate(mixed $value): \RtlyKit\Validation\Result
    {
        return \RtlyKit\validate_vehicle_plate($value);
    }
}

if (! function_exists('number_to_words')) {
    /**
     * Convert number to words ('fa' by default, or 'ar').
     */
    function number_to_words(string|int|float $number, string $locale = 'fa'): string
    {
        return \RtlyKit\number_to_words($number, $locale);
    }
}

if (! function_exists('normalize_text')) {
    /**
     * Normalize Persian/Arabic text.
     */
    function normalize_text(string $text): string
    {
        return \RtlyKit\normalize_text($text);
    }
}

if (! function_exists('contains_rtl')) {
    /**
     * Check if text contains RTL characters.
     */
    function contains_rtl(string $text): bool
    {
        return \RtlyKit\contains_rtl($text);
    }
}

if (! function_exists('text_direction')) {
    /**
     * Detect text direction (rtl/ltr).
     */
    function text_direction(string $text): string
    {
        return \RtlyKit\text_direction($text);
    }
}

if (! function_exists('is_iran_holiday')) {
    /**
     * Check if a Jalali date is an official Iranian fixed holiday.
     */
    function is_iran_holiday(\RtlyKit\Calendar\Jalali|int $year, ?int $month = null, ?int $day = null): bool
    {
        return \RtlyKit\is_iran_holiday($year, $month, $day);
    }
}

if (! function_exists('prayer_times')) {
    /**
     * Get prayer times for a city.
     *
     * @return array{fajr: ?string, sunrise: ?string, dhuhr: string, asr: ?string, maghrib: ?string, isha: ?string}
     */
    function prayer_times(string $city = 'tehran', string $method = 'Tehran'): array
    {
        return \RtlyKit\prayer_times($city, $method);
    }
}

if (! function_exists('format_number')) {
    /**
     * Format number with Persian thousand separators.
     */
    function format_number(string|int|float $number): string
    {
        return \RtlyKit\format_number($number);
    }
}

if (! function_exists('ordinal')) {
    /**
     * Persian ordinal form of a number.
     */
    function ordinal(int|float $number): string
    {
        return \RtlyKit\ordinal($number);
    }
}
// @codeCoverageIgnoreEnd
