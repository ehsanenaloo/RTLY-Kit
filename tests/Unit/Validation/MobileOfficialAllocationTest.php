<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\Mobile;

/**
 * The Communications Regulatory Authority of Iran (CRA) national numbering plan
 * ("Communication of 24.VIII.2026", published by the ITU, posted 2026-10-01,
 * https://www.itu.int/oth/T0202000066/en , English Word file
 * /dms_pub/itu-t/oth/02/02/T02020000660035MSWE.docx, read 2026-10-08) lists the
 * national destination codes (NDC) below as "Mobile services". The plan names
 * no operators, so it can only confirm that a prefix is a mobile allocation;
 * the operator names come from the public tables in resources/data/SOURCES.md.
 *
 * The same plan lists the 94xxx blocks (942121, 94200, 94220, 94221, 94260,
 * 942800-942802, 942900-942904, 9430130, 940000, 940009, 9412, 94440) as
 * "Fixed Phone" non-geographical numbers, which is why 0941 is not in our
 * operator table.
 */
final class MobileOfficialAllocationTest extends TestCase
{
    /** NDCs listed as "Mobile services" (digits after the national prefix 0). */
    private const CRA_MOBILE_NDC = [
        '900', '901', '902', '903', '904', '905', '91', '920', '921', '922', '923', '93',
        '990', '991', '992', '993', '994', '99510', '99550', '996', '9981', '9982',
        '99830', '99831', '99832', '99888', '99900', '99901', '99902', '99903', '9991',
        '99921', '99930', '99931', '99932', '99933', '99934', '9995', '99969', '99977',
        '9998', '9999',
    ];

    /**
     * One example number per operator-table prefix, with the operator name.
     *
     * @return array<string, array{string, string}>
     */
    public static function operatorPrefixes(): array
    {
        $mci     = 'همراه اول';
        $irancell = 'ایرانسل';
        $rightel = 'رایتل';
        $rows = [
            ['0910', $mci], ['0911', $mci], ['0912', $mci], ['0913', $mci], ['0914', $mci],
            ['0915', $mci], ['0916', $mci], ['0917', $mci], ['0918', $mci], ['0919', $mci],
            ['0990', $mci], ['0991', $mci], ['0992', $mci], ['0993', $mci], ['0994', $mci],
            ['0900', $irancell], ['0901', $irancell], ['0902', $irancell], ['0903', $irancell],
            ['0904', $irancell], ['0905', $irancell], ['0930', $irancell], ['0933', $irancell],
            ['0935', $irancell], ['0936', $irancell], ['0937', $irancell], ['0938', $irancell],
            ['0939', $irancell],
            ['0920', $rightel], ['0921', $rightel], ['0922', $rightel], ['0923', $rightel],
            ['0931', 'اسپادان'], ['0932', 'تالیا'], ['0934', 'تله‌کیش'],
            ['09981', 'شاتل موبایل'], ['09982', 'شاتل موبایل'], ['09991', 'آپتل'],
        ];

        $out = [];
        foreach ($rows as [$prefix, $operator]) {
            $out[$prefix] = [$prefix, $operator];
        }

        return $out;
    }

    #[DataProvider('operatorPrefixes')]
    public function test_every_operator_prefix_is_a_mobile_allocation_in_the_cra_plan(string $prefix, string $operator): void
    {
        $number = str_pad($prefix, 11, '1');

        $this->assertSame($operator, Mobile::getOperator($number), $prefix);

        $ndc    = substr($number, 1);
        $listed = false;
        foreach (self::CRA_MOBILE_NDC as $allocated) {
            if (str_starts_with($ndc, $allocated)) {
                $listed = true;
                break;
            }
        }
        $this->assertTrue($listed, "{$prefix} is not inside a CRA 'Mobile services' NDC");
    }

    /** 0991 (Hamrah-e Aval) and 09991 (Aptel) are two different CRA allocations (991 and 9991). */
    public function test_0991_and_09991_are_different_operators(): void
    {
        $this->assertSame('همراه اول', Mobile::getOperator('09911234567'));
        $this->assertSame('آپتل', Mobile::getOperator('09991234567'));
    }

    /** CRA lists 99830-99832 and 99888 separately from 9981/9982; they are not Shatel Mobile's blocks. */
    public function test_other_09983_and_09988_blocks_are_not_labelled_shatel(): void
    {
        $this->assertNull(Mobile::getOperator('09983012345'));
        $this->assertNull(Mobile::getOperator('09988812345'));
    }

    /** 094x is a fixed-number block in the CRA plan, not a mobile operator range. */
    public function test_0941_has_no_operator(): void
    {
        $this->assertNull(Mobile::getOperator('09411234567'));
    }
}
