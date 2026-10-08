<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jdn;
use RtlyKit\Calendar\UmmAlQuraTable;
use RtlyKit\Exceptions\RtlyKitException;

/**
 * Integrity of resources/data/umm-al-qura.php and of its loader.
 */
final class UmmAlQuraTableTest extends TestCase
{
    public function test_bundled_table_covers_ah_1300_to_1500_and_is_cached(): void
    {
        $table = UmmAlQuraTable::default();

        $this->assertSame(1300, $table->firstYear);
        $this->assertSame(1500, $table->lastYear);
        $this->assertSame($table, UmmAlQuraTable::default());
        $this->assertTrue($table->covers(1300));
        $this->assertTrue($table->covers(1500));
        $this->assertFalse($table->covers(1299));
        $this->assertFalse($table->covers(1501));
    }

    public function test_bundled_table_spans_the_documented_gregorian_range(): void
    {
        $table = UmmAlQuraTable::default();

        $this->assertSame(Jdn::fromGregorian(1882, 11, 12), $table->firstJdn()); // 1 Muharram 1300
        $this->assertSame(Jdn::fromGregorian(2077, 11, 17), $table->endJdn());   // day after 29/30 Dhu al-Hijjah 1500
    }

    public function test_year_starts_increase_strictly_and_years_have_354_or_355_days(): void
    {
        $table = UmmAlQuraTable::default();

        for ($year = $table->firstYear; $year <= $table->lastYear; $year++) {
            $length = ($year === $table->lastYear ? $table->endJdn() : $table->yearStart($year + 1)) - $table->yearStart($year);
            $this->assertContains($length, [354, 355], "AH {$year}");
            $this->assertLessThan(1 << 12, $table->bits($year), "AH {$year}");
        }
    }

    public function test_month_lengths_are_29_or_30_and_sum_to_the_year_length(): void
    {
        $table = UmmAlQuraTable::default();

        foreach ([1300, 1446, 1447, 1500] as $year) {
            $sum = 0;
            for ($m = 1; $m <= 12; $m++) {
                $len = $table->monthLength($year, $m);
                $this->assertContains($len, [29, 30]);
                $sum += $len;
            }
            $this->assertGreaterThanOrEqual(354, $sum, "AH {$year}");
            $this->assertLessThanOrEqual(355, $sum, "AH {$year}");
        }
    }

    /** Anchors documented in Hijri: 1 Ramadan 1446 = 2025-03-01, 1 Muharram 1447 = 2025-06-26. */
    public function test_known_anchor_dates(): void
    {
        $table = UmmAlQuraTable::default();

        $ramadan1446 = $table->yearStart(1446);
        foreach (range(1, 8) as $m) {
            $ramadan1446 += $table->monthLength(1446, $m);
        }
        $this->assertSame(Jdn::fromGregorian(2025, 3, 1), $ramadan1446);
        $this->assertSame(Jdn::fromGregorian(2025, 6, 26), $table->yearStart(1447));
    }

    public function test_hijri_conversion_is_unchanged_by_the_extraction(): void
    {
        $this->assertSame([2025, 3, 1], Hijri::hijriToGregorian(1446, 9, 1));
        $this->assertSame([1446, 9, 1], Hijri::gregorianToHijri(2025, 3, 1));
        $this->assertSame([2024, 4, 10], Hijri::hijriToGregorian(1445, 10, 1));
        // Every table day maps back to itself.
        $table = UmmAlQuraTable::default();
        for ($jdn = $table->firstJdn(); $jdn < $table->endJdn(); $jdn += 13) {
            [$gy, $gm, $gd] = Jdn::toGregorian($jdn);
            [$hy, $hm, $hd] = Hijri::gregorianToHijri($gy, $gm, $gd);
            $this->assertSame([$gy, $gm, $gd], Hijri::hijriToGregorian($hy, $hm, $hd));
        }
    }

    /** @return array<string, array{mixed}> */
    public static function malformed(): array
    {
        $ok = ['first_year' => 1300, 'last_year' => 1301, 'start_jdn' => 2408762, 'months' => [0x555, 0x2AB]];

        return [
            'not an array'       => ['nope'],
            'missing key'        => [['first_year' => 1300, 'last_year' => 1301, 'months' => [0x555, 0x2AB]]],
            'non-int year'       => [['first_year' => '1300'] + $ok],
            'months not a list'  => [['months' => ['a' => 0x555, 'b' => 0x2AB]] + $ok],
            'year below 1'       => [['first_year' => 0, 'last_year' => 1] + $ok],
            'last before first'  => [['last_year' => 1299] + $ok],
            'last above max'     => [['last_year' => Hijri::MAX_YEAR + 1] + $ok],
            'count mismatch'     => [['months' => [0x555]] + $ok],
            'bitmask too large'  => [['months' => [0x555, 0x1000]] + $ok],
            'bitmask negative'   => [['months' => [0x555, -1]] + $ok],
            'bitmask not int'    => [['months' => [0x555, '2AB']] + $ok],
            'year too short'     => [['months' => [0x555, 0x000]] + $ok],  // 348 days
            'year too long'      => [['months' => [0x555, 0xFFF]] + $ok],  // 360 days
        ];
    }

    #[DataProvider('malformed')]
    public function test_integrity_checks_reject_bad_data(mixed $data): void
    {
        $this->expectException(RtlyKitException::class);
        UmmAlQuraTable::fromArray($data);
    }

    public function test_valid_minimal_table_is_accepted(): void
    {
        $table = UmmAlQuraTable::fromArray(
            ['first_year' => 1300, 'last_year' => 1301, 'start_jdn' => 2408762, 'months' => [0x555, 0x2AB]],
        );

        $this->assertSame(2408762, $table->firstJdn());
        $this->assertSame(2408762 + 354, $table->yearStart(1301));
        $this->assertSame(2408762 + 354 + 354, $table->endJdn());
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(RtlyKitException::class);
        UmmAlQuraTable::fromFile(__DIR__.'/does-not-exist.php');
    }

    public function test_corrupt_file_throws(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'uq');
        $this->assertNotFalse($path);
        file_put_contents($path, "<?php\nreturn ['first_year' => 1300];\n");

        try {
            $this->expectException(RtlyKitException::class);
            UmmAlQuraTable::fromFile($path);
        } finally {
            unlink($path);
        }
    }
}
