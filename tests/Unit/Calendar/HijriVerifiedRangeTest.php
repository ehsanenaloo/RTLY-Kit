<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;

final class HijriVerifiedRangeTest extends TestCase
{
    public function test_verified_range_is_1318_to_1500(): void
    {
        $this->assertSame([1318, 1500], Hijri::ummAlQuraVerifiedRange());
    }

    public function test_boundary_years(): void
    {
        $this->assertFalse(Hijri::isUmmAlQuraVerified(1317));
        $this->assertTrue(Hijri::isUmmAlQuraVerified(1318));
        $this->assertTrue(Hijri::isUmmAlQuraVerified(1446));
        $this->assertTrue(Hijri::isUmmAlQuraVerified(1500));
        $this->assertFalse(Hijri::isUmmAlQuraVerified(1501));
    }

    public function test_unconfirmed_and_outside_years(): void
    {
        $this->assertFalse(Hijri::isUmmAlQuraVerified(1300));
        $this->assertFalse(Hijri::isUmmAlQuraVerified(1299));
        $this->assertFalse(Hijri::isUmmAlQuraVerified(0));
        $this->assertFalse(Hijri::isUmmAlQuraVerified(-5));
        $this->assertFalse(Hijri::isUmmAlQuraVerified(PHP_INT_MAX));
    }

    public function test_has_data_semantics_are_unchanged(): void
    {
        $this->assertTrue(Hijri::hasUmmAlQuraData(1300));
        $this->assertTrue(Hijri::hasUmmAlQuraData(1317));
        $this->assertTrue(Hijri::hasUmmAlQuraData(1500));
        $this->assertFalse(Hijri::hasUmmAlQuraData(1299));
        $this->assertFalse(Hijri::hasUmmAlQuraData(1501));
    }

    public function test_every_verified_year_has_table_data(): void
    {
        [$first, $last] = Hijri::ummAlQuraVerifiedRange();
        for ($y = $first; $y <= $last; $y++) {
            $this->assertTrue(Hijri::hasUmmAlQuraData($y), (string) $y);
            $this->assertTrue(Hijri::isUmmAlQuraVerified($y), (string) $y);
        }
    }
}
