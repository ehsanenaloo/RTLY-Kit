<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;

/**
 * Compares the library with the official Nowruz dates and leap-year markers published by the
 * Calendar Center of the Institute of Geophysics, University of Tehran (AP 1206-1497).
 */
final class JalaliOfficialNowruzTest extends TestCase
{
    /** @return array{sources: array<string, mixed>, years: array<int, array{string, string, int}>} */
    private static function fixture(): array
    {
        /** @var array{sources: array<string, mixed>, years: array<int, array{string, string, int}>} $data */
        $data = require __DIR__.'/../../Fixtures/jalali-official-nowruz.php';

        return $data;
    }

    public function test_one_farvardin_matches_the_official_gregorian_date_for_every_year(): void
    {
        $count = 0;
        foreach (self::fixture()['years'] as $year => [$iso, $source]) {
            if ($year < 1206 || $year > 1497) {
                continue;
            }
            $this->assertSame(
                $iso,
                Jalali::create($year, 1, 1)->toGregorian()->format('Y-m-d'),
                "1 Farvardin {$year} differs from the official date (source {$source})",
            );
            $count++;
        }

        $this->assertSame(292, $count);
    }

    public function test_leap_years_match_the_official_markers_for_every_year(): void
    {
        foreach (self::fixture()['years'] as $year => [, $source, $marker]) {
            if ($year < 1206 || $year > 1497) {
                continue;
            }
            $this->assertSame(
                $marker > 0,
                Jalali::isLeapYear($year),
                "Leap status of {$year} differs from the official marker {$marker} (source {$source})",
            );
        }
    }
}
