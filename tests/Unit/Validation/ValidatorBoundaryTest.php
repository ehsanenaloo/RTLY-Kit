<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\DataTables;
use RtlyKit\Validation\Mobile;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\PostalCode;
use RtlyKit\Validation\Result;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;

/**
 * Known-answer boundary cases: anchors, normalisation and the exact ranges of every validator.
 */
final class ValidatorBoundaryTest extends TestCase
{
    public function test_national_code_is_anchored_at_both_ends(): void
    {
        $this->assertSame(['invalid_format'], NationalCode::validate('a0499370899')->errors());
        $this->assertSame(['invalid_format'], NationalCode::validate('0499370899a')->errors());
        $this->assertSame(['invalid_format'], NationalCode::validate('10499370899')->errors());
        $this->assertSame(['invalid_format'], NationalCode::validate('049937089')->errors());
    }

    public function test_national_code_normalize_strips_separators_and_whitespace(): void
    {
        $this->assertSame('0499370899', NationalCode::normalize('049 937-0899'));
        $this->assertSame('0499370899', NationalCode::normalize("049\u{200C}9370899"));
        $this->assertSame('0499370899', NationalCode::normalize("\n\t0499370899\r\n"));
        $this->assertTrue(NationalCode::isValid("\n0499370899\n"));
    }

    public function test_national_code_checksum_uses_the_right_weights(): void
    {
        $this->assertTrue(NationalCode::isValid('0499370899'));
        $this->assertSame(['invalid_checksum'], NationalCode::validate('0499370898')->errors());
        $this->assertSame(['invalid_checksum'], NationalCode::validate('4099370899')->errors());
    }

    public function test_float_input_boundary(): void
    {
        $this->assertSame(['invalid_type'], NationalCode::validate(1.0E15)->errors());
        $this->assertSame('999999999999999', NationalCode::validate(999999999999999.0)->details()['normalized']);
        $this->assertSame('499370899', NationalCode::validate(499370899.0)->details()['normalized']);
    }

    public function test_sheba_anchors_and_normalisation(): void
    {
        $valid = 'IR270170000000100324200001';
        $this->assertTrue(Sheba::isValid($valid));
        $this->assertSame(['invalid_format'], Sheba::validate('A'.$valid)->errors());
        $this->assertSame(['invalid_format'], Sheba::validate($valid.'0')->errors());
        $this->assertTrue(Sheba::isValid("\t".$valid."\n"));
        $this->assertTrue(Sheba::isValid('ir27 0170-0000 0010 0324 2000 01'));
        $this->assertSame($valid, Sheba::normalize(substr($valid, 2)));
        // 25 digits is not the bare 24-digit form and gets no prefix.
        $this->assertSame('2701700000001003242000011', Sheba::normalize('2701700000001003242000011'));
    }

    public function test_sheba_details_carry_bank_code(): void
    {
        $r = Sheba::validate('IR270170000000100324200001');
        $this->assertSame('017', $r->details()['bank_code']);
        $this->assertSame('IR270170000000100324200001', $r->details()['normalized']);
    }

    public function test_bank_card_anchors(): void
    {
        $this->assertTrue(BankCard::isValid('6037991234567893'));
        $this->assertSame(['invalid_format'], BankCard::validate('16037991234567893')->errors());
        $this->assertSame(['invalid_format'], BankCard::validate('60379912345678931')->errors());
        $this->assertSame(['invalid_format'], BankCard::validate('603799123456789')->errors());
    }

    public function test_bank_card_repeated_digits_and_luhn(): void
    {
        $this->assertSame(['repeated_digits'], BankCard::validate('0000000000000000')->errors());
        $this->assertSame(['repeated_digits'], BankCard::validate('5555 5555 5555 5555')->errors());
        // 4539 1488 0343 6467 is a published Luhn-valid test number.
        $this->assertTrue(BankCard::isValid('4539148803436467'));
        $this->assertFalse(BankCard::isValid('4539148803436476'));
        $this->assertFalse(BankCard::isValid('4539148803436468'));
    }

    public function test_bank_card_details(): void
    {
        $r = BankCard::validate('6037-9912-3456-7893');
        $this->assertSame('603799', $r->details()['bin']);
        $this->assertSame('6037991234567893', $r->details()['normalized']);
        $this->assertNull(BankCard::validate('4539148803436467')->details()['bank_name']);
    }

    public function test_mobile_normalisation_requires_exact_lengths(): void
    {
        $this->assertSame('09121234567', Mobile::normalize('00989121234567'));
        $this->assertSame('09121234567', Mobile::normalize('989121234567'));
        $this->assertSame('00981234567', Mobile::normalize('00981234567'));
        $this->assertSame('98123', Mobile::normalize('98123'));
        $this->assertSame('12345678901234', Mobile::normalize('12345678901234'));
        $this->assertSame('123456789012', Mobile::normalize('123456789012'));
    }

    public function test_mobile_is_anchored_at_the_start(): void
    {
        $this->assertSame(['invalid_format'], Mobile::validate('109121234567')->errors());
        $this->assertTrue(Mobile::isValid('09091234567'));
        $this->assertTrue(Mobile::isValid('09991234567'));
        $this->assertFalse(Mobile::isValid('09591234567'));
        $this->assertFalse(Mobile::isValid('09891234567'));
    }

    public function test_postal_code_boundaries(): void
    {
        $this->assertTrue(PostalCode::isValid('1234567890'));
        $this->assertTrue(PostalCode::isValid('9999999999'));
        $this->assertSame(['invalid_format'], PostalCode::validate('0123456789')->errors());
        $this->assertSame(['invalid_length'], PostalCode::validate('123456789')->errors());
        $this->assertSame(['invalid_length'], PostalCode::validate('12345678901')->errors());
    }

    public function test_vehicle_plate_zero_parts_are_invalid_region(): void
    {
        $this->assertSame(['invalid_region'], VehiclePlate::validate('00ب34567')->errors());
        $this->assertSame(['invalid_region'], VehiclePlate::validate('12ب00067')->errors());
        $this->assertSame(['invalid_region'], VehiclePlate::validate('12ب34500')->errors());
        $this->assertNull(VehiclePlate::parse('00ب34567'));
        $this->assertNull(VehiclePlate::parse('12ب00067'));
        $this->assertNull(VehiclePlate::parse('12ب34500'));
        $this->assertNotNull(VehiclePlate::parse('10ب10010'));
    }

    public function test_vehicle_plate_details_and_normalisation(): void
    {
        $this->assertSame('12D34567', VehiclePlate::normalize('12d345 67'));
        $this->assertTrue(VehiclePlate::isValid('12d34567'));
        $this->assertTrue(VehiclePlate::isValid("\t12ب34567\n"));
        $this->assertSame('XYZ', VehiclePlate::validate('xyz')->details()['normalized']);
        $this->assertSame(
            ['normalized' => '12ب34567', 'two_digit' => '12', 'letter' => 'ب', 'three_digit' => '345', 'region' => '67'],
            VehiclePlate::validate('12 ب 345 ایران 67')->details(),
        );
    }

    public function test_result_errors_are_reindexed(): void
    {
        $r = Result::invalid([3 => 'a', 7 => 'b']);
        $this->assertSame([0 => 'a', 1 => 'b'], $r->errors());
        $this->assertSame(['a'], Result::invalid('a')->errors());
    }

    public function test_data_table_names_are_validated_strictly(): void
    {
        $this->assertNotEmpty(DataTables::load('national-code-locations'));
        $this->expectException(RtlyKitException::class);
        DataTables::load('../data/national-code-locations');
    }
}
