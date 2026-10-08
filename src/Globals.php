<?php

declare(strict_types=1);

namespace RtlyKit;

use ReflectionFunction;

/**
 * Opt-in short global function names.
 *
 * By default RTLY-Kit defines no global function: the helpers live in the
 * `RtlyKit` namespace (`use function RtlyKit\jdate;`) and cannot collide with
 * anything. Call {@see Globals::register()} once, for example in your
 * bootstrap file, to also get `jdate()`, `is_national_code()`, ... globally.
 *
 * Only names that are still free are defined. Names already taken by another
 * function are never overwritten and never raise an error; they are returned
 * so you can decide what to do (log them, fail the boot, or ignore them and
 * keep using the namespaced function):
 *
 *     $skipped = \RtlyKit\Globals::register();
 *     if ($skipped !== []) {
 *         error_log('RTLY-Kit globals skipped: '.implode(', ', $skipped));
 *     }
 */
final class Globals
{
    /**
     * Every global name the opt-in registers (identical to the namespaced helper names).
     */
    public const NAMES = [
        'jdate',
        'hdate',
        'hebrew_date',
        'to_persian_digits',
        'to_english_digits',
        'to_persian',
        'to_english',
        'is_national_code',
        'is_sheba',
        'is_bank_card',
        'is_mobile',
        'is_postal_code',
        'is_vehicle_plate',
        'validate_national_code',
        'validate_sheba',
        'validate_bank_card',
        'validate_mobile',
        'validate_postal_code',
        'validate_vehicle_plate',
        'number_to_words',
        'normalize_text',
        'contains_rtl',
        'text_direction',
        'is_iran_holiday',
        'prayer_times',
        'format_number',
        'ordinal',
    ];

    /**
     * Define the short global helper names that are free.
     *
     * Idempotent: calling it again defines nothing new and returns the same
     * list of skipped names (names that are RTLY-Kit's own earlier
     * definitions are not reported as skipped).
     *
     * @return list<string> names that were NOT defined because another function already uses them
     */
    public static function register(): array
    {
        $file = __DIR__.'/functions-global.php';
        $skipped = [];
        $missing = false;

        foreach (self::NAMES as $name) {
            if (! function_exists($name)) {
                $missing = true;

                continue;
            }

            if (! self::isOurs($name, $file)) {
                $skipped[] = $name;
            }
        }

        if ($missing) {
            // The file declares each function only if its name is still free.
            require_once $file;
        }

        return $skipped;
    }

    private static function isOurs(string $name, string $file): bool
    {
        // Only called for names that already exist, so the reflection cannot fail.
        $defined = (new ReflectionFunction($name))->getFileName();

        return $defined !== false && realpath($defined) === realpath($file);
    }
}
