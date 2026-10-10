<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Text\Detector;
use RtlyKit\Text\Normalizer;
use RtlyKit\Text\Slugify;

final class TextHardeningTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function nastyStrings(): array
    {
        return [
            'zwsp between spaces' => ["a \u{200B} b"],
            'leading zwsp zwnj' => ["\u{200B}\u{200C}سلام"],
            'zwnj around space' => ["می \u{200C} روم"],
            'zwnj zwsp zwnj' => ["می\u{200C}\u{200B}\u{200C}روم"],
            'trailing zwnj zwsp' => ["سلام\u{200C}\u{200B}"],
            'bom zwj' => ["\u{FEFF}a\u{200D} b"],
            'soft hyphen' => ["a \u{00AD} b"],
            'soft hyphen zwnj' => ["می\u{200C}\u{00AD}\u{200C}روم"],
            'tatweel zwnj' => ["می\u{200C}ـ\u{200C}روم"],
            'harakat only' => ["\u{200C}\u{064E}\u{200C}"],
            'tabs newlines' => ["\t a \n\n b \r\n"],
            'arabic letters' => ['كتاب يك ة'],
            'only zero width' => ["\u{200B}\u{200C}\u{200D}\u{FEFF}"],
            'nbsp' => ["a\u{00A0}\u{200C}\u{00A0}b"],
            'empty' => [''],
            'ascii' => ['  plain   text  '],
            'invalid utf8' => ["a \xff \u{200B} b"],
        ];
    }

    #[DataProvider('nastyStrings')]
    public function test_clean_is_idempotent(string $input): void
    {
        $once = Normalizer::clean($input);

        self::assertSame($once, Normalizer::clean($once), bin2hex($input));
    }

    public function test_clean_known_answers(): void
    {
        self::assertSame('a b', Normalizer::clean("a \u{200B} b"));
        self::assertSame('سلام', Normalizer::clean("\u{200B}\u{200C}سلام"));
        self::assertSame('سلام', Normalizer::clean("سلام\u{200C}\u{200B}"));
        self::assertSame("می\u{200C}روم", Normalizer::clean("می\u{200C}\u{200B}\u{200C}روم"));
        self::assertSame('a b', Normalizer::clean("a \u{00AD} b"));
        self::assertSame('', Normalizer::clean("\u{200B}\u{200C}\u{200D}\u{FEFF}"));
    }

    public function test_slugify_does_not_eat_text_equal_to_the_separator(): void
    {
        self::assertSame('abcabdef', Slugify::make('abc def', 'ab'));
        self::assertSame('maxx', Slugify::make('maxx', 'x'));
        self::assertSame('xmax', Slugify::make('xmax', 'x'));
        self::assertSame('axb', Slugify::make('a b', 'x'));
    }

    public function test_slugify_control_character_is_not_a_separator(): void
    {
        self::assertSame('ab', Slugify::make("a\x01b"));
        self::assertSame('a-b', Slugify::make("a\x01 b"));
        self::assertSame('ab', Slugify::make("\x01a\x01b\x01"));
    }

    public function test_slugify_default_behaviour_is_unchanged(): void
    {
        self::assertSame('hello-world', Slugify::make('  Hello,   World!  '));
        self::assertSame('a-b', Slugify::make('--a--b--'));
        self::assertSame('a_b', Slugify::make('__a__b__', '_'));
        self::assertSame('کتاب-خوب', Slugify::make("کتاب\u{200C}خوب"));
        self::assertSame('a_b_c', Slugify::make('a b c', '_'));
        self::assertSame('abc', Slugify::make('a b c', ''));
        self::assertSame('a--b', Slugify::make('a b', '--'));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function localeScripts(): array
    {
        return [
            'fa-Latn' => ['fa-Latn', false],
            'ar-Latn' => ['ar-Latn', false],
            'ur-Latn' => ['ur-Latn', false],
            'he-Latn' => ['he-Latn', false],
            'ug-Latn' => ['ug-Latn', false],
            'sd-Deva' => ['sd-Deva', false],
            'fa_Latn_IR' => ['fa_Latn_IR', false],
            'ar-latn-eg' => ['ar-latn-eg', false],
            'ku-Arab' => ['ku-Arab', true],
            'pa-Arab-PK' => ['pa-Arab-PK', true],
            'en-Arab' => ['en-Arab', true],
            'az-Hebr' => ['az-Hebr', true],
            'en-Phnx' => ['en-Phnx', true],
            'fa' => ['fa', true],
            'fa-IR' => ['fa-IR', true],
            'ar_SA' => ['ar_SA', true],
            'ar-u-nu-latn' => ['ar-u-nu-latn', true],
            'ar@euro' => ['ar@euro', true],
            'fa_IR.UTF-8' => ['fa_IR.UTF-8', true],
            'en' => ['en', false],
            'en-US' => ['en-US', false],
            'ku-Latn' => ['ku-Latn', false],
            'sd' => ['sd', true],
        ];
    }

    #[DataProvider('localeScripts')]
    public function test_explicit_script_subtag_wins(string $locale, bool $rtl): void
    {
        self::assertSame($rtl, Detector::isRtlLocale($locale), $locale);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function historicRtlLetters(): array
    {
        return [
            'Phoenician' => ["\u{10900}"],
            'Lydian' => ["\u{10920}"],
            'Imperial Aramaic' => ["\u{10840}"],
            'Avestan' => ["\u{10B00}"],
            'Old South Arabian' => ["\u{10A60}"],
            'Old North Arabian' => ["\u{10A80}"],
            'Nabataean' => ["\u{10880}"],
            'Palmyrene' => ["\u{10860}"],
            'Hatran' => ["\u{108E0}"],
            'Psalter Pahlavi' => ["\u{10B80}"],
            'Inscriptional Pahlavi' => ["\u{10B60}"],
            'Inscriptional Parthian' => ["\u{10B40}"],
            'Old Turkic' => ["\u{10C00}"],
            'Old Hungarian' => ["\u{10C80}"],
            'Hanifi Rohingya' => ["\u{10D00}"],
            'Yezidi' => ["\u{10E80}"],
            'Chorasmian' => ["\u{10FB0}"],
            'Elymaic' => ["\u{10FE0}"],
            'Mende Kikakui' => ["\u{1E800}"],
            'Cypriot' => ["\u{10800}"],
            'Adlam' => ["\u{1E900}"],
            'Hebrew' => ["\u{05D0}"],
            'Arabic' => ["\u{0628}"],
            'Thaana' => ["\u{0780}"],
        ];
    }

    #[DataProvider('historicRtlLetters')]
    public function test_direction_agrees_with_contains_rtl_for_every_rtl_script(string $letter): void
    {
        self::assertTrue(Detector::containsRtl($letter), bin2hex($letter));
        self::assertSame('rtl', Detector::direction($letter), bin2hex($letter));
        self::assertSame('rtl', Detector::direction('123 '.$letter.' abc'), bin2hex($letter));
    }

    public function test_direction_stays_ltr_for_latin_and_neutral_text(): void
    {
        self::assertSame('ltr', Detector::direction('abc'));
        self::assertSame('ltr', Detector::direction('123 !?'));
        self::assertSame('ltr', Detector::direction("abc \u{05D0}"));
        self::assertSame('rtl', Detector::direction("\u{05D0} abc"));
    }
}
