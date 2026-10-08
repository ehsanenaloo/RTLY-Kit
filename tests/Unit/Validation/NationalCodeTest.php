<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\NationalCode;

final class NationalCodeTest extends TestCase
{
    public function test_valid_national_codes(): void
    {
        // These are well-known valid test patterns (checksum correct)
        $this->assertTrue(NationalCode::isValid('0013542419'));
        $this->assertTrue(NationalCode::isValid('0499370899'));
    }

    public function test_invalid_national_codes(): void
    {
        $this->assertFalse(NationalCode::isValid('1234567890'));
        $this->assertFalse(NationalCode::isValid('0000000000'));
        $this->assertFalse(NationalCode::isValid('1111111111'));
        $this->assertFalse(NationalCode::isValid('123'));
        $this->assertFalse(NationalCode::isValid('abcdefghij'));
    }

    public function test_persian_digits(): void
    {
        $this->assertTrue(NationalCode::isValid('۰۰۱۳۵۴۲۴۱۹'));
    }

    public function test_normalize(): void
    {
        $this->assertSame('0013542419', NationalCode::normalize('۰۰۱۳۵۴۲۴۱۹'));
        $this->assertSame('0013542419', NationalCode::normalize(' 0013542419 '));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function knownPrefixes(): iterable
    {
        yield '001 Tehran' => ['0011234563', 'تهران', 'تهران مرکزی'];
        yield '044 Shemiran' => ['0441234569', 'تهران', 'شمیران'];
        yield '105 Neyshabur' => ['1051234565', 'خراسان رضوی', 'نیشابور'];
        yield '112 Fereydunshahr' => ['1121234569', 'اصفهان', 'فریدونشهر'];
        yield '136 Tabriz' => ['1361234563', 'آذربایجان شرقی', 'تبریز'];
        yield '169 Azarshahr' => ['1691234567', 'آذربایجان شرقی', 'آذرشهر'];
    }

    #[DataProvider('knownPrefixes')]
    public function test_known_prefix_resolves_location(string $code, string $province, string $city): void
    {
        $this->assertTrue(NationalCode::isValid($code));
        $this->assertSame(['province' => $province, 'city' => $city], NationalCode::getLocation($code));
    }

    public function test_persian_digits_resolve_location(): void
    {
        $this->assertSame(
            ['province' => 'آذربایجان شرقی', 'city' => 'تبریز'],
            NationalCode::getLocation('۱۳۶۱۲۳۴۵۶۳'),
        );
    }

    public function test_unknown_prefix_and_invalid_code_return_null(): void
    {
        // Prefix 000 is not in the verified table; checksum-valid code.
        $this->assertTrue(NationalCode::isValid('0001234560'));
        $this->assertNull(NationalCode::getLocation('0001234560'));
        $this->assertNull(NationalCode::getLocation('1361234560'));
        $this->assertNull(NationalCode::getLocation('abc'));
    }

    public function test_national_code_result(): void
    {
        $ok = NationalCode::validate('0013542419');
        $this->assertTrue($ok->isValid());
        $this->assertSame([], $ok->errors());
        $this->assertSame('0013542419', $ok->details()['normalized']);
        $this->assertSame(['invalid_checksum'], NationalCode::validate('0013542418')->errors());
        $this->assertSame(['repeated_digits'], NationalCode::validate('1111111111')->errors());
        $this->assertSame(['invalid_format'], NationalCode::validate('123')->errors());
        $this->assertSame(['province' => 'تهران', 'city' => 'تهران مرکزی'], NationalCode::getLocation('0013542419'));
    }
}
