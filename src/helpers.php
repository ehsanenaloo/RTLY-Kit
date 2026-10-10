<?php

declare(strict_types=1);

/*
 * Namespaced helper functions (always loaded by Composer).
 *
 * They live in the `RtlyKit` namespace, so they can never collide with a
 * function of the application or of another package:
 *
 *     use function RtlyKit\jdate;
 *
 *     echo jdate()->format('Y/m/d');
 *
 * Short global names (`jdate()`, `is_national_code()`, ...) are opt-in via
 * \RtlyKit\Globals::register(), which defines only the names that are free
 * and reports the ones it skipped.
 */

namespace RtlyKit;

use DateTimeInterface;
use DateTimeZone;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Holiday\IranHolidays;
use RtlyKit\Number\Digits;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;
use RtlyKit\Prayer\PrayerTimes;
use RtlyKit\Support\AutoLoader;
use RtlyKit\Text\Detector;
use RtlyKit\Text\Normalizer;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\Mobile;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\PostalCode;
use RtlyKit\Validation\Result;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;

// Boot optional integrations (Carbon macros, ...)
AutoLoader::boot();

/**
 * Create a new Jalali instance.
 */
function jdate(DateTimeInterface|string|int|null $time = null, ?DateTimeZone $timezone = null): Jalali
{
    return Jalali::make($time, $timezone);
}

/**
 * Create a new Hijri instance.
 */
function hdate(DateTimeInterface|string|int|null $time = null, ?DateTimeZone $timezone = null): Hijri
{
    return Hijri::make($time, $timezone);
}

/**
 * Create a new Hebrew calendar instance.
 */
function hebrew_date(DateTimeInterface|string|int|null $time = null, ?DateTimeZone $timezone = null): Hebrew
{
    return Hebrew::make($time, $timezone);
}

/**
 * Convert English digits to Persian digits.
 */
function to_persian_digits(string|int|float $value): string
{
    return Digits::toPersian($value);
}

/**
 * Convert Persian / Arabic digits to English digits.
 */
function to_english_digits(string $value): string
{
    return Digits::toEnglish($value);
}

/**
 * Convert English digits to Persian digits (short alias of to_persian_digits; Arabic-Indic digits are left as they are, see Digits::convert()).
 */
function to_persian(string|int|float $value): string
{
    return Digits::toPersian($value);
}

/**
 * Convert Persian / Arabic digits to English digits (short alias of to_english_digits).
 */
function to_english(string $value): string
{
    return Digits::toEnglish($value);
}

/**
 * Validate Iranian National Code.
 */
function is_national_code(mixed $value): bool
{
    return NationalCode::isValid($value);
}

/**
 * Validate Iranian Sheba / IBAN.
 */
function is_sheba(mixed $value): bool
{
    return Sheba::isValid($value);
}

/**
 * Validate Iranian Bank Card number.
 */
function is_bank_card(mixed $value): bool
{
    return BankCard::isValid($value);
}

/**
 * Validate Iranian Mobile number.
 */
function is_mobile(mixed $value): bool
{
    return Mobile::isValid($value);
}

/**
 * Validate Iranian Postal Code.
 */
function is_postal_code(mixed $value): bool
{
    return PostalCode::isValid($value);
}

/**
 * Validate Iranian Vehicle Plate.
 */
function is_vehicle_plate(mixed $value): bool
{
    return VehiclePlate::isValid($value);
}

/**
 * Validate an Iranian National Code with structured errors.
 */
function validate_national_code(mixed $value): Result
{
    return NationalCode::validate($value);
}

/**
 * Validate an Iranian Sheba / IBAN with structured errors.
 */
function validate_sheba(mixed $value): Result
{
    return Sheba::validate($value);
}

/**
 * Validate an Iranian bank card number with structured errors.
 */
function validate_bank_card(mixed $value): Result
{
    return BankCard::validate($value);
}

/**
 * Validate an Iranian mobile number with structured errors.
 */
function validate_mobile(mixed $value): Result
{
    return Mobile::validate($value);
}

/**
 * Validate an Iranian postal code with structured errors.
 */
function validate_postal_code(mixed $value): Result
{
    return PostalCode::validate($value);
}

/**
 * Validate an Iranian vehicle plate with structured errors.
 */
function validate_vehicle_plate(mixed $value): Result
{
    return VehiclePlate::validate($value);
}

/**
 * Convert number to words ('fa' by default, or 'ar').
 */
function number_to_words(int|float|string $number, string $locale = 'fa'): string
{
    return NumberToWords::convert($number, $locale);
}

/**
 * Normalize Persian/Arabic text.
 */
function normalize_text(string $text): string
{
    return Normalizer::normalize($text);
}

/**
 * Check if text contains RTL characters.
 */
function contains_rtl(string $text): bool
{
    return Detector::containsRtl($text);
}

/**
 * Detect text direction (rtl/ltr).
 */
function text_direction(string $text): string
{
    return Detector::direction($text);
}

/**
 * Check if a Jalali date is an official Iranian fixed holiday.
 */
function is_iran_holiday(Jalali|int $year, ?int $month = null, ?int $day = null): bool
{
    return IranHolidays::isHoliday($year, $month, $day);
}

/**
 * Get prayer times for a city.
 *
 * @return array{fajr: ?string, sunrise: ?string, dhuhr: string, asr: ?string, maghrib: ?string, isha: ?string}
 */
function prayer_times(string $city = 'tehran', string $method = 'Tehran'): array
{
    return PrayerTimes::forCity($city, $method)->getTimes();
}

/**
 * Format number with Persian thousand separators.
 */
function format_number(int|float|string $number): string
{
    return Format::withSeparator($number);
}

/**
 * Persian ordinal form of a number.
 */
function ordinal(int|float $number): string
{
    return Format::ordinal($number);
}
