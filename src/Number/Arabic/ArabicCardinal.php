<?php

declare(strict_types=1);

namespace RtlyKit\Number\Arabic;

use RtlyKit\Number\ArabicOptions;

/**
 * Cardinal numbers in Modern Standard Arabic.
 *
 * Implements the rules of the Arabic number-words specification (R1-R15,
 * R21, R22) for digit strings of up to 27 digits. The phrase is first built as a
 * list of {@see ArabicToken}s; resolving case and annexation happens in one place
 * ({@see self::render()}), so the plain and the vowelled output cannot drift apart.
 *
 * @internal Not part of the public API; use {@see \RtlyKit\Number\NumberToWords}.
 */
final class ArabicCardinal
{
    /** Largest supported number of decimal digits (10^27 - 1). */
    public const MAX_DIGITS = 27;

    private const DAMMA = "\u{064F}";

    private const FATHA = "\u{064E}";

    private const KASRA = "\u{0650}";

    private const DAMMATAN = "\u{064C}";

    private const FATHATAN = "\u{064B}";

    private const KASRATAN = "\u{064D}";

    private const SUKUN = "\u{0652}";

    private const SHADDA = "\u{0651}";

    /** Series A (with taa marbuta): used with a masculine singular noun and for bare counting. */
    private const SERIES_A = [3 => 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة'];

    /** Series B: used with a feminine singular noun and as the multiplier of a hundred. */
    private const SERIES_B = [3 => 'ثلاث', 'أربع', 'خمس', 'ست', 'سبع', 'ثماني', 'تسع', 'عشر'];

    /** Stems of the tens 20-90 (the ون / ين ending is added). */
    private const TENS = [2 => 'عشر', 'ثلاث', 'أربع', 'خمس', 'ست', 'سبع', 'ثمان', 'تسع'];

    /**
     * Scale words by group position: [singular, plural for 3-10].
     *
     * @var array<int, array{string, string}>
     */
    private const SCALES = [
        1 => ['ألف', 'آلاف'],
        2 => ['مليون', 'ملايين'],
        3 => ['مليار', 'مليارات'],
        4 => ['تريليون', 'تريليونات'],
        5 => ['كوادريليون', 'كوادريليونات'],
        6 => ['كوينتيليون', 'كوينتيليونات'],
        7 => ['سكستيليون', 'سكستيليونات'],
        8 => ['سبتيليون', 'سبتيليونات'],
    ];

    private const BILYON = ['بليون', 'بلايين'];

    /**
     * @param string $digits unsigned digits without leading zeros ('0' for zero), at most MAX_DIGITS
     */
    public static function words(string $digits, bool $negative, ArabicOptions $options): string
    {
        $tokens = $digits === '0'
            ? [new ArabicToken(ArabicToken::STANDALONE, 'صفر')]
            : self::build($digits, $options);

        $text = self::join($tokens, $options);

        return $negative ? $options->negative.' '.$text : $text;
    }

    /**
     * @return list<ArabicToken>
     */
    private static function build(string $digits, ArabicOptions $options): array
    {
        $padded = str_pad($digits, (int) (ceil(strlen($digits) / 3) * 3), '0', STR_PAD_LEFT);
        $groups = str_split($padded, 3);
        $count = count($groups);
        $gender = $options->mode === ArabicOptions::MODE_NOUN ? $options->gender : ArabicOptions::GENDER_MASCULINE;
        $phrases = [];

        foreach ($groups as $i => $group) {
            $value = (int) $group;
            if ($value === 0) {
                continue;
            }
            $scale = $count - 1 - $i;
            $phrases[] = $scale === 0
                ? self::tail($value, $gender, ArabicToken::ANNEX_NOUN, $options)
                : self::scalePhrase($value, $scale, $options);
        }

        $tokens = [];
        foreach ($phrases as $index => $phrase) {
            foreach ($phrase as $position => $token) {
                $tokens[] = ($index > 0 && $position === 0) ? $token->withAnd() : $token;
            }
        }

        return $tokens;
    }

    /**
     * The phrase for a group of 1..999 in front of a scale word (R9, R10, R13).
     *
     * @return list<ArabicToken>
     */
    private static function scalePhrase(int $m, int $scale, ArabicOptions $options): array
    {
        [$singular, $plural] = self::scaleWords($scale, $options);
        $noun = ArabicToken::ANNEX_NOUN;

        // R13: hundreds plus 1..10 are split so that «مئة وثلاثة آلاف» cannot be read as 103 thousand.
        if ($m > 100 && $m % 100 >= 1 && $m % 100 <= 10) {
            return [
                ...self::scalePhrase($m - $m % 100, $scale, $options),
                ...self::withFirstAnd(self::scalePhrase($m % 100, $scale, $options)),
            ];
        }

        if ($m === 1) {
            return [new ArabicToken(ArabicToken::TRIPTOTE, $singular, ArabicToken::ROLE_CASE, $noun)];
        }

        if ($m === 2) {
            return [new ArabicToken(ArabicToken::DUAL, $singular, ArabicToken::ROLE_CASE, $noun)];
        }

        if ($m <= 10) {
            return [
                new ArabicToken(ArabicToken::TRIPTOTE, (self::SERIES_A[$m] ?? ''), ArabicToken::ROLE_CASE, ArabicToken::ANNEX_NEXT),
                self::pluralToken($plural, $scale, $options),
            ];
        }

        $exactHundreds = $m % 100 === 0;

        return [
            ...self::tail($m, ArabicOptions::GENDER_MASCULINE, $exactHundreds ? ArabicToken::ANNEX_NEXT : ArabicToken::ANNEX_NONE, $options),
            new ArabicToken(ArabicToken::TRIPTOTE, $singular, $exactHundreds ? ArabicToken::ROLE_GENITIVE : ArabicToken::ROLE_ACCUSATIVE, $noun),
        ];
    }

    /**
     * @param list<ArabicToken> $tokens
     *
     * @return list<ArabicToken>
     */
    private static function withFirstAnd(array $tokens): array
    {
        $tokens[0] = $tokens[0]->withAnd();

        return $tokens;
    }

    /**
     * @return array{string, string} [singular, plural]
     */
    private static function scaleWords(int $scale, ArabicOptions $options): array
    {
        if ($scale === 3 && $options->billion === ArabicOptions::BILLION_BILYON) {
            return self::BILYON;
        }

        return self::SCALES[$scale];
    }

    private static function pluralToken(string $plural, int $scale, ArabicOptions $options): ArabicToken
    {
        $diptote = $scale === 2 || ($scale === 3 && $options->billion === ArabicOptions::BILLION_BILYON);

        return new ArabicToken(
            $diptote ? ArabicToken::DIPTOTE : ArabicToken::TRIPTOTE,
            $plural,
            ArabicToken::ROLE_GENITIVE,
            ArabicToken::ANNEX_NOUN,
        );
    }

    /**
     * The phrase for 1..999 (R4-R8). Only the 1-10 unit, 11-19 and the unit of 21-99
     * follow $gender (R21).
     *
     * @param string $lastAnnex annexation of the last word of the phrase
     *
     * @return list<ArabicToken>
     */
    private static function tail(int $value, string $gender, string $lastAnnex, ArabicOptions $options): array
    {
        $h = intdiv($value, 100);
        $r = $value % 100;
        $tokens = [];

        if ($h > 0) {
            $tokens[] = self::hundred($h, $r === 0 ? $lastAnnex : ArabicToken::ANNEX_NONE, $options);
        }

        if ($r === 0) {
            return $tokens;
        }

        $and = $h > 0;

        if ($r <= 10) {
            $tokens[] = self::unit($r, $gender, $lastAnnex)->withAndIf($and);

            return $tokens;
        }

        if ($r <= 19) {
            return [...$tokens, ...self::teen($r, $gender, $lastAnnex, $and)];
        }

        $tens = intdiv($r, 10);
        $unit = $r % 10;
        if ($unit > 0) {
            $tokens[] = self::unit($unit, $gender, ArabicToken::ANNEX_NONE)->withAndIf($and);
            $and = true;
        }
        $tokens[] = (new ArabicToken(ArabicToken::TENS, self::TENS[$tens], ArabicToken::ROLE_CASE, $lastAnnex))->withAndIf($and);

        return $tokens;
    }

    private static function unit(int $n, string $gender, string $annex): ArabicToken
    {
        $feminine = $gender === ArabicOptions::GENDER_FEMININE;

        if ($n === 1) {
            return new ArabicToken(ArabicToken::STANDALONE, $feminine ? 'واحدة' : 'واحد');
        }

        if ($n === 2) {
            // R2: 1 and 2 are adjectives that follow the noun, so 2 is never in construct.
            return new ArabicToken(ArabicToken::DUAL, $feminine ? 'اثنت' : 'اثن');
        }

        // R3 polarity: a masculine noun takes series A, a feminine noun series B.
        if (! $feminine) {
            return new ArabicToken(ArabicToken::TRIPTOTE, (self::SERIES_A[$n] ?? ''), ArabicToken::ROLE_CASE, $annex);
        }

        return new ArabicToken($n === 8 ? ArabicToken::EIGHT : ArabicToken::TRIPTOTE, (self::SERIES_B[$n] ?? ''), ArabicToken::ROLE_CASE, $annex);
    }

    /**
     * @return list<ArabicToken>
     */
    private static function teen(int $r, string $gender, string $lastAnnex, bool $and): array
    {
        $feminine = $gender === ArabicOptions::GENDER_FEMININE;
        $n = $r - 10;

        $first = match (true) {
            $n === 1 => new ArabicToken(ArabicToken::FIXED, $feminine ? 'إحدى' : 'أحد'),
            // The first part of 12 is inflected like a dual and sits in construct with عشر.
            $n === 2 => new ArabicToken(ArabicToken::DUAL, $feminine ? 'اثنت' : 'اثن', ArabicToken::ROLE_CASE, ArabicToken::ANNEX_NEXT),
            default => new ArabicToken(ArabicToken::FIXED, $feminine ? (self::SERIES_B[$n] ?? '') : (self::SERIES_A[$n] ?? '')),
        };

        return [
            $first->withAndIf($and),
            new ArabicToken(ArabicToken::FIXED, $feminine ? 'عشرة' : 'عشر', ArabicToken::ROLE_CASE, $lastAnnex),
        ];
    }

    private static function hundred(int $h, string $annex, ArabicOptions $options): ArabicToken
    {
        $mia = $options->hundreds === ArabicOptions::HUNDREDS_MA_I_A ? 'مائة' : 'مئة';

        if ($h === 1) {
            return new ArabicToken(ArabicToken::TRIPTOTE, $mia, ArabicToken::ROLE_CASE, $annex);
        }

        if ($h === 2) {
            return new ArabicToken(ArabicToken::DUAL, $options->hundreds === ArabicOptions::HUNDREDS_MA_I_A ? 'مائت' : 'مئت', ArabicToken::ROLE_CASE, $annex);
        }

        // R7: the multiplier is always series B; joined 8 loses its final yaa (ثمانمئة, D3).
        $joined = $options->joinHundreds;
        $stem = ($h === 8 && $joined) ? 'ثمان' : (self::SERIES_B[$h] ?? '');

        return new ArabicToken(ArabicToken::HUNDRED, $stem, ArabicToken::ROLE_CASE, $annex, false, $mia, $joined);
    }

    /**
     * @param list<ArabicToken> $tokens
     */
    private static function join(array $tokens, ArabicOptions $options): string
    {
        $vowels = $options->diacritics === ArabicOptions::DIACRITICS_CASE;
        $noun = $options->mode === ArabicOptions::MODE_NOUN;
        $last = count($tokens) - 1;
        $text = '';

        foreach ($tokens as $i => $token) {
            $annexed = $token->annex === ArabicToken::ANNEX_NEXT
                || ($token->annex === ArabicToken::ANNEX_NOUN && $noun && $i === $last);
            $case = match ($token->role) {
                ArabicToken::ROLE_GENITIVE => ArabicOptions::CASE_GENITIVE,
                ArabicToken::ROLE_ACCUSATIVE => ArabicOptions::CASE_ACCUSATIVE,
                default => $options->case,
            };
            $word = self::render($token, $case, $annexed, $vowels);

            if ($i > 0) {
                $text .= $token->and ? ' و' : ' ';
            }
            $text .= $word;
        }

        return $text;
    }

    /**
     * Resolve case and annexation for one token (R11, R12, section 8 of the spec).
     */
    private static function render(ArabicToken $token, string $case, bool $annexed, bool $vowels): string
    {
        $nominative = $case === ArabicOptions::CASE_NOMINATIVE;
        $stem = $token->stem;

        switch ($token->kind) {
            case ArabicToken::DUAL:
                if ($annexed) {
                    return $nominative ? $stem.'ا' : ($vowels ? $stem.self::FATHA.'ي'.self::SUKUN : $stem.'ي');
                }
                if (! $vowels) {
                    return $stem.($nominative ? 'ان' : 'ين');
                }

                return $nominative
                    ? $stem.'ان'.self::KASRA
                    : $stem.self::FATHA.'ي'.self::SUKUN.'ن'.self::KASRA;

            case ArabicToken::TENS:
                $word = $stem.($nominative ? 'ون' : 'ين');

                return $vowels ? $word.self::FATHA : $word;

            case ArabicToken::FIXED:
                return ($vowels && ! str_ends_with($stem, 'ى')) ? self::attach($stem, self::FATHA) : $stem;

            case ArabicToken::HUNDRED:
                return self::renderHundred($token, $case, $annexed, $vowels);

            case ArabicToken::EIGHT:
                return $vowels ? self::renderEight($stem, $case, $annexed) : $stem;

            case ArabicToken::DIPTOTE:
                return $vowels ? $stem.($annexed ? self::KASRA : self::FATHA) : $stem;

            default:
                if (! $vowels) {
                    return $stem;
                }

                return self::renderTriptote($stem, $case, $annexed && $token->kind !== ArabicToken::STANDALONE);
        }
    }

    private static function renderTriptote(string $stem, string $case, bool $annexed): string
    {
        if ($annexed) {
            return self::attach($stem, match ($case) {
                ArabicOptions::CASE_NOMINATIVE => self::DAMMA,
                ArabicOptions::CASE_ACCUSATIVE => self::FATHA,
                default => self::KASRA,
            });
        }

        return match ($case) {
            ArabicOptions::CASE_NOMINATIVE => self::attach($stem, self::DAMMATAN),
            ArabicOptions::CASE_ACCUSATIVE => str_ends_with($stem, 'ة') ? $stem.self::FATHATAN : self::attach($stem, self::FATHATAN, 'ا'),
            default => self::attach($stem, self::KASRATAN),
        };
    }

    private static function renderEight(string $stem, string $case, bool $annexed): string
    {
        if ($case === ArabicOptions::CASE_ACCUSATIVE) {
            return $stem.self::FATHA;
        }

        // Defective noun: ثماني with a following word, ثمانٍ without.
        return $annexed ? $stem : substr($stem, 0, -2).self::KASRATAN;
    }

    private static function renderHundred(ArabicToken $token, string $case, bool $annexed, bool $vowels): string
    {
        $stem = $token->stem;
        $space = $token->joined ? '' : ' ';

        if (! $vowels) {
            return $stem.$space.$token->aux;
        }

        // The multiplier takes the case of the phrase; مئة is the genitive annexed to it.
        $multiplier = match (true) {
            $stem === 'ثماني' => self::renderEight($stem, $case, true),
            $stem === 'ثمان' => $stem.self::KASRA,
            default => self::attach($stem, match ($case) {
                ArabicOptions::CASE_NOMINATIVE => self::DAMMA,
                ArabicOptions::CASE_ACCUSATIVE => self::FATHA,
                default => self::KASRA,
            }),
        };

        return $multiplier.$space.$token->aux.($annexed ? self::KASRA : self::KASRATAN);
    }

    /**
     * Append a vowel to a stem, keeping the canonical mark order for the geminated «ست».
     */
    private static function attach(string $stem, string $mark, string $tail = ''): string
    {
        return $stem.$mark.(str_ends_with($stem, 'ست') ? self::SHADDA : '').$tail;
    }
}
