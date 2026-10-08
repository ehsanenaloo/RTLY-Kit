<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit;

use ErrorException;
use PHPUnit\Framework\TestCase;
use RtlyKit\Contracts\Validator;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Exceptions\RtlyKitThrowable;
use RtlyKit\Number\ArabicOptions;
use RtlyKit\Number\Digits;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;
use RtlyKit\Text\Detector;
use RtlyKit\Text\Normalizer;
use RtlyKit\Text\Slugify;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\Mobile;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\PostalCode;
use RtlyKit\Validation\Result;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;
use Stringable;
use Throwable;

use function RtlyKit\contains_rtl;
use function RtlyKit\format_number;
use function RtlyKit\is_bank_card;
use function RtlyKit\is_mobile;
use function RtlyKit\is_national_code;
use function RtlyKit\is_postal_code;
use function RtlyKit\is_sheba;
use function RtlyKit\is_vehicle_plate;
use function RtlyKit\normalize_text;
use function RtlyKit\number_to_words;
use function RtlyKit\ordinal;
use function RtlyKit\text_direction;
use function RtlyKit\to_english;
use function RtlyKit\to_english_digits;
use function RtlyKit\to_persian;
use function RtlyKit\to_persian_digits;
use function RtlyKit\validate_bank_card;
use function RtlyKit\validate_mobile;
use function RtlyKit\validate_national_code;
use function RtlyKit\validate_postal_code;
use function RtlyKit\validate_sheba;
use function RtlyKit\validate_vehicle_plate;

/**
 * Property-style fuzz test: no public entry point of the validation, number and
 * text layers may leak a raw TypeError/ValueError/DivisionByZeroError, a PHP
 * warning, notice or deprecation. The only accepted outcomes are a normal
 * return or an {@see RtlyKitThrowable}.
 *
 * Parameters declared `mixed` (the validators) receive every kind of value.
 * Parameters with a native scalar type receive the awkward values that type
 * can legally hold (NUL bytes, invalid UTF-8, huge strings, NaN, INF, int
 * limits): passing a value of a different native type is a caller error that
 * PHP itself reports as a TypeError, in every library.
 */
final class PublicApiFuzzTest extends TestCase
{
    /** @var list<class-string<Validator>> */
    private const VALIDATORS = [
        NationalCode::class,
        Sheba::class,
        BankCard::class,
        Mobile::class,
        PostalCode::class,
        VehiclePlate::class,
    ];

    /**
     * @return list<string>
     */
    private static function strings(): array
    {
        return [
            '',
            ' ',
            "\0",
            "12\0345",
            "IR\0" . str_repeat('0', 24),
            "\xff",
            "\xff\xfe\xfd",
            "\xC0\xAF",
            "\xED\xA0\x80",
            "abc\xE0\x80",
            "\u{202E}\u{200F}\u{200C}\u{200D}\u{FEFF}",
            '۱۲۳٤٥٦',
            '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹',
            '0499370899',
            '09123456789',
            'IR062960000000100324200001',
            '6037991899071116',
            '12ب345-67',
            '-',
            '+',
            '-0',
            '1e5',
            '0x1F',
            '١٢٣٫٤٥',
            '1,234,567.89',
            '۱٬۲۳۴٫۵',
            '(((',
            '\\',
            '/',
            'ك ي ى ة ؤ إ أ',
            'سلام دنیا',
            'שלום',
            'منفی',
            'منفی منفی',
            'صد و و و',
            'هزار هزار',
            'یک میلیارد میلیون',
            str_repeat('9', 4096),
            str_repeat('9', 4097),
            str_repeat('۹', 2049),
            str_repeat('a', 5000),
            str_repeat('9', 100_000),
            str_repeat("\u{200C}", 5000),
            str_repeat('سلام ', 3000),
            "\xff" . str_repeat('9', 100),
            str_repeat("\xff", 5000),
        ];
    }

    /**
     * @return list<int|float>
     */
    private static function numbers(): array
    {
        return [
            0, 1, -1, 3, 21, 1000, 123456789, 999_999_999, 1_000_000_000, PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MAX - 1,
            0.0, -0.0, 1.0, -1.0, 1.5, -2.5, 0.1, 1e15, 1e21, 1e22, 1e100, 1e308, -1e308,
            PHP_FLOAT_MAX, PHP_FLOAT_MIN, PHP_FLOAT_EPSILON, NAN, INF, -INF, (float) PHP_INT_MAX, (float) PHP_INT_MIN,
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function mixed(): array
    {
        $object = new class () {
            public string $x = 'y';
        };
        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return '0499370899';
            }
        };
        $throwing = new class () implements Stringable {
            public function __toString(): string
            {
                throw new \RuntimeException('boom');
            }
        };
        $resource = fopen('php://memory', 'r');

        return [
            ...self::strings(),
            ...self::numbers(),
            null,
            true,
            false,
            [],
            [''],
            ['0499370899'],
            [[]],
            [1 => [2 => [3 => 'x']]],
            new \stdClass(),
            $object,
            $stringable,
            $throwing,
            static fn (): string => '0499370899',
            $resource,
            Result::valid(),
            new \ArrayObject(['0499370899']),
            new \DateTimeImmutable('2024-03-20'),
        ];
    }

    /* ---------------- validators (mixed input) ---------------- */

    public function test_every_validator_returns_a_result_for_any_value_and_never_throws(): void
    {
        foreach (self::VALIDATORS as $class) {
            self::assertTrue(is_subclass_of($class, Validator::class), $class . ' must implement the Validator contract');

            foreach (self::mixed() as $i => $value) {
                $label = $class . ' value #' . $i . ' (' . get_debug_type($value) . ')';

                $this->guard($label, function () use ($class, $value, $label): void {
                    $result = $class::validate($value);
                    self::assertInstanceOf(Result::class, $result, $label);
                    self::assertSame($result->isValid(), $class::isValid($value), $label);

                    if (! $result->isValid()) {
                        self::assertNotSame([], $result->errors(), $label);
                    }
                }, allowLibraryException: false);
            }
        }
    }

    public function test_validator_extras_accept_any_value(): void
    {
        foreach (self::mixed() as $i => $value) {
            $label = 'value #' . $i . ' (' . get_debug_type($value) . ')';

            $this->guard('BankCard::getBankName ' . $label, fn () => BankCard::getBankName($value), allowLibraryException: false);
            $this->guard('Sheba::getBankName ' . $label, fn () => Sheba::getBankName($value), allowLibraryException: false);
            $this->guard('Mobile::getOperator ' . $label, fn () => Mobile::getOperator($value), allowLibraryException: false);
            $this->guard('NationalCode::getLocation ' . $label, fn () => NationalCode::getLocation($value), allowLibraryException: false);
        }
    }

    public function test_validator_string_helpers_survive_awkward_strings(): void
    {
        foreach (self::strings() as $i => $s) {
            $label = 'string #' . $i;

            foreach (self::VALIDATORS as $class) {
                $this->guard($class . '::normalize ' . $label, fn () => $class::normalize($s));
            }

            $this->guard('VehiclePlate::parse ' . $label, fn () => VehiclePlate::parse($s));
        }
    }

    public function test_namespaced_validation_helpers_accept_any_value(): void
    {
        $fns = [
            is_national_code(...), is_sheba(...), is_bank_card(...), is_mobile(...), is_postal_code(...), is_vehicle_plate(...),
            validate_national_code(...), validate_sheba(...), validate_bank_card(...), validate_mobile(...),
            validate_postal_code(...), validate_vehicle_plate(...),
        ];

        foreach ($fns as $n => $fn) {
            foreach (self::mixed() as $i => $value) {
                $this->guard('helper #' . $n . ' value #' . $i, static fn () => $fn($value), allowLibraryException: false);
            }
        }
    }

    /* ---------------- numbers ---------------- */

    public function test_digit_functions_survive_awkward_input(): void
    {
        foreach (self::strings() as $i => $s) {
            $label = 'string #' . $i;

            $this->guard('Digits::toPersian ' . $label, fn () => Digits::toPersian($s));
            $this->guard('Digits::toArabic ' . $label, fn () => Digits::toArabic($s));
            $this->guard('Digits::toEnglish ' . $label, fn () => Digits::toEnglish($s));
            $this->guard('Digits::arabicToPersian ' . $label, fn () => Digits::arabicToPersian($s));
            $this->guard('Digits::convert ' . $label, fn () => Digits::convert($s));
            $this->guard('Digits::convert(to) ' . $label, fn () => Digits::convert('123', $s));
            $this->guard('to_persian ' . $label, fn () => to_persian($s));
            $this->guard('to_english ' . $label, fn () => to_english($s));
            $this->guard('to_english_digits ' . $label, fn () => to_english_digits($s));
        }

        foreach (self::numbers() as $i => $n) {
            $label = 'number #' . $i;

            $this->guard('Digits::toPersian ' . $label, fn () => Digits::toPersian($n));
            $this->guard('Digits::toArabic ' . $label, fn () => Digits::toArabic($n));
            $this->guard('to_persian_digits ' . $label, fn () => to_persian_digits($n));
        }
    }

    public function test_format_survives_awkward_input(): void
    {
        foreach (self::strings() as $i => $s) {
            $label = 'string #' . $i;

            $this->guard('Format::withSeparator ' . $label, fn () => Format::withSeparator($s));
            $this->guard('Format::withSeparator(seps) ' . $label, fn () => Format::withSeparator('1234567.5', $s, $s));
            $this->guard('format_number ' . $label, fn () => format_number($s));
        }

        foreach (self::numbers() as $i => $n) {
            $label = 'number #' . $i;

            $this->guard('Format::withSeparator ' . $label, fn () => Format::withSeparator($n));
            $this->guard('Format::ordinal ' . $label, fn () => Format::ordinal($n));
            $this->guard('format_number ' . $label, fn () => format_number($n));
            $this->guard('ordinal ' . $label, fn () => ordinal($n));
        }
    }

    public function test_number_to_words_survives_awkward_input(): void
    {
        $locales = ['fa', 'ar', 'FA', 'xx', '', "\0", "\xff", 'fa_IR', str_repeat('f', 5000)];

        foreach (self::strings() as $i => $s) {
            $label = 'string #' . $i;

            foreach (['fa', 'ar'] as $locale) {
                $this->guard('NumberToWords::convert ' . $locale . ' ' . $label, fn () => NumberToWords::convert($s, $locale));
            }

            $this->guard('NumberToWords::fromWords ' . $label, fn () => NumberToWords::fromWords($s));
            $this->guard('number_to_words ' . $label, fn () => number_to_words($s));
        }

        foreach (self::numbers() as $i => $n) {
            foreach ($locales as $locale) {
                $this->guard('NumberToWords::convert number #' . $i . ' locale ' . strlen($locale), fn () => NumberToWords::convert($n, $locale));
            }
        }

        foreach ($locales as $j => $locale) {
            $this->guard('NumberToWords::convert locale #' . $j, fn () => NumberToWords::convert(5, $locale));
        }
    }

    public function test_arabic_number_words_options_and_ordinals_survive_awkward_input(): void
    {
        $optionSets = [
            null,
            [],
            new ArabicOptions(),
            new ArabicOptions(mode: 'noun', gender: 'f', case: 'gen', diacritics: 'case', hundreds: 'ma_i_a', joinHundreds: false, billion: 'bilyon'),
            ['mode' => 'noun', 'gender' => 'f', 'case' => 'acc', 'diacritics' => 'case'],
            ['bogus' => 1],
            [0 => 'x'],
            ['mode' => 5],
            ['mode' => ['noun']],
            ['mode' => "\0"],
            ['gender' => null],
            ['joinHundreds' => 'yes'],
            ['negative' => "\xff"],
            ['negative' => str_repeat('x', 5000)],
            [str_repeat('k', 5000) => 1],
        ];
        $locales = ['ar', 'AR', 'ar_SA', 'fa', 'xx', '', "\xff", str_repeat('a', 5000)];

        foreach ($optionSets as $k => $options) {
            foreach (self::strings() as $i => $s) {
                $this->guard('convert ar options #' . $k . ' string #' . $i, fn () => NumberToWords::convert($s, 'ar', $options));
                $this->guard('ordinal options #' . $k . ' string #' . $i, fn () => NumberToWords::ordinal($s, 'ar', $options));
            }

            foreach (self::numbers() as $i => $n) {
                $this->guard('convert ar options #' . $k . ' number #' . $i, fn () => NumberToWords::convert($n, 'ar', $options));

                if (is_int($n)) {
                    $this->guard('ordinal options #' . $k . ' number #' . $i, fn () => NumberToWords::ordinal($n, 'ar', $options));
                }
            }

            foreach ($locales as $j => $locale) {
                $this->guard('convert options #' . $k . ' locale #' . $j, fn () => NumberToWords::convert(5, $locale, $options));
                $this->guard('ordinal options #' . $k . ' locale #' . $j, fn () => NumberToWords::ordinal(5, $locale, $options));
            }
        }

        for ($n = 0; $n <= 120; $n++) {
            $this->guard('ordinal ' . $n, fn () => NumberToWords::ordinal($n));
        }
    }

    /* ---------------- text ---------------- */

    public function test_text_functions_survive_awkward_input(): void
    {
        foreach (self::strings() as $i => $s) {
            $label = 'string #' . $i;

            $this->guard('Normalizer::normalize ' . $label, fn () => Normalizer::normalize($s));
            $this->guard('Normalizer::normalize(keep) ' . $label, fn () => Normalizer::normalize($s, false));
            $this->guard('Normalizer::fixHalfSpace ' . $label, fn () => Normalizer::fixHalfSpace($s));
            $this->guard('Normalizer::clean ' . $label, fn () => Normalizer::clean($s));
            $this->guard('Detector::isPersian ' . $label, fn () => Detector::isPersian($s));
            $this->guard('Detector::isArabic ' . $label, fn () => Detector::isArabic($s));
            $this->guard('Detector::isHebrew ' . $label, fn () => Detector::isHebrew($s));
            $this->guard('Detector::containsRtl ' . $label, fn () => Detector::containsRtl($s));
            $this->guard('Detector::direction ' . $label, fn () => Detector::direction($s));
            $this->guard('Detector::isRtlLocale ' . $label, fn () => Detector::isRtlLocale($s));
            $this->guard('Slugify::make ' . $label, fn () => Slugify::make($s));
            $this->guard('Slugify::make(sep) ' . $label, fn () => Slugify::make('سلام دنیا  hello', $s));
            $this->guard('Slugify::make(both) ' . $label, fn () => Slugify::make($s, $s));
            $this->guard('normalize_text ' . $label, fn () => normalize_text($s));
            $this->guard('contains_rtl ' . $label, fn () => contains_rtl($s));
            $this->guard('text_direction ' . $label, fn () => text_direction($s));
        }
    }

    public function test_text_functions_keep_their_documented_return_shapes(): void
    {
        foreach (self::strings() as $s) {
            self::assertContains(Detector::direction($s), ['rtl', 'ltr']);
            self::assertIsString(Normalizer::clean($s));
            if (preg_match('//u', $s) === 1) {
                self::assertIsString(Slugify::make($s));
            } else {
                $this->expectInvalidSlugText($s);
            }
        }
    }

    private function expectInvalidSlugText(string $s): void
    {
        try {
            Slugify::make($s);
            self::fail('invalid UTF-8 must be rejected');
        } catch (RtlyKitException $e) {
            self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
        }
    }

    /**
     * Run $fn; fail on any warning/notice/deprecation or on any throwable that
     * is not a library exception.
     *
     * @param callable(): mixed $fn
     */
    private function guard(string $label, callable $fn, bool $allowLibraryException = true): void
    {
        set_error_handler(static function (int $no, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $no, $file, $line);
        });

        try {
            $fn();
            $this->addToAssertionCount(1);
        } catch (RtlyKitThrowable $e) {
            if (! $allowLibraryException) {
                self::fail(sprintf('%s: threw %s (%s) but must return normally', $label, $e::class, $e->getMessage()));
            }
        } catch (Throwable $e) {
            self::fail(sprintf('%s: leaked %s: %s', $label, $e::class, $e->getMessage()));
        } finally {
            restore_error_handler();
        }
    }
}
