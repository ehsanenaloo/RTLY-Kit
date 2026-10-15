<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Prayer\CalculationMethod;
use RtlyKit\Validation\ReferenceTable;
use RtlyKit\Validation\TableKind;

/** Exact messages and edge keys of CalculationMethod and ReferenceTable. */
final class NewApiContractTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function methodMessages(): iterable
    {
        yield 'empty name' => [['name' => ''], 'The method name must be 1 to 64 bytes of text without control characters'];
        yield 'long source' => [['source' => str_repeat('a', 301)], 'The method source must be valid text of at most 300 bytes'];
        yield 'fajr below' => [['fajrAngle' => 0.5], 'The calculation method option fajr_angle must be a number from 1 and at most 30'];
        yield 'fajr above' => [['fajrAngle' => 31.0], 'The calculation method option fajr_angle must be a number from 1 and at most 30'];
        yield 'isha angle' => [['ishaAngle' => 31.0], 'The calculation method option isha_angle must be a number from 1 and at most 30'];
        yield 'isha minutes' => [['ishaAngle' => null, 'ishaMinutes' => 0.0], 'The calculation method option isha_minutes must be a number above 0 and at most 240'];
        yield 'maghrib angle' => [['maghribAngle' => 11.0], 'The calculation method option maghrib_angle must be a number above 0 and at most 10'];
        yield 'ramadan minutes' => [['ishaAngle' => null, 'ishaMinutes' => 90.0, 'ramadanIshaMinutes' => 241.0], 'The calculation method option ramadan_isha_minutes must be a number above 0 and at most 240'];
        yield 'both isha forms' => [['ishaMinutes' => 90.0], 'Give exactly one of isha_angle and isha_minutes'];
        yield 'ramadan without minutes' => [['ramadanIshaMinutes' => 120.0], 'ramadan_isha_minutes needs isha_minutes'];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('methodMessages')]
    public function test_method_messages(array $override, string $message): void
    {
        $arguments = array_merge(['name' => 'X', 'fajrAngle' => 18.0, 'ishaAngle' => 17.0], $override);

        try {
            new CalculationMethod(...$arguments);
            self::fail('Expected an InvalidPrayerConfigException.');
        } catch (InvalidPrayerConfigException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }

    public function test_an_explicit_null_for_a_required_number_is_an_error(): void
    {
        $this->expectException(InvalidPrayerConfigException::class);
        $this->expectExceptionMessage('fajr_angle must be a number from 1 and at most 30');
        CalculationMethod::fromArray(['name' => 'a', 'fajr_angle' => null, 'isha_angle' => 17]);
    }

    public function test_the_edge_keys_of_a_table(): void
    {
        $sheba = ReferenceTable::from(TableKind::ShebaBank, [0 => 'zero', 999 => 'top']);
        self::assertSame(['000' => 'zero', '999' => 'top'], $sheba->all());
        self::assertSame('zero', $sheba->get(0));
        self::assertSame('top', $sheba->get(999));
        self::assertNull($sheba->get(1000));

        $places = ReferenceTable::from(TableKind::NationalCodePrefix, [1 => ['province' => 'a', 'city' => 'b'], 999 => ['province' => 'c', 'city' => 'd']]);
        self::assertNull($places->get('1001'), 'a longer text does not match a prefix inside it');
        self::assertFalse($places->has('1001'));
        self::assertNull($places->get('0010'), 'a longer text does not match a prefix at its start');
        self::assertFalse($places->has('0010'));
        self::assertSame(['province' => 'c', 'city' => 'd'], $places->get(999));
        self::assertNull($places->get(1000));

        $bins = ReferenceTable::from(TableKind::BankBin, [100000 => 'low', 999999 => 'high']);
        self::assertSame('low', $bins->get('100000'));
        self::assertSame('high', $bins->get(999999));
        self::assertNull($bins->get('1000000'));
        self::assertNull($bins->get('0100000'));
        self::assertFalse($bins->has('x100000'));
    }

    public function test_value_errors_say_what_is_wrong_and_which_key(): void
    {
        try {
            ReferenceTable::from(TableKind::BankBin, [])->with(400000, '');
            self::fail('Expected an exception.');
        } catch (RtlyKitException $e) {
            self::assertSame('The name must be 1 to 100 characters of text without control characters: 400000', $e->getMessage());
        }

        $long = '1234567890123456789012345';
        try {
            ReferenceTable::from(TableKind::BankBin, [])->with($long, 'a');
            self::fail('Expected an exception.');
        } catch (RtlyKitException $e) {
            self::assertSame('A BIN is 6 digits and does not start with 0: 12345678901234567890...', $e->getMessage());
        }
    }

    public function test_the_other_kinds_name_their_key_rule(): void
    {
        foreach ([
            [TableKind::ShebaBank, '17x', 'A Sheba bank code is 3 digits, for example "017": 17x'],
            [TableKind::NationalCodePrefix, 0, 'A national-code prefix is 1 to 999 (3 digits as text): 0'],
        ] as [$kind, $key, $message]) {
            try {
                ReferenceTable::from($kind, [])->without($key);
                self::fail('Expected an exception.');
            } catch (RtlyKitException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }
}
