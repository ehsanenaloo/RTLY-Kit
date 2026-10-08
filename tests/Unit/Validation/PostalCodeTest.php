<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\PostalCode;

use function RtlyKit\validate_postal_code;

final class PostalCodeTest extends TestCase
{
    public function test_postal_code_validate(): void
    {
        $ok = PostalCode::validate('۱۲۳۴۵-۶۷۸۹۰');
        self::assertTrue($ok->isValid());
        self::assertSame('1234567890', $ok->details()['normalized']);

        self::assertSame(['invalid_length'], PostalCode::validate('12345')->errors());
        self::assertSame(['invalid_format'], PostalCode::validate('0123456789')->errors());
        self::assertTrue(validate_postal_code('1234567890')->isValid());
        self::assertTrue(PostalCode::isValid('1234567890'));
        self::assertFalse(PostalCode::isValid('0123456789'));
    }

    public function test_postal_code(): void
    {
        $this->assertTrue(PostalCode::isValid('1234567890'));
        $this->assertFalse(PostalCode::isValid('0123456789')); // starts with 0
        $this->assertFalse(PostalCode::isValid('12345'));
    }
}
