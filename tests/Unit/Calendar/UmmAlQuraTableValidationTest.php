<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\UmmAlQuraTable;
use RtlyKit\Exceptions\RtlyKitException;

/**
 * Exact accept/reject boundaries of the Umm al-Qura table loader: a year has
 * 348 days plus one per 30-day month, and only 353..355 are plausible.
 */
final class UmmAlQuraTableValidationTest extends TestCase
{
    private const START = 2408762;

    /**
     * @param list<int|string> $months
     *
     * @return array<string, mixed>
     */
    private static function data(int $first, int $last, array $months): array
    {
        return ['first_year' => $first, 'last_year' => $last, 'start_jdn' => self::START, 'months' => $months];
    }

    private function assertRejected(mixed $data, string $messagePattern): void
    {
        try {
            UmmAlQuraTable::fromArray($data);
            self::fail('RtlyKitException expected');
        } catch (RtlyKitException $e) {
            self::assertMatchesRegularExpression($messagePattern, $e->getMessage());
        }
    }

    /* ---------------- year range ---------------- */

    public function test_single_year_tables_at_both_ends_of_the_hijri_range_are_accepted(): void
    {
        $first = UmmAlQuraTable::fromArray(self::data(1, 1, [0x555]));
        self::assertSame([1, 1], [$first->firstYear, $first->lastYear]);
        self::assertSame(self::START, $first->firstJdn());
        self::assertSame(self::START + 354, $first->endJdn());
        self::assertTrue($first->covers(1));
        self::assertFalse($first->covers(2));

        $last = UmmAlQuraTable::fromArray(self::data(Hijri::MAX_YEAR, Hijri::MAX_YEAR, [0x555]));
        self::assertSame([9665, 9665], [$last->firstYear, $last->lastYear]);
        self::assertTrue($last->covers(9665));
        self::assertFalse($last->covers(9664));
    }

    public function test_invalid_year_ranges_are_rejected_with_the_range_in_the_message(): void
    {
        $this->assertRejected(self::data(0, 1, [0x555, 0x555]), '/^Umm al-Qura table: invalid year range 0\.\.1\.$/');
        $this->assertRejected(self::data(1301, 1300, [0x555]), '/^Umm al-Qura table: invalid year range 1301\.\.1300\.$/');
        $this->assertRejected(self::data(9665, 9666, [0x555, 0x555]), '/^Umm al-Qura table: invalid year range 9665\.\.9666\.$/');
    }

    public function test_year_count_must_match_the_range(): void
    {
        $this->assertRejected(self::data(1300, 1301, [0x555]), '/^Umm al-Qura table: expected 2 year entries, found 1\.$/');
        $this->assertRejected(self::data(1300, 1300, [0x555, 0x555]), '/^Umm al-Qura table: expected 1 year entries, found 2\.$/');
        $this->assertRejected(self::data(1300, 1302, [0x555, 0x555]), '/^Umm al-Qura table: expected 3 year entries, found 2\.$/');
    }

    /* ---------------- structure ---------------- */

    /** @return array<string, array{mixed}> */
    public static function malformedStructure(): array
    {
        $ok = self::data(1300, 1301, [0x555, 0x2AB]);

        return [
            'first_year is a string' => [['first_year' => '1300'] + $ok],
            'last_year is a string' => [['last_year' => '1301'] + $ok],
            'start_jdn is a string' => [['start_jdn' => '2408762'] + $ok],
            'first_year is a float' => [['first_year' => 1300.0] + $ok],
            'months is not an array' => [['months' => 'x'] + $ok],
            'months has string keys' => [['months' => ['a' => 0x555, 'b' => 0x2AB]] + $ok],
            'months does not start at 0' => [['months' => [1 => 0x555, 2 => 0x2AB]] + $ok],
            'first_year missing' => [array_diff_key($ok, ['first_year' => 1])],
            'last_year missing' => [array_diff_key($ok, ['last_year' => 1])],
            'start_jdn missing' => [array_diff_key($ok, ['start_jdn' => 1])],
            'months missing' => [array_diff_key($ok, ['months' => 1])],
            'null' => [null],
            'a list instead of a map' => [[1300, 1301, self::START, [0x555, 0x2AB]]],
        ];
    }

    #[DataProvider('malformedStructure')]
    public function test_malformed_structures_are_rejected(mixed $data): void
    {
        $this->assertRejected($data, '/^Umm al-Qura table: malformed structure\.$/');
    }

    /* ---------------- month bitmasks ---------------- */

    /** @return array<string, array{int|string, string}> */
    public static function invalidBitmasks(): array
    {
        return [
            'above 12 bits' => [0x1000, '/^Umm al-Qura table: invalid month bitmask for AH 1301\.$/'],
            'negative' => [-1, '/^Umm al-Qura table: invalid month bitmask for AH 1301\.$/'],
            'not an integer' => ['2AB', '/^Umm al-Qura table: invalid month bitmask for AH 1301\.$/'],
            'no month of 30 days: 348 days' => [0x000, '/^Umm al-Qura table: AH 1301 has 348 days\.$/'],
            'four 30-day months: 352 days' => [0x00F, '/^Umm al-Qura table: AH 1301 has 352 days\.$/'],
            'eight 30-day months: 356 days' => [0x0FF, '/^Umm al-Qura table: AH 1301 has 356 days\.$/'],
            'all months of 30 days: 360 days' => [0xFFF, '/^Umm al-Qura table: AH 1301 has 360 days\.$/'],
        ];
    }

    #[DataProvider('invalidBitmasks')]
    public function test_invalid_bitmasks_name_the_year(int|string $bits, string $messagePattern): void
    {
        $this->assertRejected(self::data(1300, 1301, [0x555, $bits]), $messagePattern);
    }

    public function test_year_lengths_353_354_and_355_are_accepted(): void
    {
        // Five, six and seven months of 30 days.
        $table = UmmAlQuraTable::fromArray(self::data(1300, 1302, [0x01F, 0x03F, 0x07F]));

        self::assertSame(self::START, $table->yearStart(1300));
        self::assertSame(self::START + 353, $table->yearStart(1301));
        self::assertSame(self::START + 353 + 354, $table->yearStart(1302));
        self::assertSame(self::START + 353 + 354 + 355, $table->endJdn());
    }

    public function test_the_highest_valid_bitmask_bit_is_dhu_al_hijjah(): void
    {
        // Bits 6..11 set: six 30-day months at the end of the year.
        $table = UmmAlQuraTable::fromArray(self::data(1300, 1300, [0xFC0]));

        self::assertSame(0xFC0, $table->bits(1300));
        for ($month = 1; $month <= 6; $month++) {
            self::assertSame(29, $table->monthLength(1300, $month), "month {$month}");
        }
        for ($month = 7; $month <= 12; $month++) {
            self::assertSame(30, $table->monthLength(1300, $month), "month {$month}");
        }
    }

    /* ---------------- files ---------------- */

    public function test_a_valid_data_file_is_loaded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'uq');
        self::assertNotFalse($path);
        file_put_contents($path, "<?php\nreturn ['first_year' => 1300, 'last_year' => 1301, 'start_jdn' => ".self::START.", 'months' => [0x555, 0x2AB]];\n");

        try {
            $table = UmmAlQuraTable::fromFile($path);
        } finally {
            unlink($path);
        }

        self::assertSame(1300, $table->firstYear);
        self::assertSame(1301, $table->lastYear);
        self::assertSame(self::START, $table->firstJdn());
        self::assertSame(self::START + 708, $table->endJdn());
    }

    public function test_a_missing_file_is_reported_with_its_path(): void
    {
        $path = sys_get_temp_dir().'/rtly-kit-no-such-table-'.bin2hex(random_bytes(4)).'.php';

        try {
            UmmAlQuraTable::fromFile($path);
            self::fail('RtlyKitException expected');
        } catch (RtlyKitException $e) {
            self::assertSame("Umm al-Qura data file not found or unreadable: {$path}", $e->getMessage());
        }
    }

    public function test_a_directory_is_not_a_data_file(): void
    {
        $this->expectException(RtlyKitException::class);
        $this->expectExceptionMessage('Umm al-Qura data file not found or unreadable');

        UmmAlQuraTable::fromFile(sys_get_temp_dir());
    }
}
