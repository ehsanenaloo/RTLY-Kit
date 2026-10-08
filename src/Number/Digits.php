<?php

declare(strict_types=1);

namespace RtlyKit\Number;

/**
 * Digit conversion utilities (Persian, Arabic, English).
 */
final class Digits
{
    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const ENGLISH = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function toPersian(string|int|float $value): string
    {
        return str_replace(self::ENGLISH, self::PERSIAN, self::stringify($value));
    }

    public static function toArabic(string|int|float $value): string
    {
        return str_replace(self::ENGLISH, self::ARABIC, self::stringify($value));
    }

    /**
     * Stringify without relying on implicit float coercion: PHP 8.5 raises a warning for NAN and INF.
     */
    private static function stringify(string|int|float $value): string
    {
        if (is_float($value) && ! is_finite($value)) {
            return is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');
        }

        return (string) $value;
    }

    public static function toEnglish(string $value): string
    {
        return str_replace(
            [...self::PERSIAN, ...self::ARABIC],
            [...self::ENGLISH, ...self::ENGLISH],
            $value,
        );
    }

    /**
     * Convert only Arabic-Indic digits (٠-٩) to Persian digits (۰-۹), leaving English digits untouched.
     */
    public static function arabicToPersian(string $value): string
    {
        return str_replace(self::ARABIC, self::PERSIAN, $value);
    }

    /**
     * Convert any known digit set to English, then optionally to another set.
     */
    public static function convert(string $value, string $to = 'english'): string
    {
        $english = self::toEnglish($value);

        return match (strtolower($to)) {
            'persian', 'fa', 'farsi' => self::toPersian($english),
            'arabic', 'ar'           => self::toArabic($english),
            default                  => $english,
        };
    }
}
