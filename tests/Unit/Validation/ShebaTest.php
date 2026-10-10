<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\Sheba;

/**
 * Known-answer data: Sheba check digits (ISO 7064 mod 97-10) and card Luhn
 * digits were computed with an independent script.
 */
final class ShebaTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function bankProvider(): array
    {
        return [
            'central' => ['010', 'بانک مرکزی'],
            'mellat' => ['012', 'بانک ملت'],
            'sepah' => ['015', 'بانک سپه'],
            'melli' => ['017', 'بانک ملی ایران'],
            'tejarat' => ['018', 'بانک تجارت'],
            'saderat' => ['019', 'بانک صادرات ایران'],
            'parsian' => ['054', 'بانک پارسیان'],
            'eghtesad-novin' => ['055', 'بانک اقتصاد نوین'],
            'saman' => ['056', 'بانک سامان'],
            'pasargad' => ['057', 'بانک پاسارگاد'],
            'ayandeh' => ['062', 'بانک آینده'],
            'resalat' => ['070', 'بانک قرض‌الحسنه رسالت'],
        ];
    }

    #[DataProvider('bankProvider')]
    public function testBankNameFromValidSheba(string $code, string $name): void
    {
        $sheba = self::makeSheba($code, '0100000000123456789');

        $this->assertTrue(Sheba::isValid($sheba));
        $this->assertSame($name, Sheba::getBankName($sheba));
        $this->assertSame($code, Sheba::validate($sheba)->details()['bank_code']);
    }

    public function testUnknownBankCodeStillValidButNameless(): void
    {
        $sheba = self::makeSheba('999', '0100000000123456789');

        $this->assertTrue(Sheba::isValid($sheba));
        $this->assertNull(Sheba::getBankName($sheba));
    }

    public function testInvalidChecksumHasNoBankName(): void
    {
        $sheba = self::makeSheba('012', '0100000000123456789');
        $bad = 'IR'.(substr($sheba, 2, 2) === '99' ? '98' : '99').substr($sheba, 4);

        $this->assertNull(Sheba::getBankName($bad));
    }

    public function test_sheba_normalize(): void
    {
        $normalized = Sheba::normalize('ir 0620 0000 0000 0000 0000 0000 01');
        $this->assertStringStartsWith('IR', $normalized);
    }

    public function test_sheba_valid_with_bank_name(): void
    {
        $this->assertTrue(Sheba::isValid('IR270170000000100324200001'));
        $this->assertSame('بانک ملی ایران', Sheba::getBankName('IR270170000000100324200001'));
        $this->assertSame('بانک ملت', Sheba::getBankName('ir47 0120 0000 0020 0324 2000 12'));
        $this->assertSame('بانک صادرات ایران', Sheba::getBankName('IR550190000000300123456789'));
        $this->assertTrue(Sheba::isValid('IR۲۷۰۱۷۰۰۰۰۰۰۰۱۰۰۳۲۴۲۰۰۰۰۱'));
    }

    public function test_sheba_without_ir_prefix_only_when_24_digits(): void
    {
        $this->assertTrue(Sheba::isValid('270170000000100324200001'));
        $this->assertSame('IR270170000000100324200001', Sheba::normalize('270170000000100324200001'));
        $this->assertSame('12345', Sheba::normalize('12345'));
        $this->assertFalse(Sheba::isValid('12345'));
    }

    public function test_sheba_invalid(): void
    {
        // The former README example fails mod-97.
        $this->assertFalse(Sheba::isValid('IR062000000000000000000001'));
        $this->assertFalse(Sheba::isValid('IR270170000000100324200002'));
        $this->assertNull(Sheba::getBankName('IR270170000000100324200002'));
        $result = Sheba::validate('IR270170000000100324200002');
        $this->assertSame(['invalid_checksum'], $result->errors());
        $this->assertSame('017', $result->details()['bank_code']);
        $this->assertSame(['invalid_format'], Sheba::validate('XX')->errors());
    }

    /** Build a valid IR Sheba: check digits = 98 - (BBAN+"1827"+"00") mod 97. */
    private static function makeSheba(string $bank, string $account): string
    {
        $bban = $bank.$account;
        $rem = 0;
        foreach (str_split($bban.'182700') as $d) {
            $rem = ($rem * 10 + (int) $d) % 97;
        }

        return 'IR'.str_pad((string) (98 - $rem), 2, '0', STR_PAD_LEFT).$bban;
    }
}
