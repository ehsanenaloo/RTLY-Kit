<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\Mobile;

/**
 * Known-answer rows for the `allocated` flag. The blocks come from the CRA national
 * numbering plan (ITU communication of 24.VIII.2026), see
 * the data verification notes (October 2026), section R2.E.
 * `allocated=false` means "not in the published plan", never "invalid".
 */
final class MobileAllocationFlagTest extends TestCase
{
    /**
     * @return array<string, array{string, bool, ?string}>
     */
    public static function rows(): array
    {
        return [
            '0900 Irancell block'          => ['09001234567', true, 'ایرانسل'],
            '0905 last of 900-905'         => ['09051234567', true, 'ایرانسل'],
            '0906 outside 900-905'         => ['09061234567', false, null],
            '0909 outside 900-905'         => ['09091234567', false, null],
            '0912 MCI'                     => ['09121234567', true, 'همراه اول'],
            '0919 MCI'                     => ['09191234567', true, 'همراه اول'],
            '0923 Rightel'                 => ['09231234567', true, 'رایتل'],
            '0924 outside 920-923'         => ['09241234567', false, null],
            '0929 outside 920-923'         => ['09291234567', false, null],
            '0931 Espadan'                 => ['09311234567', true, 'اسپادان'],
            '0941 fixed block'             => ['09411234567', false, null],
            '0940 fixed block'             => ['09401234567', false, null],
            '0990 MCI'                     => ['09901234567', true, 'همراه اول'],
            '0991 MCI not Aptel'           => ['09911234567', true, 'همراه اول'],
            '0994 MCI'                     => ['09941234567', true, 'همراه اول'],
            '09981 Shatel Mobile'          => ['09981234567', true, 'شاتل موبایل'],
            '09982 Shatel Mobile'          => ['09982234567', true, 'شاتل موبایل'],
            '09983 0 listed, no operator'  => ['09983012345', true, null],
            '09983 2 listed, no operator'  => ['09983212345', true, null],
            '09983 3 not listed'           => ['09983312345', false, null],
            '09988 8 listed, no operator'  => ['09988812345', true, null],
            '09988 1 not listed'           => ['09988112345', false, null],
            '09990 0 listed'               => ['09990012345', true, null],
            '09990 4 not listed'           => ['09990412345', false, null],
            '09991 Aptel'                  => ['09991234567', true, 'آپتل'],
            'Persian digits, +98 form'     => ['+۹۸ ۹۱۲ ۱۲۳ ۴۵۶۷', true, 'همراه اول'],
        ];
    }

    #[DataProvider('rows')]
    public function test_allocated_flag_and_operator(string $input, bool $allocated, ?string $operator): void
    {
        $result = Mobile::validate($input);

        $this->assertTrue($result->isValid(), $input);
        $this->assertSame($allocated, $result->details()['allocated'] ?? null, $input);
        $this->assertArrayHasKey('operator', $result->details(), $input);
        $this->assertSame($operator, $result->details()['operator'], $input);
        $this->assertSame($allocated, Mobile::isAllocated($input), $input);
        $this->assertSame($operator, Mobile::getOperator($input), $input);
    }

    public function test_unallocated_prefix_is_still_valid(): void
    {
        $this->assertTrue(Mobile::isValid('09061234567'));
        $this->assertFalse(Mobile::isAllocated('09061234567'));
    }

    public function test_invalid_input_is_never_allocated(): void
    {
        foreach (['09121234', '08121234567', '', 'abc', null, [], 1.5, str_repeat('9', 5000)] as $bad) {
            $this->assertFalse(Mobile::isValid($bad));
            $this->assertFalse(Mobile::isAllocated($bad));
            $this->assertFalse(Mobile::validate($bad)->details()['allocated'] ?? false);
        }
    }
}
