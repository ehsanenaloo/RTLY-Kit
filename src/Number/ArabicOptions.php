<?php

declare(strict_types=1);

namespace RtlyKit\Number;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Text\Utf8;

/**
 * Immutable options for Arabic number words ({@see NumberToWords::convert()} and
 * {@see NumberToWords::ordinal()} with locale `ar`).
 *
 * Every option has a default that reproduces the plain "bare counting" output, so
 * `new ArabicOptions()` equals passing no options at all. Invalid values are
 * rejected at construction with an {@see InvalidNumberException}.
 *
 * | option          | values                        | default |
 * |-----------------|-------------------------------|---------|
 * | `mode`          | `count`, `noun`               | `count` |
 * | `gender`        | `m`, `f`                      | `m`     |
 * | `case`          | `nom`, `acc`, `gen`           | `nom`   |
 * | `diacritics`    | `none`, `case`                | `none`  |
 * | `hundreds`      | `mi_a`, `ma_i_a`              | `mi_a`  |
 * | `joinHundreds`  | bool                          | `true`  |
 * | `negative`      | non-empty string, max 64 bytes| `سالب`  |
 * | `billion`       | `milyar`, `bilyon`            | `milyar`|
 * | `definite`      | bool (ordinals only)          | `true`  |
 *
 * `gender` is the grammatical gender of the SINGULAR of the counted noun and is
 * ignored while `mode` is `count`.
 */
final readonly class ArabicOptions
{
    public const MODE_COUNT = 'count';

    public const MODE_NOUN = 'noun';

    public const GENDER_MASCULINE = 'm';

    public const GENDER_FEMININE = 'f';

    public const CASE_NOMINATIVE = 'nom';

    public const CASE_ACCUSATIVE = 'acc';

    public const CASE_GENITIVE = 'gen';

    public const DIACRITICS_NONE = 'none';

    public const DIACRITICS_CASE = 'case';

    public const HUNDREDS_MI_A = 'mi_a';

    public const HUNDREDS_MA_I_A = 'ma_i_a';

    public const BILLION_MILYAR = 'milyar';

    public const BILLION_BILYON = 'bilyon';

    /** Longest accepted negative marker, in bytes. */
    private const MAX_NEGATIVE_BYTES = 64;

    private const KEYS = ['mode', 'gender', 'case', 'diacritics', 'hundreds', 'joinHundreds', 'negative', 'billion', 'definite'];

    /**
     * @param string $mode         `count` (bare number) or `noun` (a counted noun follows directly)
     * @param string $gender       `m` or `f`: gender of the singular counted noun (ignored in `count` mode)
     * @param string $case         `nom`, `acc` or `gen`: case of the whole number phrase
     * @param string $diacritics   `none` (plain letters) or `case` (case endings and tanwin)
     * @param string $hundreds     `mi_a` (مئة) or `ma_i_a` (مائة)
     * @param bool   $joinHundreds `true`: ثلاثمئة; `false`: ثلاث مئة
     * @param string $negative     word placed before negative numbers
     * @param string $billion      `milyar` (مليار) or `bilyon` (بليون) for 10^9
     * @param bool   $definite     ordinals only: with the article (الأول) or without (أول)
     *
     * @throws InvalidNumberException on an unknown value
     */
    public function __construct(
        public string $mode = self::MODE_COUNT,
        public string $gender = self::GENDER_MASCULINE,
        public string $case = self::CASE_NOMINATIVE,
        public string $diacritics = self::DIACRITICS_NONE,
        public string $hundreds = self::HUNDREDS_MI_A,
        public bool $joinHundreds = true,
        public string $negative = 'سالب',
        public string $billion = self::BILLION_MILYAR,
        public bool $definite = true,
    ) {
        self::oneOf('mode', $mode, [self::MODE_COUNT, self::MODE_NOUN]);
        self::oneOf('gender', $gender, [self::GENDER_MASCULINE, self::GENDER_FEMININE]);
        self::oneOf('case', $case, [self::CASE_NOMINATIVE, self::CASE_ACCUSATIVE, self::CASE_GENITIVE]);
        self::oneOf('diacritics', $diacritics, [self::DIACRITICS_NONE, self::DIACRITICS_CASE]);
        self::oneOf('hundreds', $hundreds, [self::HUNDREDS_MI_A, self::HUNDREDS_MA_I_A]);
        self::oneOf('billion', $billion, [self::BILLION_MILYAR, self::BILLION_BILYON]);

        if ($negative === ''
            || strlen($negative) > self::MAX_NEGATIVE_BYTES
            || ! Utf8::isValid($negative)
            || preg_match('/[\p{Cc}\p{Cf}\p{Z}]/u', $negative) === 1
        ) {
            throw new InvalidNumberException(
                'Invalid option "negative": expected a non-empty word of at most 64 bytes without spaces or control characters.',
                errorCode: ErrorCode::InvalidArgument,
                context: ['option' => 'negative'],
            );
        }
    }

    /**
     * Build from an associative array (keys are the option names above).
     *
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidNumberException on an unknown key or a value of the wrong type
     */
    public static function fromArray(array $options): self
    {
        foreach ($options as $key => $_) {
            if (! in_array($key, self::KEYS, true)) {
                $shown = Utf8::truncate((string) $key, 40);

                throw new InvalidNumberException(
                    sprintf('Unknown Arabic number option "%s" (allowed: %s).', $shown, implode(', ', self::KEYS)),
                    errorCode: ErrorCode::InvalidArgument,
                    context: ['option' => $shown],
                );
            }
        }

        $string = static function (string $key, string $default) use ($options): string {
            $value = $options[$key] ?? $default;
            if (! is_string($value)) {
                throw self::wrongType($key, 'string');
            }

            return $value;
        };
        $bool = static function (string $key, bool $default) use ($options): bool {
            $value = $options[$key] ?? $default;
            if (! is_bool($value)) {
                throw self::wrongType($key, 'bool');
            }

            return $value;
        };

        return new self(
            mode: $string('mode', self::MODE_COUNT),
            gender: $string('gender', self::GENDER_MASCULINE),
            case: $string('case', self::CASE_NOMINATIVE),
            diacritics: $string('diacritics', self::DIACRITICS_NONE),
            hundreds: $string('hundreds', self::HUNDREDS_MI_A),
            joinHundreds: $bool('joinHundreds', true),
            negative: $string('negative', 'سالب'),
            billion: $string('billion', self::BILLION_MILYAR),
            definite: $bool('definite', true),
        );
    }

    /**
     * Accept an instance, an array, or null (all defaults).
     *
     * @param self|array<array-key, mixed>|null $options
     */
    public static function resolve(self|array|null $options): self
    {
        if ($options instanceof self) {
            return $options;
        }

        return $options === null ? new self() : self::fromArray($options);
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOf(string $name, string $value, array $allowed): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new InvalidNumberException(
                sprintf('Invalid option "%s": expected one of %s.', $name, implode(', ', $allowed)),
                errorCode: ErrorCode::InvalidArgument,
                context: ['option' => $name],
            );
        }
    }

    private static function wrongType(string $name, string $expected): InvalidNumberException
    {
        return new InvalidNumberException(
            sprintf('Invalid option "%s": expected %s.', $name, $expected),
            errorCode: ErrorCode::InvalidArgument,
            context: ['option' => $name],
        );
    }
}
