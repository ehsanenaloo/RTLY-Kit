<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\Sheba;

/**
 * Known-answer data from the national IBAN (Sheba) specification of the Central
 * Bank of Iran ("مشخصات ملی شناسه حساب بانکی ایران (شبا)", section 5-2-1, table of
 * bank identifiers, dated 19 Tir 1396 on the page), as published on the website
 * of Bank Melli Iran. The live page returns HTTP 403 from outside Iran; the copy
 * read on 2026-10-08 is the Internet Archive capture of 2021-05-18:
 * https://web.archive.org/web/20210518172456/https://bmi.ir/fa/pages/192/
 * (19 rows). The table is the official list of that date: codes assigned later
 * (for example 022, 052, 059-066, 069-079, 090, 095) are not in it.
 *
 * Our table uses the short bank names; the official name is allowed to be longer
 * when ours is its beginning (010 and 019).
 */
final class ShebaOfficialCodesTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function officialTable(): array
    {
        return [
            '055' => ['055', 'بانک اقتصاد نوین'],
            '054' => ['054', 'بانک پارسیان'],
            '057' => ['057', 'بانک پاسارگاد'],
            '021' => ['021', 'پست بانک ایران'],
            '018' => ['018', 'بانک تجارت'],
            '051' => ['051', 'موسسه اعتباری توسعه'],
            '020' => ['020', 'بانک توسعه صادرات'],
            '013' => ['013', 'بانک رفاه'],
            '056' => ['056', 'بانک سامان'],
            '015' => ['015', 'بانک سپه'],
            '058' => ['058', 'بانک سرمایه'],
            '019' => ['019', 'بانک صادرات ایران'],
            '011' => ['011', 'بانک صنعت و معدن'],
            '053' => ['053', 'بانک کارآفرین'],
            '016' => ['016', 'بانک کشاورزی'],
            '010' => ['010', 'بانک مرکزی جمهوری اسلامی ایران'],
            '014' => ['014', 'بانک مسکن'],
            '012' => ['012', 'بانک ملت'],
            '017' => ['017', 'بانک ملی ایران'],
        ];
    }

    #[DataProvider('officialTable')]
    public function test_our_table_agrees_with_the_official_specification(string $code, string $officialName): void
    {
        $name = Sheba::getBankName(self::makeSheba($code));

        $this->assertNotNull($name, "code {$code} missing from our table");
        $this->assertStringStartsWith($name, $officialName, "code {$code}");
    }

    /** The conflict between aggregator pages is settled by the official table: 051 is the credit institution. */
    public function test_051_is_the_tosee_credit_institution_not_tosee_taavon(): void
    {
        $this->assertSame('موسسه اعتباری توسعه', Sheba::getBankName(self::makeSheba('051')));
        $this->assertNotSame('بانک توسعه تعاون', Sheba::getBankName(self::makeSheba('051')));
    }

    private static function makeSheba(string $bank): string
    {
        $bban = $bank.'0100000000123456789';
        $rem  = 0;
        foreach (str_split($bban.'182700') as $d) {
            $rem = ($rem * 10 + (int) $d) % 97;
        }

        return 'IR'.str_pad((string) (98 - $rem), 2, '0', STR_PAD_LEFT).$bban;
    }
}
