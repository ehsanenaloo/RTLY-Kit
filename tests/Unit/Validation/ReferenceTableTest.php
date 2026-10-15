<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\ReferenceTable;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\TableKind;

final class ReferenceTableTest extends TestCase
{
    private static function cardWithBin(string $bin): string
    {
        $first15 = $bin.'123456789';
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $d = (int) $first15[$i];
            if ($i % 2 === 0) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return $first15.((10 - $sum % 10) % 10);
    }

    public function test_the_bundled_tables(): void
    {
        $bins = ReferenceTable::bankBins();
        $sheba = ReferenceTable::shebaBanks();
        $places = ReferenceTable::nationalCodeLocations();

        self::assertSame(TableKind::BankBin, $bins->kind);
        self::assertSame(TableKind::ShebaBank, $sheba->kind);
        self::assertSame(TableKind::NationalCodePrefix, $places->kind);
        self::assertCount(39, $bins);
        self::assertCount(38, $sheba);
        self::assertCount(547, $places);
        self::assertSame('بانک ملی ایران', $bins->get(603799));
        self::assertSame('بانک مرکزی', $sheba->get('010'));
        self::assertSame('بانک مرکزی', $sheba->get(10));
        self::assertSame(['province' => 'تهران', 'city' => 'تهران مرکزی'], $places->get(1));
        self::assertSame(['province' => 'تهران', 'city' => 'تهران مرکزی'], $places->get('001'));
        self::assertNull($bins->get(504172 + 1));
    }

    public function test_changes_return_new_tables_and_leave_the_old_ones_alone(): void
    {
        $base = ReferenceTable::bankBins();
        $more = $base->with(400000, 'بانک آزمایشی');
        $fewer = $more->without(603799);

        self::assertNull($base->get(400000));
        self::assertSame('بانک آزمایشی', $more->get(400000));
        self::assertSame('بانک ملی ایران', $more->get(603799));
        self::assertNull($fewer->get(603799));
        self::assertSame('بانک آزمایشی', $fewer->get(400000));
        self::assertCount(39, $base);
        self::assertCount(40, $more);
        self::assertCount(39, $fewer);
        self::assertNotSame($base, $more);
        // The bundled table is unchanged for every other caller.
        self::assertSame('بانک ملی ایران', ReferenceTable::bankBins()->get(603799));
        self::assertNull(ReferenceTable::bankBins()->get(400000));
    }

    public function test_with_replaces_an_existing_entry_and_keeps_the_keys_sorted(): void
    {
        $table = ReferenceTable::bankBins()->with(603799, 'نام دیگر')->with(100000, 'اول');

        self::assertSame('نام دیگر', $table->get(603799));
        self::assertCount(40, $table);
        self::assertSame(100000, array_key_first($table->all()));
        $keys = array_keys($table->all());
        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys);
    }

    /** @return iterable<string, array{TableKind, int|string, int|string}> */
    public static function equivalentKeys(): iterable
    {
        yield 'BIN as text' => [TableKind::BankBin, '603799', 603799];
        yield 'BIN with Persian digits' => [TableKind::BankBin, '۶۰۳۷۹۹', 603799];
        yield 'BIN with blanks' => [TableKind::BankBin, ' 603799 ', 603799];
        yield 'Sheba code from an int' => [TableKind::ShebaBank, 17, '017'];
        yield 'Sheba code with Persian digits' => [TableKind::ShebaBank, '۰۱۷', '017'];
        yield 'prefix as text' => [TableKind::NationalCodePrefix, '001', 1];
        yield 'prefix with Persian digits' => [TableKind::NationalCodePrefix, '۰۰۱', 1];
    }

    #[DataProvider('equivalentKeys')]
    public function test_keys_are_normalised(TableKind $kind, int|string $given, int|string $stored): void
    {
        $value = $kind === TableKind::NationalCodePrefix ? ['province' => 'الف', 'city' => 'ب'] : 'نام';
        $table = ReferenceTable::from($kind, [$given => $value]);

        self::assertSame([$stored], array_keys($table->all()));
        self::assertTrue($table->has($given));
        self::assertTrue($table->has($stored));
        self::assertSame($value, $table->get($given));
        self::assertCount(0, $table->without($given));
    }

    /** @return iterable<string, array{TableKind, int|string}> */
    public static function impossibleKeys(): iterable
    {
        yield 'BIN of 5 digits' => [TableKind::BankBin, '60379'];
        yield 'BIN of 7 digits' => [TableKind::BankBin, '6037990'];
        yield 'BIN starting with 0' => [TableKind::BankBin, '012345'];
        yield 'BIN with letters' => [TableKind::BankBin, 'abcdef'];
        yield 'negative BIN' => [TableKind::BankBin, -603799];
        yield 'int below 6 digits' => [TableKind::BankBin, 99999];
        yield 'Sheba code of 2 digits' => [TableKind::ShebaBank, '17'];
        yield 'Sheba code of 4 digits' => [TableKind::ShebaBank, '0170'];
        yield 'Sheba code above 999' => [TableKind::ShebaBank, 1000];
        yield 'negative Sheba code' => [TableKind::ShebaBank, -1];
        yield 'prefix zero' => [TableKind::NationalCodePrefix, 0];
        yield 'prefix 000' => [TableKind::NationalCodePrefix, '000'];
        yield 'prefix 1000' => [TableKind::NationalCodePrefix, 1000];
        yield 'prefix of 1 digit as text' => [TableKind::NationalCodePrefix, '1'];
    }

    #[DataProvider('impossibleKeys')]
    public function test_a_lookup_of_an_impossible_key_is_empty_but_an_edit_is_an_error(TableKind $kind, int|string $key): void
    {
        $table = ReferenceTable::from($kind, []);
        $value = $kind === TableKind::NationalCodePrefix ? ['province' => 'الف', 'city' => 'ب'] : 'نام';

        self::assertNull($table->get($key));
        self::assertFalse($table->has($key));
        foreach ([fn () => $table->with($key, $value), fn () => $table->without($key)] as $edit) {
            try {
                $edit();
                self::fail('Expected an exception.');
            } catch (RtlyKitException $e) {
                self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
                self::assertSame(['table' => $kind->value], $e->getContext());
            }
        }
    }

    public function test_removing_a_missing_key_is_an_error(): void
    {
        $this->expectException(RtlyKitException::class);
        $this->expectExceptionMessage('The key is not in the table: 400000');
        ReferenceTable::bankBins()->without(400000);
    }

    /** @return iterable<string, array{TableKind, mixed}> */
    public static function badValues(): iterable
    {
        yield 'int as a bank name' => [TableKind::BankBin, 5];
        yield 'empty name' => [TableKind::ShebaBank, ''];
        yield 'blank name' => [TableKind::BankBin, '   '];
        yield 'long name' => [TableKind::BankBin, str_repeat('a', 101)];
        yield 'long Persian name' => [TableKind::BankBin, str_repeat('ب', 101)];
        yield 'control character' => [TableKind::ShebaBank, "a\nb"];
        yield 'invalid UTF-8' => [TableKind::BankBin, "a\xff"];
        yield 'a place as text' => [TableKind::NationalCodePrefix, 'تهران'];
        yield 'a place without a city' => [TableKind::NationalCodePrefix, ['province' => 'تهران']];
        yield 'a place with an extra key' => [TableKind::NationalCodePrefix, ['province' => 'a', 'city' => 'b', 'x' => 'c']];
        yield 'a place in the wrong order' => [TableKind::NationalCodePrefix, ['city' => 'b', 'province' => 'a']];
        yield 'a place with a number' => [TableKind::NationalCodePrefix, ['province' => 'a', 'city' => 5]];
        yield 'a place with an empty city' => [TableKind::NationalCodePrefix, ['province' => 'a', 'city' => '']];
        yield 'a place with a long province' => [TableKind::NationalCodePrefix, ['province' => str_repeat('a', 101), 'city' => 'b']];
    }

    #[DataProvider('badValues')]
    public function test_bad_values_are_rejected(TableKind $kind, mixed $value): void
    {
        $key = match ($kind) {
            TableKind::BankBin => 400000,
            TableKind::ShebaBank => '999',
            TableKind::NationalCodePrefix => 999,
        };

        $this->expectException(RtlyKitException::class);
        ReferenceTable::from($kind, [])->with($key, $value);
    }

    public function test_values_at_the_limit_are_accepted(): void
    {
        $name = str_repeat('ب', 100);

        self::assertSame($name, ReferenceTable::from(TableKind::BankBin, [400000 => $name])->get(400000));
        self::assertSame(['province' => $name, 'city' => $name], ReferenceTable::from(TableKind::NationalCodePrefix, [999 => ['province' => $name, 'city' => $name]])->get(999));
    }

    public function test_from_builds_a_table_with_only_the_given_entries(): void
    {
        $table = ReferenceTable::from(TableKind::ShebaBank, ['017' => 'الف', 62 => 'ب']);

        self::assertSame(['017' => 'الف', '062' => 'ب'], $table->all());
        self::assertCount(2, $table);
        $this->expectException(RtlyKitException::class);
        ReferenceTable::from(TableKind::ShebaBank, ['0170' => 'الف']);
    }

    public function test_error_messages_stay_short(): void
    {
        try {
            ReferenceTable::bankBins()->with(str_repeat('9', 5000), 'a');
            self::fail('Expected an exception.');
        } catch (RtlyKitException $e) {
            self::assertLessThan(120, strlen($e->getMessage()));
        }
    }

    public function test_the_bank_card_validator_uses_a_given_table_for_the_name_only(): void
    {
        $card = self::cardWithBin('400000');
        $custom = ReferenceTable::bankBins()->with(400000, 'بانک آزمایشی');

        self::assertNull(BankCard::getBankName($card));
        self::assertTrue(BankCard::isValid($card));
        self::assertSame('بانک آزمایشی', BankCard::getBankName($card, $custom));
        self::assertSame('بانک آزمایشی', BankCard::validate($card, $custom)->details()['bank_name']);

        // A built-in BIN can be removed from a table; the card is still valid.
        $without = ReferenceTable::bankBins()->without(603799);
        self::assertNull(BankCard::getBankName('6037991899071116', $without));
        self::assertSame('بانک ملی ایران', BankCard::getBankName('6037991899071116'));
        self::assertTrue(BankCard::validate('6037991899071116', $without)->isValid());

        // Validity never depends on the table: a wrong checksum stays wrong.
        $bad = substr($card, 0, 15).(((int) $card[15] + 1) % 10);
        self::assertSame(['invalid_checksum'], BankCard::validate($bad, $custom)->errors());
        self::assertSame('بانک آزمایشی', BankCard::validate($bad, $custom)->details()['bank_name']);
    }

    public function test_the_sheba_validator_uses_a_given_table_for_the_name_only(): void
    {
        $sheba = 'IR270170000000100324200001';
        $custom = ReferenceTable::shebaBanks()->with('017', 'بانک دیگر');

        self::assertSame('بانک دیگر', Sheba::getBankName($sheba, $custom));
        self::assertSame('بانک دیگر', Sheba::validate($sheba, $custom)->details()['bank_name']);
        self::assertNotSame('بانک دیگر', Sheba::getBankName($sheba));
        self::assertNull(Sheba::getBankName($sheba, ReferenceTable::shebaBanks()->without('017')));
        self::assertTrue(Sheba::validate($sheba, ReferenceTable::shebaBanks()->without('017'))->isValid());
    }

    public function test_the_national_code_validator_uses_a_given_table_for_the_place_only(): void
    {
        $custom = ReferenceTable::nationalCodeLocations()->with(1, ['province' => 'استان آزمایشی', 'city' => 'شهر آزمایشی']);

        self::assertSame(['province' => 'استان آزمایشی', 'city' => 'شهر آزمایشی'], NationalCode::getLocation('0013542419', $custom));
        self::assertSame(['province' => 'تهران', 'city' => 'تهران مرکزی'], NationalCode::getLocation('0013542419'));
        self::assertNull(NationalCode::getLocation('0013542419', ReferenceTable::nationalCodeLocations()->without(1)));
        self::assertTrue(NationalCode::validate('0013542419', ReferenceTable::nationalCodeLocations()->without(1))->isValid());
        self::assertSame(['invalid_checksum'], NationalCode::validate('0013542418', $custom)->errors());
    }

    public function test_a_table_of_the_wrong_kind_is_a_programming_error(): void
    {
        foreach ([
            fn () => BankCard::validate('6037991899071116', ReferenceTable::shebaBanks()),
            fn () => Sheba::validate('IR270170000000100324200001', ReferenceTable::bankBins()),
            fn () => NationalCode::validate('0013542419', ReferenceTable::bankBins()),
            fn () => BankCard::getBankName('6037991899071116', ReferenceTable::nationalCodeLocations()),
        ] as $call) {
            try {
                $call();
                self::fail('Expected an exception.');
            } catch (RtlyKitException $e) {
                self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
                self::assertArrayHasKey('table', $e->getContext());
            }
        }
    }
}
