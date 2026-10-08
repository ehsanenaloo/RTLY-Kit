<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Number\NumberToWords;

final class FromWordsStrictTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function nonCanonical(): array
    {
        return [
            'units before hundreds' => ['دو صد'],
            'trailing and' => ['بیست و'],
            'leading and' => ['و بیست'],
            'double and' => ['بیست و و یک'],
            'missing and between parts' => ['بیست یک'],
            'missing and between groups' => ['یک هزار دویست'],
            'teen then unit' => ['یازده و یک'],
            'ten then unit' => ['ده و یک'],
            'unit then unit' => ['یک و دو'],
            'unit before tens' => ['پنج و بیست'],
            'tens before hundreds' => ['بیست و صد'],
            'and after sign' => ['منفی و پنج'],
            'and before scale' => ['یک و هزار'],
            'bare scale after and' => ['یک میلیون و هزار'],
            'zero combined' => ['صفر و یک'],
            'zero then number' => ['صفر یک'],
            'only and' => ['و'],
        ];
    }

    #[DataProvider('nonCanonical')]
    public function test_non_canonical_forms_are_rejected(string $words): void
    {
        try {
            NumberToWords::fromWords($words);
            self::fail("expected rejection of '{$words}'");
        } catch (InvalidNumberException $e) {
            self::assertSame(ErrorCode::InvalidNumberWords, $e->getErrorCode());
        }
    }

    public function test_canonical_and_common_forms_are_accepted(): void
    {
        self::assertSame(1000, NumberToWords::fromWords('هزار'));
        self::assertSame(1001, NumberToWords::fromWords('هزار و یک'));
        self::assertSame(100, NumberToWords::fromWords('صد'));
        self::assertSame(101, NumberToWords::fromWords('صد و یک'));
        self::assertSame(11, NumberToWords::fromWords('یازده'));
        self::assertSame(321, NumberToWords::fromWords('سیصد و بیست و یک'));
        self::assertSame(0, NumberToWords::fromWords('صفر'));
        self::assertSame(101000, NumberToWords::fromWords('صد و یک هزار'));
        self::assertSame(2300005, NumberToWords::fromWords('دو میلیون و سیصد هزار و پنج'));
        self::assertSame(-42, NumberToWords::fromWords('منفی چهل و دو'));
        self::assertSame(21, NumberToWords::fromWords("بیست\u{200C}و\u{200C}یک"));
    }

    public function test_every_convert_output_up_to_two_thousand_round_trips(): void
    {
        for ($n = 0; $n <= 2000; $n++) {
            self::assertSame($n, NumberToWords::fromWords(NumberToWords::convert($n)), (string) $n);
        }
    }
}
