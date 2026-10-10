<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Number\Digits;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;
use RtlyKit\Text\Detector;
use RtlyKit\Text\Normalizer;
use RtlyKit\Text\Slugify;
use RtlyKit\Validation\Input;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;

/**
 * Text and number functions must not depend on the process locale (Turkish dotless i, a decimal comma).
 */
final class LocaleIndependenceTest extends TestCase
{
    private string|false $saved = false;

    protected function setUp(): void
    {
        $this->saved = setlocale(LC_ALL, '0');
    }

    protected function tearDown(): void
    {
        if (is_string($this->saved)) {
            setlocale(LC_ALL, $this->saved);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return [
            'turkish' => ['tr_TR.UTF-8'],
            'german (decimal comma)' => ['de_DE.UTF-8'],
            'C' => ['C'],
        ];
    }

    #[DataProvider('locales')]
    public function test_results_do_not_change_with_the_locale(string $locale): void
    {
        if (setlocale(LC_ALL, $locale) === false) {
            self::markTestSkipped("The locale $locale is not installed.");
        }

        // Case mapping must stay ASCII/Unicode-simple, never the Turkish i.
        self::assertSame('istanbul-i', Slugify::make('ISTANBUL I'));
        self::assertSame('ir', Slugify::make('IR'));
        self::assertTrue(Sheba::isValid('ir062960000000100324200001'));
        self::assertSame('IR062960000000100324200001', Sheba::normalize('ir062960000000100324200001'));
        self::assertSame('12D34567', VehiclePlate::normalize('12d34567'));
        self::assertTrue(Detector::isRtlLocale('FA'));
        self::assertTrue(Detector::isRtlLocale('Ar-Sa'));
        self::assertSame('۱۲۳', Digits::convert('123', 'PERSIAN'));
        self::assertSame('یک میلیون', NumberToWords::convert(1000000, 'FA'));
        self::assertSame('واحد', NumberToWords::convert(1, 'AR'));

        // Floats are written with a dot and no thousands grouping from the locale.
        self::assertSame('۱٬۲۳۴٬۵۶۷٫۸۹۱', Format::withSeparator(1234567.891));
        self::assertSame('۰٫۳', Format::withSeparator(0.1 + 0.2));
        self::assertSame('۱٬۰۰۰٬۰۰۰', Format::withSeparator(1.0E+6));
        self::assertSame('100000000000000', Input::coerce(1.0E+14));
        self::assertSame('یک میلیارد', NumberToWords::convert(1.0E+9));

        // Text steps.
        self::assertSame('علی ۱۲۳', Normalizer::clean('علي  ١٢٣'));
    }
}
