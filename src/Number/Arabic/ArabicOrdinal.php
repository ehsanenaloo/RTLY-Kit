<?php

declare(strict_types=1);

namespace RtlyKit\Number\Arabic;

use RtlyKit\Number\ArabicOptions;

/**
 * Ordinal numbers 1-99 in Modern Standard Arabic (R17-R19 of the specification).
 *
 * @internal Not part of the public API; use {@see \RtlyKit\Number\NumberToWords::ordinal()}.
 */
final class ArabicOrdinal
{
    public const MAX = 99;

    private const MASCULINE = [1 => 'أول', 'ثاني', 'ثالث', 'رابع', 'خامس', 'سادس', 'سابع', 'ثامن', 'تاسع', 'عاشر'];

    private const FEMININE = [1 => 'أولى', 'ثانية', 'ثالثة', 'رابعة', 'خامسة', 'سادسة', 'سابعة', 'ثامنة', 'تاسعة', 'عاشرة'];

    private const TENS = [2 => 'عشر', 'ثلاث', 'أربع', 'خمس', 'ست', 'سبع', 'ثمان', 'تسع'];

    /**
     * @param int $n 1..99
     */
    public static function words(int $n, ArabicOptions $options): string
    {
        $feminine = $options->gender === ArabicOptions::GENDER_FEMININE;
        $article = $options->definite ? 'ال' : '';

        if ($n <= 10) {
            return self::unit($n, $feminine, $options);
        }

        if ($n <= 19) {
            // Both parts agree with the noun; the compound is built on fatha, so it has no case.
            $first = $n === 11 ? ($feminine ? 'حادية' : 'حادي') : ($feminine ? self::FEMININE[$n - 10] : self::MASCULINE[$n - 10]);

            return $article.$first.' '.($feminine ? 'عشرة' : 'عشر');
        }

        $nominative = $options->case === ArabicOptions::CASE_NOMINATIVE;
        $tens = $article.self::TENS[intdiv($n, 10)].($nominative ? 'ون' : 'ين');
        $unit = $n % 10;
        if ($unit === 0) {
            return $tens;
        }

        // 21-99: the ordinal of the unit (agreeing with the noun), then «و» and the tens.
        if ($unit === 1) {
            $first = $options->definite
                ? 'ال'.($feminine ? 'حادية' : 'حادي')
                : self::indefiniteFirstOfTwentyOne($feminine, $options);
        } else {
            $first = self::unit($unit, $feminine, $options);
        }

        return $first.' و'.$tens;
    }

    private static function unit(int $n, bool $feminine, ArabicOptions $options): string
    {
        if ($feminine) {
            return ($options->definite ? 'ال' : '').self::FEMININE[$n];
        }

        if ($n === 2 && ! $options->definite) {
            // Defective: ثانٍ (plain ثان) in the nominative and genitive, ثانيا in the accusative.
            return $options->case === ArabicOptions::CASE_ACCUSATIVE ? 'ثانيا' : 'ثان';
        }

        return ($options->definite ? 'ال' : '').self::MASCULINE[$n];
    }

    private static function indefiniteFirstOfTwentyOne(bool $feminine, ArabicOptions $options): string
    {
        if ($feminine) {
            return 'حادية';
        }

        return $options->case === ArabicOptions::CASE_ACCUSATIVE ? 'حاديا' : 'حاد';
    }
}
