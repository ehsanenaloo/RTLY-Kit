<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\BankCard;

/**
 * Known-answer data: Sheba check digits (ISO 7064 mod 97-10) and card Luhn
 * digits were computed with an independent script.
 */
final class BankCardTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function binProvider(): array
    {
        return [
            'melli' => ['603799', 'بانک ملی ایران'],
            'sepah' => ['589210', 'بانک سپه'],
            'mellat' => ['610433', 'بانک ملت'],
            'tejarat' => ['627353', 'بانک تجارت'],
            'tejarat2' => ['585983', 'بانک تجارت'],
            'keshavarzi' => ['603770', 'بانک کشاورزی'],
            'saman' => ['621986', 'بانک سامان'],
            'parsian' => ['622106', 'بانک پارسیان'],
            'eghtesad novin' => ['627412', 'بانک اقتصاد نوین'],
            'sina' => ['639346', 'بانک سینا'],
            'pasargad' => ['502229', 'بانک پاسارگاد'],
            'saderat' => ['603769', 'بانک صادرات ایران'],
            'maskan' => ['628023', 'بانک مسکن'],
            'refah' => ['589463', 'بانک رفاه کارگران'],
        ];
    }

    #[DataProvider('binProvider')]
    public function testKnownBins(string $bin, string $name): void
    {
        $card = self::withCheckDigit($bin . '123456789');
        $this->assertTrue(BankCard::isValid($card));
        $this->assertSame($name, BankCard::getBankName($card));
    }

    public function testUnknownBinReturnsNull(): void
    {
        $card = self::withCheckDigit('999999123456789');
        $this->assertTrue(BankCard::isValid($card));
        $this->assertNull(BankCard::getBankName($card));
    }

    public function test_bank_card_luhn_invalid(): void
    {
        $this->assertFalse(BankCard::isValid('1234567890123456'));
        $this->assertFalse(BankCard::isValid('0000000000000000'));
    }

    public function test_bank_card_valid(): void
    {
        $this->assertTrue(BankCard::isValid('6037991234567893'));
        $this->assertTrue(BankCard::isValid('6037-9912-3456-7893'));
        $this->assertTrue(BankCard::isValid('۶۰۳۷۹۹۱۲۳۴۵۶۷۸۹۳'));
        $this->assertSame('بانک ملی ایران', BankCard::getBankName('6037991234567893'));
        $this->assertSame('بانک ملت', BankCard::getBankName('6104339876543210'));
        $this->assertSame('بانک سامان', BankCard::getBankName('6219860000000019'));
    }

    public function test_bank_card_invalid(): void
    {
        $this->assertFalse(BankCard::isValid('6037991234567894'));
        $this->assertNull(BankCard::getBankName('6037991234567894'));
        $this->assertSame(['invalid_checksum'], BankCard::validate('6037991234567894')->errors());
        $this->assertSame(['repeated_digits'], BankCard::validate('0000000000000000')->errors());
        $this->assertSame(['invalid_format'], BankCard::validate('1234')->errors());
        // Luhn-valid card with an unknown BIN is valid but has no bank name.
        $this->assertTrue(BankCard::isValid('4111111111111111'));
        $this->assertNull(BankCard::getBankName('4111111111111111'));
    }

    private static function withCheckDigit(string $first15): string
    {
        for ($d = 0; $d <= 9; $d++) {
            $card = $first15 . $d;
            $sum = 0;
            for ($i = 0; $i < 16; $i++) {
                $n = (int) $card[15 - $i];
                if ($i % 2 === 1) {
                    $n *= 2;
                    if ($n > 9) {
                        $n -= 9;
                    }
                }
                $sum += $n;
            }
            if ($sum % 10 === 0) {
                return $card;
            }
        }

        return $first15 . '0';
    }
}
