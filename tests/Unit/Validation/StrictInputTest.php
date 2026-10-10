<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\Mobile;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\PostalCode;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;

/**
 * Only digits and the allowed separators may surround a number; text, other punctuation,
 * negative numbers and control characters (a trailing newline or NUL) are invalid.
 */
final class StrictInputTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function mobileGood(): array
    {
        return [
            'national' => ['09121234567'],
            'bare' => ['9121234567'],
            '98' => ['989121234567'],
            'plus98' => ['+989121234567'],
            'plus98 spaced' => ['+98 912 123 4567'],
            '0098' => ['00989121234567'],
            'persian digits' => ['۰۹۱۲۱۲۳۴۵۶۷'],
            'arabic digits' => ['٠٩١٢١٢٣٤٥٦٧'],
            'hyphens' => ['0912-123-4567'],
            'parentheses' => ['(0912) 123 4567'],
            'dots' => ['0912.123.4567'],
            'nbsp' => ["0912\u{00A0}123\u{00A0}4567"],
            'zwnj' => ["0912\u{200C}1234567"],
            'lrm and rlm' => ["\u{200E}0912\u{200F}1234567"],
        ];
    }

    #[DataProvider('mobileGood')]
    public function test_mobile_accepted_forms(string $input): void
    {
        self::assertTrue(Mobile::isValid($input), $input);
        self::assertSame('09121234567', Mobile::normalize($input), $input);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function mobileBad(): array
    {
        return [
            'negative int' => [-9121234567],
            'negative string' => ['-9121234567'],
            'negative with space' => [' -09121234567'],
            'text around' => ['call 09121234567 now'],
            'slash' => ['0912 / 1234567'],
            'trailing letter' => ['09121234567x'],
            'trailing newline' => ["09121234567\n"],
            'trailing nul' => ["09121234567\0"],
            'leading tab' => ["\t09121234567"],
            'plus not 98' => ['+09121234567'],
            'plus inside' => ['0912+1234567'],
            'double plus' => ['++989121234567'],
            'comma' => ['0912,1234567'],
        ];
    }

    #[DataProvider('mobileBad')]
    public function test_mobile_rejects_text_and_signs(mixed $input): void
    {
        $result = Mobile::validate($input);

        self::assertFalse($result->isValid());
        self::assertSame(['invalid_format'], $result->errors());
    }

    public function test_positive_int_input_still_works(): void
    {
        self::assertTrue(Mobile::isValid(9121234567));
        self::assertTrue(PostalCode::isValid(1593715416));
        self::assertTrue(BankCard::isValid(6037991234567893));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function postalBad(): array
    {
        return [
            'text' => ['Zip: 1593715416!'],
            'negative int' => [-1593715416],
            'negative string' => ['-1593715416'],
            'slash' => ['15937/15416'],
            'trailing newline' => ["1234567890\n"],
            'letter' => ['15937A5416'],
        ];
    }

    #[DataProvider('postalBad')]
    public function test_postal_code_rejects_text_and_signs(mixed $input): void
    {
        self::assertSame(['invalid_format'], PostalCode::validate($input)->errors());
    }

    public function test_postal_code_accepts_separators(): void
    {
        foreach (['15937-15416', '15937 15416', "15937\u{00A0}15416", '(15937)15416', '15937.15416', '۱۵۹۳۷۱۵۴۱۶'] as $in) {
            self::assertTrue(PostalCode::isValid($in), $in);
            self::assertSame('1593715416', PostalCode::normalize($in), $in);
        }
        self::assertSame(['invalid_length'], PostalCode::validate('12345')->errors());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function cardBad(): array
    {
        return [
            'text' => ['card 6037 9912 3456 7893 ok'],
            'negative string' => ['-6037991234567893'],
            'trailing newline' => ["6037991234567893\n"],
            'slash' => ['6037/9912/3456/7893'],
        ];
    }

    #[DataProvider('cardBad')]
    public function test_bank_card_rejects_text_and_signs(mixed $input): void
    {
        self::assertSame(['invalid_format'], BankCard::validate($input)->errors());
    }

    public function test_bank_card_accepts_separators(): void
    {
        foreach (['6037 9912 3456 7893', '6037-9912-3456-7893', "6037\u{00A0}9912\u{00A0}3456\u{00A0}7893", '۶۰۳۷-۹۹۱۲-۳۴۵۶-۷۸۹۳'] as $in) {
            self::assertTrue(BankCard::isValid($in), $in);
        }
    }

    /**
     * Trailing newline / NUL used to pass because trim() strips them.
     */
    public function test_national_code_and_sheba_reject_control_characters(): void
    {
        foreach (["0499370899\n", "0499370899\0", "\n0499370899", "0499370899\r\n", "0499370899\t"] as $in) {
            self::assertSame(['invalid_format'], NationalCode::validate($in)->errors(), bin2hex($in));
        }

        $sheba = 'IR270170000000100324200001';
        self::assertTrue(Sheba::isValid($sheba));
        foreach ([$sheba."\n", $sheba."\0", "\n".$sheba, $sheba."\r\n"] as $in) {
            self::assertSame(['invalid_format'], Sheba::validate($in)->errors(), bin2hex($in));
        }
    }

    public function test_national_code_accepts_all_spacing_characters(): void
    {
        self::assertTrue(NationalCode::isValid("049\u{00A0}937\u{200C}0899"));
        self::assertTrue(NationalCode::isValid("\u{200F}0499370899\u{200E}"));
        self::assertTrue(NationalCode::isValid(' 049-937-0899 '));
    }

    public function test_vehicle_plate_rejects_trailing_newline(): void
    {
        self::assertTrue(VehiclePlate::isValid('12ب34567'));
        self::assertFalse(VehiclePlate::isValid("12ب34567\n"));
        self::assertFalse(VehiclePlate::isValid("12ب34567\0"));
    }

    /**
     * ISO 13616 only produces check digits 02..98. These three strings pass mod 97-10 but are not
     * producible by the standard (00 is the same residue as 97, 01 as 98, 99 as 02).
     *
     * @return array<string, array{string}>
     */
    public static function impossibleCheckDigits(): array
    {
        return [
            '00' => ['IR000170100000000000000070'],
            '01' => ['IR010170100000000000000052'],
            '99' => ['IR990170100000000000000034'],
        ];
    }

    #[DataProvider('impossibleCheckDigits')]
    public function test_sheba_rejects_check_digits_outside_02_to_98(string $sheba): void
    {
        $result = Sheba::validate($sheba);

        self::assertFalse($result->isValid());
        self::assertSame(['invalid_checksum'], $result->errors());
    }

    public function test_sheba_boundary_check_digits_02_and_98_are_producible(): void
    {
        // Same BBAN with the matching check digits 97 -> valid, proving only 00/01/99 are special.
        self::assertTrue(Sheba::isValid('IR970170100000000000000070'));
        self::assertTrue(Sheba::isValid('IR980170100000000000000052'));
        self::assertTrue(Sheba::isValid('IR020170100000000000000034'));
    }

    public function test_bank_names_agree_between_card_and_sheba_tables(): void
    {
        /** @var array<string, string> $sheba */
        $sheba = require dirname(__DIR__, 3).'/resources/data/sheba-banks.php';
        /** @var array<int, string> $bins */
        $bins = require dirname(__DIR__, 3).'/resources/data/bank-bins.php';

        self::assertSame('بانک صادرات ایران', $sheba['019']);
        self::assertSame('بانک رفاه کارگران', $sheba['013']);
        self::assertSame('بانک صادرات ایران', $bins[603769]);
        self::assertSame('بانک رفاه کارگران', $bins[589463]);

        // No bank may be spelled with a short name in one table and a longer one in the other.
        $zwnj = "\u{200C}";
        foreach ($sheba as $code => $shebaName) {
            foreach ($bins as $bin => $binName) {
                $a = str_replace($zwnj, '', $shebaName);
                $b = str_replace($zwnj, '', $binName);
                if ($a === $b) {
                    continue;
                }
                self::assertFalse(str_starts_with($a, $b) || str_starts_with($b, $a), "{$code} '{$shebaName}' vs {$bin} '{$binName}'");
            }
        }
    }
}
