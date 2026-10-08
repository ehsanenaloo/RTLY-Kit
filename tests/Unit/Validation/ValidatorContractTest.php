<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Contracts\Validator;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\Mobile;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\PostalCode;
use RtlyKit\Validation\Result;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;

final class ValidatorContractTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<Validator>, 1: string, 2: string}>
     */
    public static function validators(): array
    {
        return [
            'national code' => [NationalCode::class, '0499370899', 'invalid_checksum'],
            'sheba' => [Sheba::class, 'IR062960000000100324200001', 'invalid_checksum'],
            'bank card' => [BankCard::class, '6037991899071116', 'invalid_checksum'],
            'mobile' => [Mobile::class, '09123456789', 'invalid_format'],
            'postal code' => [PostalCode::class, '1593715416', 'invalid_length'],
            'vehicle plate' => [VehiclePlate::class, '12ب345-67', 'invalid_format'],
        ];
    }

    /**
     * @param class-string<Validator> $class
     */
    #[DataProvider('validators')]
    public function test_validator_implements_the_contract_and_is_callable_by_class_string(string $class, string $good, string $badCode): void
    {
        self::assertInstanceOf(Validator::class, new class () implements Validator {
            public static function validate(mixed $value): Result
            {
                return Result::valid();
            }

            public static function isValid(mixed $value): bool
            {
                return true;
            }
        });
        self::assertContains(Validator::class, class_implements($class));

        $ok = $class::validate($good);
        self::assertTrue($ok->isValid());
        self::assertTrue($class::isValid($good));
        self::assertSame([], $ok->errors());
    }

    /**
     * @param class-string<Validator> $class
     */
    #[DataProvider('validators')]
    public function test_non_scalar_values_are_rejected_as_invalid_type(string $class, string $good, string $badCode): void
    {
        foreach ([null, true, false, [], [$good], new \stdClass(), NAN, INF, -INF, 1.5] as $value) {
            $result = $class::validate($value);

            self::assertFalse($result->isValid(), get_debug_type($value));
            self::assertSame(['invalid_type'], $result->errors(), get_debug_type($value));
            self::assertFalse($class::isValid($value));
            // The detail keys of a normal result are still present.
            self::assertSame('', $result->details()['normalized']);
        }
    }

    /**
     * @param class-string<Validator> $class
     */
    #[DataProvider('validators')]
    public function test_strings_over_the_cap_are_rejected_cheaply(string $class, string $good, string $badCode): void
    {
        $result = $class::validate(str_repeat('1', 4097));
        self::assertSame(['input_too_long'], $result->errors());

        $exactlyAtCap = $class::validate(str_repeat('1', 4096));
        self::assertNotContains('input_too_long', $exactlyAtCap->errors());
    }

    /**
     * @param class-string<Validator> $class
     */
    #[DataProvider('validators')]
    public function test_invalid_utf8_and_nul_bytes_do_not_throw(string $class, string $good, string $badCode): void
    {
        foreach (["\xff\xfe", "\0", "\xC0\xAF", "\xED\xA0\x80"] as $value) {
            self::assertFalse($class::validate($value)->isValid());
        }

        // Stray bytes around a valid value are tolerated or rejected, but never throw.
        foreach ([$good . "\0", "\xC0\xAF" . $good, "\xff" . $good . "\xfe"] as $value) {
            self::assertInstanceOf(Result::class, $class::validate($value));
        }
    }

    public function test_integers_and_integral_floats_are_accepted_as_numbers(): void
    {
        self::assertTrue(Mobile::isValid(9123456789));
        self::assertTrue(Mobile::isValid(989123456789));
        self::assertTrue(PostalCode::isValid(1593715416));
        self::assertTrue(PostalCode::isValid(1593715416.0));
        self::assertTrue(BankCard::isValid(6037991899071116));
        self::assertSame('0499370899', NationalCode::normalize('0499370899'));
    }

    public function test_leading_zero_loss_in_integers_is_not_repaired(): void
    {
        // 499370899 has 9 digits: no silent zero-padding.
        self::assertSame(['invalid_format'], NationalCode::validate(499370899)->errors());
    }

    public function test_existing_string_behaviour_is_unchanged(): void
    {
        self::assertSame('تهران', NationalCode::getLocation('0499370899')['province'] ?? null);
        self::assertSame('بانک ملی ایران', BankCard::getBankName('6037991899071116'));
        self::assertNull(BankCard::getBankName(null));
        self::assertNull(Sheba::getBankName([]));
        self::assertNull(Mobile::getOperator(new \stdClass()));
        self::assertNull(NationalCode::getLocation(NAN));
        self::assertSame('همراه اول', Mobile::getOperator('09123456789'));
    }
}
