<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\Mobile;

final class MobileTest extends TestCase
{
    public function test_mobile_valid(): void
    {
        $this->assertTrue(Mobile::isValid('09121234567'));
        $this->assertTrue(Mobile::isValid('989121234567'));
        $this->assertTrue(Mobile::isValid('۰۹۱۲۱۲۳۴۵۶۷'));
        $this->assertSame('همراه اول', Mobile::getOperator('09121234567'));
    }

    public function test_mobile_invalid(): void
    {
        $this->assertFalse(Mobile::isValid('091212345'));
        $this->assertFalse(Mobile::isValid('08121234567'));
    }

    public function test_mobile_normalize_forms(): void
    {
        foreach (['09121234567', '9121234567', '989121234567', '+98 912 123 4567', '00989121234567', '+98-912-123-4567', '۰۹۱۲۱۲۳۴۵۶۷'] as $in) {
            $this->assertSame('09121234567', Mobile::normalize($in), $in);
            $this->assertTrue(Mobile::isValid($in), $in);
        }
        $this->assertFalse(Mobile::isValid('0912123456'));
        $this->assertFalse(Mobile::isValid('09121234567890'));
    }

    public function test_mobile_operators(): void
    {
        $this->assertSame('همراه اول', Mobile::getOperator('09191234567'));
        $this->assertSame('همراه اول', Mobile::getOperator('09911234567'));
        $this->assertSame('ایرانسل', Mobile::getOperator('09351234567'));
        $this->assertSame('ایرانسل', Mobile::getOperator('09011234567'));
        $this->assertSame('رایتل', Mobile::getOperator('09211234567'));
        $this->assertSame('شاتل موبایل', Mobile::getOperator('09981234567'));
        $this->assertSame('آپتل', Mobile::getOperator('09991012345'));
        foreach (['0990', '0991', '0992', '0993', '0994'] as $prefix) {
            $this->assertSame('همراه اول', Mobile::getOperator($prefix.'1234567'), $prefix);
        }
        // Valid shape, but prefix not in the conservative table.
        $this->assertTrue(Mobile::isValid('09951234567'));
        $this->assertNull(Mobile::getOperator('09951234567'));
        $this->assertNull(Mobile::getOperator('08121234567'));
    }
}
