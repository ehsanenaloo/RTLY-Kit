<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Number\ArabicOptions;
use RtlyKit\Number\NumberToWords;

/**
 * Runs every row of tests/Fixtures/arabic-number-words.php through the public API.
 * Expected values come from the fixture (cited sources or spec rules), never from
 * running the implementation.
 */
final class ArabicNumberWordsFixtureTest extends TestCase
{
    /**
     * @return array{rows: list<list<mixed>>, ordinals: list<list<mixed>>, vocalized: list<list<mixed>>}
     */
    private static function fixture(): array
    {
        /** @var array{rows: list<list<mixed>>, ordinals: list<list<mixed>>, vocalized: list<list<mixed>>} $fixture */
        $fixture = require __DIR__.'/../../Fixtures/arabic-number-words.php';

        return $fixture;
    }

    /**
     * @return array<string, array{int|string, ?string, string, string, string}>
     */
    public static function cardinalRows(): array
    {
        $cases = [];
        foreach (self::fixture()['rows'] as $i => $row) {
            [$number, $gender, $case, $mode, $text, $source, $confidence] = $row;
            $label = sprintf('#%d %s %s/%s/%s [%s %s]', $i, $number, $gender ?? '-', $case, $mode, $confidence, $source);
            $cases[$label] = [$number, $gender, $case, $mode, $text];
        }

        return $cases;
    }

    #[DataProvider('cardinalRows')]
    public function test_cardinal_row(int|string $number, ?string $gender, string $case, string $mode, string $expected): void
    {
        $options = new ArabicOptions(mode: $mode, gender: $gender ?? 'm', case: $case);

        self::assertSame($expected, NumberToWords::convert($number, 'ar', $options));
        self::assertSame($expected, NumberToWords::convert($number, 'ar', ['mode' => $mode, 'gender' => $gender ?? 'm', 'case' => $case]));
    }

    public function test_default_options_equal_the_first_nominative_count_rows(): void
    {
        foreach (self::fixture()['rows'] as $row) {
            [$number, $gender, $case, $mode, $text] = $row;
            if ($gender === null && $case === 'nom' && $mode === 'count') {
                self::assertSame($text, NumberToWords::convert($number, 'ar'), (string) $number);
            }
        }
    }

    public function test_gender_is_ignored_in_count_mode(): void
    {
        foreach ([3, 8, 13, 21, 103, 1003, 3000] as $n) {
            self::assertSame(
                NumberToWords::convert($n, 'ar'),
                NumberToWords::convert($n, 'ar', new ArabicOptions(mode: 'count', gender: 'f')),
                (string) $n,
            );
        }
    }

    /**
     * @return array<string, array{int, string, string, bool, string}>
     */
    public static function ordinalRows(): array
    {
        $cases = [];
        foreach (self::fixture()['ordinals'] as $i => $row) {
            [$number, $gender, $case, $definite, $text, $source, $confidence] = $row;
            $label = sprintf('#%d %d %s/%s/%s [%s %s]', $i, $number, $gender, $case, $definite ? 'def' : 'indef', $confidence, $source);
            $cases[$label] = [$number, $gender, $case, $definite, $text];
        }

        return $cases;
    }

    #[DataProvider('ordinalRows')]
    public function test_ordinal_row(int $number, string $gender, string $case, bool $definite, string $expected): void
    {
        $options = new ArabicOptions(gender: $gender, case: $case, definite: $definite);

        self::assertSame($expected, NumberToWords::ordinal($number, 'ar', $options));
        self::assertSame($expected, NumberToWords::ordinal((string) $number, 'ar', $options));
    }

    /**
     * Case-ending projection of the vowelled source phrases: the vowelled fixture rows keep
     * the full tashkeel of their sources (inner vowels, inconsistent spellings); the
     * `diacritics: case` mode writes the case endings only. Each expected string below was
     * derived BY HAND from the source phrase in the fixture's fifth column by dropping the
     * inner vowels and keeping the ending of every inflecting word (annexed words take the
     * bare vowel, words standing alone take tanwin).
     *
     * @return array<string, array{int, string, string, string, string}>
     */
    public static function vocalizedProjection(): array
    {
        return [
            // S3: «ثَلاثَةُ آلافِ رَجُلٍ» (the fixture column keeps the table form with tanwin)
            '3000 m nom' => [3000, 'm', 'nom', 'noun', 'ثلاثةُ آلافِ'],
            '3000 m acc' => [3000, 'm', 'acc', 'noun', 'ثلاثةَ آلافِ'],
            '3000 m gen' => [3000, 'm', 'gen', 'noun', 'ثلاثةِ آلافِ'],
            '100 m nom' => [100, 'm', 'nom', 'noun', 'مئةُ'],
            '100 m acc' => [100, 'm', 'acc', 'noun', 'مئةَ'],
            '100 m gen' => [100, 'm', 'gen', 'noun', 'مئةِ'],
            '800 m gen' => [800, 'm', 'gen', 'noun', 'ثمانِمئةِ'],
            '1000 m nom' => [1000, 'm', 'nom', 'noun', 'ألفُ'],
            '1000 m acc' => [1000, 'm', 'acc', 'noun', 'ألفَ'],
            '1000 m gen' => [1000, 'm', 'gen', 'noun', 'ألفِ'],
            '200 m nom' => [200, 'm', 'nom', 'noun', 'مئتا'],
            '200 m gen' => [200, 'm', 'gen', 'noun', 'مئتَيْ'],
            '11 m acc' => [11, 'm', 'acc', 'noun', 'أحدَ عشرَ'],
            '11 f nom' => [11, 'f', 'nom', 'noun', 'إحدى عشرةَ'],
            '12 m nom' => [12, 'm', 'nom', 'noun', 'اثنا عشرَ'],
            '12 f nom' => [12, 'f', 'nom', 'noun', 'اثنتا عشرةَ'],
            '13 f nom' => [13, 'f', 'nom', 'noun', 'ثلاثَ عشرةَ'],
            '18 f nom' => [18, 'f', 'nom', 'noun', 'ثمانيَ عشرةَ'],
            '3 f nom' => [3, 'f', 'nom', 'noun', 'ثلاثُ'],
            '8 f nom' => [8, 'f', 'nom', 'noun', 'ثماني'],
            '10 f nom' => [10, 'f', 'nom', 'noun', 'عشرُ'],
            '3 m nom' => [3, 'm', 'nom', 'noun', 'ثلاثةُ'],
            // S2: «أَلْفَانِ وَتِسْعُمِئَةٍ وَٱثْنَتَا عَشْرَةَ سَنَةً» (alif wasl written as plain alif)
            '2912 f nom' => [2912, 'f', 'nom', 'noun', 'ألفانِ وتسعُمئةٍ واثنتا عشرةَ'],
            // S8: the academy example, vowelled throughout (the same spelling as the fixture)
            '179153635 m nom' => [179153635, 'm', 'nom', 'noun', 'مئةٌ وتسعةٌ وسبعونَ مليونًا ومئةٌ وثلاثةٌ وخمسونَ ألفًا وستُّمئةٍ وخمسةٌ وثلاثونَ'],
        ];
    }

    #[DataProvider('vocalizedProjection')]
    public function test_vowelled_source_phrases_in_case_ending_form(int $number, string $gender, string $case, string $mode, string $expected): void
    {
        $options = new ArabicOptions(mode: $mode, gender: $gender, case: $case, diacritics: 'case');

        self::assertSame($expected, NumberToWords::convert($number, 'ar', $options));
    }

    /**
     * Fixture rows whose source vowelling contradicts the spec's own rule R7 (the
     * multiplier takes the case, مئة is the genitive annexed to it). The implementation
     * follows R7; the sources' endings are listed so the difference stays visible.
     *
     * @return array<string, array{int, string, string, string, string}>
     */
    public static function vocalizedDeviations(): array
    {
        return [
            // source (S3): ثَلاثُمئةُ — damma on مئة
            '300 nom (source: ثَلاثُمئةُ)' => [300, 'm', 'nom', 'ثلاثُمئةِ', 'ثَلاثُمئةُ'],
            // source (S3): أَرْبَعَمئةَ — fatha on مئة
            '400 acc (source: أَرْبَعَمئةَ)' => [400, 'm', 'acc', 'أربعَمئةِ', 'أَرْبَعَمئةَ'],
        ];
    }

    #[DataProvider('vocalizedDeviations')]
    public function test_hundreds_follow_rule_r7_not_the_inconsistent_source_endings(int $number, string $gender, string $case, string $expected, string $sourceForm): void
    {
        $got = NumberToWords::convert($number, 'ar', new ArabicOptions(mode: 'noun', gender: $gender, case: $case, diacritics: 'case'));

        self::assertSame($expected, $got);
        self::assertNotSame($sourceForm, $got);
    }

    public function test_every_vocalized_row_matches_the_plain_output_once_marks_are_removed(): void
    {
        foreach (self::fixture()['vocalized'] as $row) {
            [$number, $gender, $case, $vowelled] = $row;
            // N3: strip short vowels, shadda, sukun and alif wasl; N1: strip the tanwin alif of tamyiz words.
            $stripped = (string) preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', str_replace('ٱ', 'ا', (string) $vowelled));
            $stripped = (string) preg_replace('/(ألف|مليون)ا(?=\s|$)/u', '$1', $stripped);

            self::assertSame(
                $stripped,
                NumberToWords::convert($number, 'ar', new ArabicOptions(mode: 'noun', gender: $gender, case: $case)),
                sprintf('%s %s %s', $number, $gender, $case),
            );
        }
    }
}
