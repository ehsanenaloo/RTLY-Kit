<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\DataTables;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\Sheba;

/**
 * Integrity of the data files in resources/data (counts are part of the
 * documented data provenance and must not change silently).
 */
final class DataTablesTest extends TestCase
{
    public function test_bank_bins_table(): void
    {
        $table = DataTables::load('bank-bins');

        self::assertCount(39, $table);

        foreach ($table as $bin => $name) {
            self::assertIsInt($bin);
            self::assertMatchesRegularExpression('/^[1-9]\d{5}$/', (string) $bin, 'BIN must be 6 digits');
            self::assertIsString($name);
            self::assertNotSame('', trim($name));
        }

        // Known answers.
        self::assertSame('بانک ملی ایران', $table[603799]);
        self::assertSame('بانک ملت', $table[610433]);
        self::assertSame('بانک ملت', $table[991975]);
    }

    public function test_sheba_bank_table(): void
    {
        $table = DataTables::load('sheba-banks');

        self::assertCount(38, $table);

        foreach ($table as $code => $name) {
            self::assertIsString($code, 'keys keep their leading zeros, so they are strings');
            self::assertMatchesRegularExpression('/^\d{3}$/', $code);
            self::assertIsString($name);
            self::assertNotSame('', trim($name));
        }

        self::assertSame('بانک ملت', $table['012']);
        self::assertSame('بانک مرکزی', $table['010']);
        self::assertSame('بانک قرض‌الحسنه مهر ایران', $table['090']);
    }

    public function test_national_code_location_table(): void
    {
        $table = DataTables::load('national-code-locations');

        self::assertCount(547, $table);

        foreach ($table as $prefix => $location) {
            self::assertIsInt($prefix);
            self::assertGreaterThanOrEqual(1, $prefix);
            self::assertLessThanOrEqual(999, $prefix);
            self::assertIsArray($location);
            self::assertSame(['province', 'city'], array_keys($location));
            self::assertNotSame('', trim((string) $location['province']));
            self::assertNotSame('', trim((string) $location['city']));
        }

        self::assertSame(['province' => 'قم', 'city' => 'قم'], $table[37]);
        self::assertSame('تهران', $table[1]['province']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function files(): array
    {
        return [
            'bins' => ['bank-bins', '/^\s+(\d+) =>/m'],
            'sheba' => ['sheba-banks', "/^\\s+'(\\d+)' =>/m"],
            'locations' => ['national-code-locations', '/^\s+(\d+) =>/m'],
        ];
    }

    #[DataProvider('files')]
    public function test_no_key_is_declared_twice_in_the_source_file(string $name, string $keyPattern): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/resources/data/' . $name . '.php');
        self::assertIsString($source);

        preg_match_all($keyPattern, $source, $m);

        self::assertNotSame([], $m[1]);
        self::assertSame(count($m[1]), count(array_unique($m[1])), 'duplicate key in ' . $name);
        self::assertCount(count($m[1]), DataTables::load($name));
    }

    public function test_tables_are_cached_between_calls(): void
    {
        self::assertSame(DataTables::load('bank-bins'), DataTables::load('bank-bins'));
    }

    public function test_unknown_or_malformed_table_names_raise_a_library_exception(): void
    {
        foreach (['does-not-exist', '../composer', '', 'BANK-BINS', "bank-bins\0"] as $name) {
            try {
                DataTables::load($name);
                self::fail('Expected an exception for ' . json_encode($name));
            } catch (RtlyKitException $e) {
                self::assertSame(ErrorCode::DataUnavailable, $e->getErrorCode());
                self::assertSame(['table' => $name], $e->getContext());
            }
        }
    }

    public function test_validators_resolve_names_from_the_tables(): void
    {
        self::assertSame('بانک ملی ایران', BankCard::getBankName('6037991899071116'));
        self::assertSame('بانک ملت', Sheba::validate('IR120120000000002345678901')->details()['bank_name']);
        self::assertSame('تهران', NationalCode::getLocation('0499370899')['province'] ?? null);
    }
}
