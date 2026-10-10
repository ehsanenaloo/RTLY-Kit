<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\PostalCode;

use function RtlyKit\validate_postal_code;

final class PostalCodeTest extends TestCase
{
    public function test_postal_code_validate(): void
    {
        $ok = PostalCode::validate('۱۵۹۳۷-۱۵۴۱۶');
        self::assertTrue($ok->isValid());
        self::assertSame('1593715416', $ok->details()['normalized']);

        self::assertSame(['invalid_length'], PostalCode::validate('12345')->errors());
        self::assertSame(['invalid_format'], PostalCode::validate('0123456789')->errors());
        self::assertTrue(validate_postal_code('1593715416')->isValid());
        self::assertTrue(PostalCode::isValid('1593715416'));
        self::assertFalse(PostalCode::isValid('0123456789'));
    }

    /**
     * Codes whose first five digits hold neither 0 nor 2 (rule stated by three public sources, see
     * resources/data/SOURCES.md).
     *
     * @return array<string, array{string}>
     */
    public static function validCodes(): array
    {
        return [
            'tehran' => ['1593715416'],
            'digits 1 and 3 to 9 only' => ['1345678910'],
            'zero and two after the fifth digit' => ['1345602020'],
            'repeated digit is not checked' => ['1111111111'],
            'nines' => ['9999999999'],
        ];
    }

    #[DataProvider('validCodes')]
    public function test_valid_codes(string $code): void
    {
        self::assertTrue(PostalCode::isValid($code), $code);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function codesWithZeroOrTwoInTheFirstFive(): array
    {
        return [
            'starts with 0' => ['0593715416'],
            'zero first' => ['0123456789'],
            'two first' => ['2593715416'],
            'two in the third place' => ['1523715416'],
            'zero in the fifth place' => ['1593015416'],
            'two in the fifth place' => ['1593215416'],
            'two in the second place' => ['1234567890'],
        ];
    }

    #[DataProvider('codesWithZeroOrTwoInTheFirstFive')]
    public function test_zero_or_two_in_the_first_five_digits_is_invalid_format(string $code): void
    {
        self::assertSame(['invalid_format'], PostalCode::validate($code)->errors(), $code);
    }

    public function test_postal_code(): void
    {
        $this->assertTrue(PostalCode::isValid('1593715416'));
        $this->assertFalse(PostalCode::isValid('0123456789')); // starts with 0
        $this->assertFalse(PostalCode::isValid('12345'));
    }
}
