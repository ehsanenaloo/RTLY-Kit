<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\VehiclePlate;

use function RtlyKit\validate_vehicle_plate;

final class VehiclePlateTest extends TestCase
{
    public function test_vehicle_plate_validate_helper(): void
    {
        $r = validate_vehicle_plate('12ب345 ایران 67');
        self::assertTrue($r->isValid());
        self::assertSame('ب', $r->details()['letter']);
        self::assertSame('67', $r->details()['region']);
        self::assertSame(['invalid_format'], VehiclePlate::validate('abc')->errors());
    }

    public function test_vehicle_plate_normalize(): void
    {
        $normalized = VehiclePlate::normalize('12 ب 345 ایران 67');
        $this->assertNotEmpty($normalized);
    }

    public function test_bare_alef_is_an_alias_of_alef_lam_fe(): void
    {
        foreach (['12ا34567', '۱۲ ا ۳۴۵ ایران ۶۷', '12أ34567', '12إ34567', '12الف34567'] as $input) {
            $r = VehiclePlate::validate($input);
            self::assertTrue($r->isValid(), $input);
            self::assertSame('الف', $r->details()['letter'], $input);
            self::assertSame('12الف34567', $r->details()['normalized'], $input);
        }
        self::assertSame('الف', VehiclePlate::parse('12ا34567')['letter'] ?? null);
        self::assertSame('12الف34567', VehiclePlate::normalize('12ا34567'));
        // Not an alias elsewhere: two letters, no letter, and آ (a different letter) stay invalid.
        self::assertFalse(VehiclePlate::isValid('12اا34567'));
        self::assertFalse(VehiclePlate::isValid('12آ34567'));
        self::assertFalse(VehiclePlate::isValid('12ا3456'));
    }

    public function test_arabic_letter_variants_are_read_as_persian(): void
    {
        self::assertSame('ک', VehiclePlate::parse('12ك34567')['letter'] ?? null);
        self::assertSame('ی', VehiclePlate::parse('12ي34567')['letter'] ?? null);
        self::assertSame('ی', VehiclePlate::parse('12ى34567')['letter'] ?? null);
        self::assertSame('ه', VehiclePlate::parse('12ة34567')['letter'] ?? null);
    }

    public function test_vehicle_plate(): void
    {
        $this->assertTrue(VehiclePlate::isValid('12ب34567'));
        $this->assertTrue(VehiclePlate::isValid('12 ب 345 ایران 67'));
        $this->assertTrue(VehiclePlate::isValid('۱۲ ب ۳۴۵ ايران ۶۷'));
        $this->assertTrue(VehiclePlate::isValid('12الف34567'));
        $this->assertSame(
            ['two_digit' => '12', 'letter' => 'ب', 'three_digit' => '345', 'region' => '67'],
            VehiclePlate::parse('12 ب 345 ایران 67'),
        );
        $this->assertFalse(VehiclePlate::isValid('12ب34500'));
        $this->assertSame(['invalid_region'], VehiclePlate::validate('12ب34500')->errors());
        $this->assertNull(VehiclePlate::parse('12ب34500'));
        $this->assertFalse(VehiclePlate::isValid('12چ34567'));
        $this->assertFalse(VehiclePlate::isValid('12بب34567'));
    }
}
