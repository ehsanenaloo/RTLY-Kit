<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Exceptions\UnsupportedLocaleException;
use RtlyKit\Number\ArabicOptions;
use RtlyKit\Number\NumberToWords;

final class ArabicNumberWordsTest extends TestCase
{
    private const NINES = 'تسعمئة وتسعة وتسعون';

    /* ---------------- scale boundaries and the cap ---------------- */

    public function test_group_boundaries(): void
    {
        self::assertSame('تسعمئة وتسعة وتسعون', NumberToWords::convert(999, 'ar'));
        self::assertSame('ألف', NumberToWords::convert(1000, 'ar'));
        self::assertSame(self::NINES.' ألف و'.self::NINES, NumberToWords::convert(999_999, 'ar'));
        self::assertSame('مليون', NumberToWords::convert(1_000_000, 'ar'));
        self::assertSame(self::NINES.' مليون و'.self::NINES.' ألف و'.self::NINES, NumberToWords::convert(999_999_999, 'ar'));
        self::assertSame('مليار', NumberToWords::convert(1_000_000_000, 'ar'));
        self::assertSame(
            self::NINES.' مليار و'.self::NINES.' مليون و'.self::NINES.' ألف و'.self::NINES,
            NumberToWords::convert(999_999_999_999, 'ar'),
        );
        self::assertSame('تريليون', NumberToWords::convert(1_000_000_000_000, 'ar'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function powersOfTen(): array
    {
        return [
            '10^3' => ['1000', 'ألف'],
            '10^6' => ['1000000', 'مليون'],
            '10^9' => ['1000000000', 'مليار'],
            '10^12' => ['1000000000000', 'تريليون'],
            '10^15' => ['1000000000000000', 'كوادريليون'],
            '10^18' => ['1000000000000000000', 'كوينتيليون'],
            '10^21' => ['1000000000000000000000', 'سكستيليون'],
            '10^24' => ['1000000000000000000000000', 'سبتيليون'],
            '2 * 10^24' => ['2000000000000000000000000', 'سبتيليونان'],
            '3 * 10^24' => ['3000000000000000000000000', 'ثلاثة سبتيليونات'],
            '10^26' => ['100000000000000000000000000', 'مئة سبتيليون'],
        ];
    }

    #[DataProvider('powersOfTen')]
    public function test_scale_names(string $digits, string $expected): void
    {
        self::assertSame($expected, NumberToWords::convert($digits, 'ar'));
    }

    public function test_largest_supported_value_uses_every_scale(): void
    {
        $scales = ['سبتيليون', 'سكستيليون', 'كوينتيليون', 'كوادريليون', 'تريليون', 'مليار', 'مليون', 'ألف'];
        $expected = [];
        foreach ($scales as $scale) {
            $expected[] = self::NINES.' '.$scale;
        }
        $expected[] = self::NINES;

        self::assertSame(implode(' و', $expected), NumberToWords::convert(str_repeat('9', 27), 'ar'));
    }

    public function test_the_cap_is_one_digit_above_the_largest_value(): void
    {
        try {
            NumberToWords::convert('1'.str_repeat('0', 27), 'ar');
            self::fail('expected an exception');
        } catch (InvalidNumberException $e) {
            self::assertSame(ErrorCode::NumberTooLarge, $e->getErrorCode());
            self::assertSame('10^27 - 1', $e->getContext()['limit']);
        }
    }

    public function test_php_int_extremes(): void
    {
        $expected = 'تسعة كوينتيليونات ومئتان وثلاثة وعشرون كوادريليون وثلاثمئة واثنان وسبعون تريليون'
            .' وستة وثلاثون مليار وثمانمئة وأربعة وخمسون مليون وسبعمئة وخمسة وسبعون ألف وثمانمئة وسبعة';

        self::assertSame($expected, NumberToWords::convert(PHP_INT_MAX, 'ar'));
        self::assertSame('سالب '.$expected, NumberToWords::convert('-9223372036854775807', 'ar'));
        self::assertStringStartsWith('سالب تسعة كوينتيليونات ومئتان وثلاثة وعشرون كوادريليون', NumberToWords::convert(PHP_INT_MIN, 'ar'));
    }

    public function test_floats_beyond_int_range_are_exact(): void
    {
        self::assertSame('عشرة كوينتيليونات', NumberToWords::convert(1.0e19, 'ar'));
        self::assertSame('مليار', NumberToWords::convert(1.0e9, 'ar'));
        self::assertSame('سكستيليون', NumberToWords::convert(1.0e21, 'ar'));
    }

    #[DataProvider('plurals')]
    public function test_dual_and_plural_scale_forms(int|string $number, string $expected): void
    {
        self::assertSame($expected, NumberToWords::convert($number, 'ar'));
    }

    /**
     * @return array<string, array{int|string, string}>
     */
    public static function plurals(): array
    {
        return [
            '2000' => [2000, 'ألفان'],
            '3000' => [3000, 'ثلاثة آلاف'],
            '10000' => [10_000, 'عشرة آلاف'],
            '11000' => [11_000, 'أحد عشر ألف'],
            '2 million' => [2_000_000, 'مليونان'],
            '10 million' => [10_000_000, 'عشرة ملايين'],
            '11 million' => [11_000_000, 'أحد عشر مليون'],
            '2 billion' => [2_000_000_000, 'ملياران'],
            '4 billion' => [4_000_000_000, 'أربعة مليارات'],
            '2 trillion' => [2_000_000_000_000, 'تريليونان'],
            '6 trillion' => [6_000_000_000_000, 'ستة تريليونات'],
            '2 quadrillion' => [2_000_000_000_000_000, 'كوادريليونان'],
            '9 quintillion' => ['9000000000000000000', 'تسعة كوينتيليونات'],
            '2 sextillion' => ['2000000000000000000000', 'سكستيليونان'],
            '5 sextillion' => ['5000000000000000000000', 'خمسة سكستيليونات'],
        ];
    }

    /* ---------------- R13: groups ending in 01-10 ---------------- */

    #[DataProvider('splitGroups')]
    public function test_hundreds_plus_one_to_ten_are_split(int|string $number, string $expected): void
    {
        self::assertSame($expected, NumberToWords::convert($number, 'ar'));
    }

    /**
     * @return array<string, array{int|string, string}>
     */
    public static function splitGroups(): array
    {
        return [
            '102000 (the documented example)' => [102_000, 'مئة ألف وألفان'],
            '101000' => [101_000, 'مئة ألف وألف'],
            '103000' => [103_000, 'مئة ألف وثلاثة آلاف'],
            '110000' => [110_000, 'مئة ألف وعشرة آلاف'],
            '111000 stays plain' => [111_000, 'مئة وأحد عشر ألف'],
            '120000 stays plain' => [120_000, 'مئة وعشرون ألف'],
            '200000' => [200_000, 'مئتا ألف'],
            '201000' => [201_000, 'مئتا ألف وألف'],
            '910000' => [910_000, 'تسعمئة ألف وعشرة آلاف'],
            '103000000' => [103_000_000, 'مئة مليون وثلاثة ملايين'],
            '102 billion' => [102_000_000_000, 'مئة مليار وملياران'],
            '102000 with a tail' => [102_005, 'مئة ألف وألفان وخمسة'],
            '10^24 + hundreds' => ['103000000000000000000000000', 'مئة سبتيليون وثلاثة سبتيليونات'],
        ];
    }

    /* ---------------- negatives ---------------- */

    public function test_negative_numbers(): void
    {
        self::assertSame('صفر', NumberToWords::convert(-0, 'ar'));
        self::assertSame('صفر', NumberToWords::convert('-0', 'ar'));
        self::assertSame('صفر', NumberToWords::convert(-0.0, 'ar'));
        self::assertSame('سالب واحد', NumberToWords::convert(-1, 'ar'));
        self::assertSame('سالب مليار', NumberToWords::convert('-1000000000', 'ar'));
        self::assertSame('سالب سكستيليون', NumberToWords::convert('-1000000000000000000000', 'ar'));
        self::assertSame('سالب مئة ألف وألفان', NumberToWords::convert(-102_000, 'ar'));
    }

    public function test_negative_marker_option(): void
    {
        self::assertSame('ناقص خمسة', NumberToWords::convert(-5, 'ar', new ArabicOptions(negative: 'ناقص')));
        self::assertSame('ناقص ثلاث', NumberToWords::convert(-3, 'ar', ['negative' => 'ناقص', 'mode' => 'noun', 'gender' => 'f']));
        self::assertSame('خمسة', NumberToWords::convert(5, 'ar', new ArabicOptions(negative: 'ناقص')));
    }

    public function test_negative_of_x_is_the_marker_plus_the_positive(): void
    {
        foreach ([1, 2, 3, 11, 12, 21, 100, 200, 1234, 2000, 102_000, 1_000_000, 999_999_999_999] as $n) {
            foreach ([['mode' => 'count'], ['mode' => 'noun', 'gender' => 'f', 'case' => 'gen'], ['diacritics' => 'case']] as $options) {
                self::assertSame(
                    'سالب '.NumberToWords::convert($n, 'ar', $options),
                    NumberToWords::convert(-$n, 'ar', $options),
                    $n.' '.json_encode($options),
                );
            }
        }
    }

    /* ---------------- structural properties ---------------- */

    public function test_outputs_are_clean_for_every_number_up_to_a_few_thousand(): void
    {
        $offenders = [];
        foreach ([['mode' => 'count'], ['mode' => 'noun', 'gender' => 'f'], ['mode' => 'noun', 'case' => 'gen']] as $options) {
            for ($n = 0; $n <= 3000; $n++) {
                $words = NumberToWords::convert($n, 'ar', $options);
                // No digits, no double or edge spaces, no dangling conjunction.
                if (preg_match('/\d|  |^\s|\s$| و$/u', $words) === 1 || $words !== NumberToWords::convert((string) $n, 'ar', $options)) {
                    $offenders[] = $n.' '.json_encode($options).' '.$words;
                }
            }
        }

        self::assertSame([], $offenders);
    }

    public function test_diacritic_output_is_the_plain_output_plus_marks_for_the_nominative(): void
    {
        $offenders = [];
        for ($n = 0; $n <= 2500; $n++) {
            foreach (['count', 'noun'] as $mode) {
                $plain = NumberToWords::convert($n, 'ar', ['mode' => $mode]);
                $marked = NumberToWords::convert($n, 'ar', ['mode' => $mode, 'diacritics' => 'case']);
                if ($plain !== preg_replace('/[\x{064B}-\x{0652}]/u', '', $marked)) {
                    $offenders[] = $n.' '.$mode;
                }
            }
        }

        self::assertSame([], $offenders);
    }

    public function test_diacritic_marks_are_in_canonical_order(): void
    {
        foreach ([6, 600, 6000, 66, 666, 3000, 2912, 179_153_635] as $n) {
            foreach (['nom', 'acc', 'gen'] as $case) {
                foreach (['m', 'f'] as $gender) {
                    $marked = NumberToWords::convert($n, 'ar', ['mode' => 'noun', 'gender' => $gender, 'case' => $case, 'diacritics' => 'case']);
                    // Vowel (ccc 27-32) must precede shadda (ccc 33) and sukun (ccc 34).
                    self::assertDoesNotMatchRegularExpression('/[\x{0651}\x{0652}][\x{064B}-\x{0650}]/u', $marked, $n.' '.$case.' '.$gender);
                    if (function_exists('normalizer_is_normalized')) {
                        self::assertTrue(normalizer_is_normalized($marked), $marked);
                    }
                }
            }
        }
    }

    /* ---------------- case handling ---------------- */

    #[DataProvider('caseSpellings')]
    public function test_case_changes_letters_only_where_the_spec_says(int|string $number, string $mode, string $case, string $expected): void
    {
        self::assertSame($expected, NumberToWords::convert($number, 'ar', ['mode' => $mode, 'case' => $case]));
    }

    /**
     * @return array<string, array{int|string, string, string, string}>
     */
    public static function caseSpellings(): array
    {
        return [
            '2 gen' => [2, 'count', 'gen', 'اثنين'],
            '12 acc' => [12, 'count', 'acc', 'اثني عشر'],
            '20 gen' => [20, 'count', 'gen', 'عشرين'],
            '200 acc' => [200, 'count', 'acc', 'مئتين'],
            '2000 gen' => [2000, 'count', 'gen', 'ألفين'],
            '2 million gen' => [2_000_000, 'count', 'gen', 'مليونين'],
            '2 billion gen' => [2_000_000_000, 'count', 'gen', 'مليارين'],
            '2 trillion acc' => [2_000_000_000_000, 'count', 'acc', 'تريليونين'],
            '3000 gen is invariable' => [3000, 'count', 'gen', 'ثلاثة آلاف'],
            '15 gen is invariable' => [15, 'count', 'gen', 'خمسة عشر'],
            '300 gen is invariable' => [300, 'count', 'gen', 'ثلاثمئة'],
            '200000 gen' => [200_000, 'count', 'gen', 'مئتي ألف'],
            '200 noun construct' => [200, 'noun', 'nom', 'مئتا'],
            '200 noun construct gen' => [200, 'noun', 'gen', 'مئتي'],
            '205 noun is not a construct' => [205, 'noun', 'nom', 'مئتان وخمسة'],
            '2 million noun construct' => [2_000_000, 'noun', 'nom', 'مليونا'],
            '2 million noun gen' => [2_000_000, 'noun', 'gen', 'مليوني'],
            '2 billion noun' => [2_000_000_000, 'noun', 'nom', 'مليارا'],
            '2 million and a half count' => [2_500_000, 'count', 'nom', 'مليونان وخمسمئة ألف'],
            '2 million and a half noun' => [2_500_000, 'noun', 'nom', 'مليونان وخمسمئة ألف'],
            '22 noun gen' => [22, 'noun', 'gen', 'اثنين وعشرين'],
        ];
    }

    /* ---------------- hundreds and billion options ---------------- */

    public function test_hundreds_spelling_and_joining(): void
    {
        $spaced = new ArabicOptions(joinHundreds: false);
        $maia = new ArabicOptions(hundreds: 'ma_i_a');
        $both = new ArabicOptions(hundreds: 'ma_i_a', joinHundreds: false);

        self::assertSame('ثلاث مئة', NumberToWords::convert(300, 'ar', $spaced));
        self::assertSame('ثماني مئة', NumberToWords::convert(800, 'ar', $spaced));
        self::assertSame('ست مئة وخمسة', NumberToWords::convert(605, 'ar', $spaced));
        self::assertSame('مئة', NumberToWords::convert(100, 'ar', $spaced));
        self::assertSame('مئتان', NumberToWords::convert(200, 'ar', $spaced));
        self::assertSame('ثلاث مئة ألف وتسع مئة', NumberToWords::convert(300_900, 'ar', $spaced));

        self::assertSame('مائة', NumberToWords::convert(100, 'ar', $maia));
        self::assertSame('مائتان', NumberToWords::convert(200, 'ar', $maia));
        self::assertSame('ثلاثمائة', NumberToWords::convert(300, 'ar', $maia));
        self::assertSame('ثمانمائة', NumberToWords::convert(800, 'ar', $maia));
        self::assertSame('مائتا ألف', NumberToWords::convert(200_000, 'ar', $maia));
        self::assertSame('مائة وواحد', NumberToWords::convert(101, 'ar', $maia));
        self::assertSame('ثماني مائة', NumberToWords::convert(800, 'ar', $both));
        self::assertSame('مائتين وخمسة', NumberToWords::convert(205, 'ar', new ArabicOptions(hundreds: 'ma_i_a', case: 'gen')));
    }

    public function test_billion_name(): void
    {
        $bilyon = new ArabicOptions(billion: 'bilyon');

        self::assertSame('بليون', NumberToWords::convert(1_000_000_000, 'ar', $bilyon));
        self::assertSame('بليونان', NumberToWords::convert(2_000_000_000, 'ar', $bilyon));
        self::assertSame('ثلاثة بلايين', NumberToWords::convert(3_000_000_000, 'ar', $bilyon));
        self::assertSame('أحد عشر بليون', NumberToWords::convert(11_000_000_000, 'ar', $bilyon));
        self::assertSame('بليون ومليون', NumberToWords::convert(1_001_000_000, 'ar', $bilyon));
        // Only 10^9 is affected.
        self::assertSame('تريليون', NumberToWords::convert(1_000_000_000_000, 'ar', $bilyon));
        self::assertSame('مليون', NumberToWords::convert(1_000_000, 'ar', $bilyon));
        self::assertSame('مليار', NumberToWords::convert(1_000_000_000, 'ar', ['billion' => 'milyar']));
    }

    /* ---------------- vowel marks ---------------- */

    #[DataProvider('vowelled')]
    public function test_vowelled_forms(int|string $number, string $mode, string $gender, string $case, string $expected): void
    {
        self::assertSame(
            $expected,
            NumberToWords::convert($number, 'ar', new ArabicOptions(mode: $mode, gender: $gender, case: $case, diacritics: 'case')),
        );
    }

    /**
     * Expected strings follow the endings table of the spec (section 8); entries marked
     * "extension" are standard endings for words the spec leaves open.
     *
     * @return array<string, array{int|string, string, string, string, string}>
     */
    public static function vowelled(): array
    {
        return [
            'zero' => [0, 'count', 'm', 'nom', 'صفرٌ'],
            'one, nominative' => [1, 'count', 'm', 'nom', 'واحدٌ'],
            'one, feminine accusative' => [1, 'noun', 'f', 'acc', 'واحدةً'],
            'three, bare counting' => [3, 'count', 'm', 'nom', 'ثلاثةٌ'],
            'six, feminine, genitive' => [6, 'noun', 'f', 'gen', 'ستِّ'],
            'six, feminine, count' => [6, 'count', 'f', 'nom', 'ستةٌ'],
            'eight, feminine, accusative' => [8, 'noun', 'f', 'acc', 'ثمانيَ'],
            '28 feminine' => [28, 'noun', 'f', 'nom', 'ثمانٍ وعشرونَ'],
            'two in count mode (extension)' => [2, 'count', 'm', 'nom', 'اثنانِ'],
            'two, genitive (extension)' => [2, 'count', 'm', 'gen', 'اثنَيْنِ'],
            '21 accusative' => [21, 'count', 'm', 'acc', 'واحدًا وعشرينَ'],
            '13 masculine' => [13, 'noun', 'm', 'nom', 'ثلاثةَ عشرَ'],
            '19 feminine' => [19, 'noun', 'f', 'gen', 'تسعَ عشرةَ'],
            'zero tail thousand' => [1000, 'count', 'm', 'nom', 'ألفٌ'],
            '100000 noun' => [100_000, 'noun', 'm', 'nom', 'مئةُ ألفِ'],
            '100000 count' => [100_000, 'count', 'm', 'nom', 'مئةُ ألفٍ'],
            '200000 genitive' => [200_000, 'count', 'm', 'gen', 'مئتَيْ ألفٍ'],
            '2000 noun construct' => [2000, 'noun', 'm', 'nom', 'ألفا'],
            '2000 genitive construct' => [2000, 'noun', 'm', 'gen', 'ألفَيْ'],
            '3 million noun' => [3_000_000, 'noun', 'm', 'nom', 'ثلاثةُ ملايينِ'],
            '3 million count' => [3_000_000, 'count', 'm', 'nom', 'ثلاثةُ ملايينَ'],
            '4 billion' => [4_000_000_000, 'noun', 'm', 'nom', 'أربعةُ ملياراتِ'],
            '11 thousand' => [11_000, 'count', 'm', 'nom', 'أحدَ عشرَ ألفًا'],
            '11 thousand noun' => [11_000, 'noun', 'm', 'nom', 'أحدَ عشرَ ألفَ'],
            '600 feminine accusative' => [600, 'noun', 'f', 'acc', 'ستَّمئةِ'],
            '1 billion' => [1_000_000_000, 'noun', 'm', 'acc', 'مليارَ'],
        ];
    }

    public function test_vowelled_spaced_and_ma_i_a_hundreds(): void
    {
        $spaced = static fn (string $case, string $mode = 'count'): ArabicOptions => new ArabicOptions(mode: $mode, case: $case, diacritics: 'case', joinHundreds: false);

        self::assertSame('ثماني مئةٍ', NumberToWords::convert(800, 'ar', $spaced('nom')));
        self::assertSame('ثمانيَ مئةٍ', NumberToWords::convert(800, 'ar', $spaced('acc')));
        self::assertSame('ثلاثُ مئةِ', NumberToWords::convert(300, 'ar', $spaced('nom', 'noun')));
        self::assertSame('ستُّ مئةٍ', NumberToWords::convert(600, 'ar', $spaced('nom')));
        self::assertSame(
            'مائةٌ',
            NumberToWords::convert(100, 'ar', new ArabicOptions(hundreds: 'ma_i_a', diacritics: 'case')),
        );
        self::assertSame(
            'مائتَيْنِ',
            NumberToWords::convert(200, 'ar', new ArabicOptions(hundreds: 'ma_i_a', diacritics: 'case', case: 'gen')),
        );
    }

    /* ---------------- ordinals ---------------- */

    public function test_ordinals_cover_one_to_ninety_nine(): void
    {
        for ($n = 1; $n <= 99; $n++) {
            foreach (['m', 'f'] as $gender) {
                foreach ([true, false] as $definite) {
                    foreach (['nom', 'acc', 'gen'] as $case) {
                        $words = NumberToWords::ordinal($n, 'ar', new ArabicOptions(gender: $gender, case: $case, definite: $definite));
                        self::assertNotSame('', $words);
                        self::assertDoesNotMatchRegularExpression('/\d|  |^\s|\s$/u', $words, (string) $n);
                    }
                }
            }
        }
    }

    public function test_ordinal_known_values(): void
    {
        self::assertSame('الأول', NumberToWords::ordinal(1));
        self::assertSame('الثانية', NumberToWords::ordinal(2, 'ar', ['gender' => 'f']));
        self::assertSame('الحادي عشر', NumberToWords::ordinal('11'));
        self::assertSame('الثانية عشرة', NumberToWords::ordinal(12, 'ar', ['gender' => 'f']));
        self::assertSame('العشرون', NumberToWords::ordinal(20));
        self::assertSame('العشرين', NumberToWords::ordinal(20, 'ar', ['case' => 'gen']));
        self::assertSame('الحادي والعشرون', NumberToWords::ordinal(21));
        self::assertSame('الثالثة والأربعون', NumberToWords::ordinal(43, 'ar', ['gender' => 'f']));
        self::assertSame('التاسع والتسعون', NumberToWords::ordinal(99, 'ar_SA'));
        self::assertSame('ثانيا', NumberToWords::ordinal(2, 'ar', ['definite' => false, 'case' => 'acc']));
        self::assertSame('ثان', NumberToWords::ordinal(2, 'ar', ['definite' => false]));
        self::assertSame('حادية عشرة', NumberToWords::ordinal(11, 'ar', ['definite' => false, 'gender' => 'f']));
        self::assertSame('حاد وعشرون', NumberToWords::ordinal(21, 'ar', ['definite' => false]));
        self::assertSame('حاديا وعشرين', NumberToWords::ordinal(21, 'ar', ['definite' => false, 'case' => 'acc']));
        self::assertSame('حادية وعشرون', NumberToWords::ordinal(21, 'ar', ['definite' => false, 'gender' => 'f']));
        self::assertSame('ثان وثلاثون', NumberToWords::ordinal(32, 'ar', ['definite' => false]));
        self::assertSame('الأولى', NumberToWords::ordinal('٠١', 'ar', ['gender' => 'f']));
    }

    #[DataProvider('badOrdinals')]
    public function test_ordinal_rejects_out_of_range_values(int|string $value, ErrorCode $code): void
    {
        try {
            NumberToWords::ordinal($value);
            self::fail('expected an exception');
        } catch (InvalidNumberException $e) {
            self::assertSame($code, $e->getErrorCode());
        }
    }

    /**
     * @return array<string, array{int|string, ErrorCode}>
     */
    public static function badOrdinals(): array
    {
        return [
            'zero' => [0, ErrorCode::InvalidNumber],
            'negative' => [-3, ErrorCode::InvalidNumber],
            'minus zero string' => ['-0', ErrorCode::InvalidNumber],
            '100' => [100, ErrorCode::NumberTooLarge],
            'big' => [123_456, ErrorCode::NumberTooLarge],
            'huge string' => [str_repeat('9', 40), ErrorCode::NumberTooLarge],
            'text' => ['abc', ErrorCode::InvalidNumber],
            'decimal' => ['1.5', ErrorCode::InvalidNumber],
            'oversized' => [str_repeat('0', 5000).'1', ErrorCode::InputTooLong],
        ];
    }

    public function test_ordinal_locale_and_options_errors(): void
    {
        try {
            NumberToWords::ordinal(3, 'fa');
            self::fail('expected an exception');
        } catch (UnsupportedLocaleException $e) {
            self::assertSame(ErrorCode::UnsupportedLocale, $e->getErrorCode());
        }

        $this->expectException(InvalidNumberException::class);
        NumberToWords::ordinal(3, 'ar', ['diacritics' => 'case']);
    }

    /* ---------------- options ---------------- */

    public function test_defaults(): void
    {
        $o = new ArabicOptions();

        self::assertSame(['count', 'm', 'nom', 'none', 'mi_a', true, 'سالب', 'milyar', true], [
            $o->mode, $o->gender, $o->case, $o->diacritics, $o->hundreds, $o->joinHundreds, $o->negative, $o->billion, $o->definite,
        ]);
        self::assertEquals($o, ArabicOptions::fromArray([]));
        self::assertEquals($o, ArabicOptions::resolve(null));
        self::assertSame($o, ArabicOptions::resolve($o));
        self::assertSame(NumberToWords::convert(1234, 'ar'), NumberToWords::convert(1234, 'ar', $o));
        self::assertSame(NumberToWords::convert(1234, 'ar'), NumberToWords::convert(1234, 'ar', []));
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_options_throw_library_exceptions(array $options): void
    {
        try {
            NumberToWords::convert(5, 'ar', $options);
            self::fail('expected an exception');
        } catch (InvalidNumberException $e) {
            self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
        }
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function invalidOptions(): array
    {
        return [
            'unknown key' => [['colour' => 'red']],
            'integer key' => [[0 => 'm']],
            'long unknown key' => [[str_repeat('k', 500) => 1]],
            'bad mode' => [['mode' => 'plural']],
            'bad gender' => [['gender' => 'x']],
            'bad case' => [['case' => 'dat']],
            'bad diacritics' => [['diacritics' => 'full']],
            'bad hundreds' => [['hundreds' => 'maia']],
            'bad billion' => [['billion' => 'trillion']],
            'mode of wrong type' => [['mode' => 3]],
            'array value' => [['gender' => ['m']]],
            'flag of wrong type' => [['joinHundreds' => 'yes']],
            'definite of wrong type' => [['definite' => 1]],
            'empty negative' => [['negative' => '']],
            'negative with space' => [['negative' => 'ناقص جدا']],
            'negative control' => [['negative' => "a\0b"]],
            'negative invalid utf8' => [['negative' => "\xff"]],
            'negative too long' => [['negative' => str_repeat('ن', 40)]],
            'negative of wrong type' => [['negative' => 5]],
            'case sensitive' => [['mode' => 'COUNT']],
        ];
    }

    public function test_options_are_rejected_for_persian(): void
    {
        try {
            NumberToWords::convert(5, 'fa', ['mode' => 'noun']);
            self::fail('expected an exception');
        } catch (InvalidNumberException $e) {
            self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
        }

        self::assertSame('بیست و یک', NumberToWords::convert(21, 'fa'));
        self::assertSame('بیست و یک', NumberToWords::convert(21, 'FA_ir'));
    }

    public function test_unsupported_locale_is_checked_before_options(): void
    {
        $this->expectException(UnsupportedLocaleException::class);
        NumberToWords::convert(5, 'xx', ['mode' => 'bogus']);
    }

    public function test_input_forms_for_arabic(): void
    {
        self::assertSame('ألف ومئتان وأربعة وثلاثون', NumberToWords::convert('١٬٢٣٤', 'ar'));
        self::assertSame('ألف ومئتان وأربعة وثلاثون', NumberToWords::convert('1,234', 'AR'));
        self::assertSame('ثلاثة', NumberToWords::convert(3.0, 'ar'));
        self::assertSame('خمسة', NumberToWords::convert('+5', 'ar'));
        self::assertSame('خمسة', NumberToWords::convert('00005', 'ar'));

        $this->expectException(InvalidNumberException::class);
        NumberToWords::convert(1.5, 'ar');
    }

    public function test_oversized_string_is_rejected_for_arabic_too(): void
    {
        try {
            NumberToWords::convert(str_repeat('0', 5000).'1', 'ar');
            self::fail('expected an exception');
        } catch (InvalidNumberException $e) {
            self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
        }
    }
}
