<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jdn;

final class JdnTest extends TestCase
{
    /**
     * Published reference values: J2000.0 (2000-01-01) = JDN 2451545,
     * Unix epoch = JDN 2440588, 0001-01-01 (proleptic Gregorian) = JDN 1721426.
     *
     * @return array<string, array{int, int, int, int}>
     */
    public static function knownDates(): array
    {
        return [
            'J2000'            => [2000, 1, 1, 2451545],
            'unix epoch'       => [1970, 1, 1, 2440588],
            'gregorian year 1' => [1, 1, 1, 1721426],
            'leap day 2024'    => [2024, 2, 29, 2460370],
        ];
    }

    #[DataProvider('knownDates')]
    public function test_known_answers_both_directions(int $y, int $m, int $d, int $jdn): void
    {
        $this->assertSame($jdn, Jdn::fromGregorian($y, $m, $d));
        $this->assertSame([$y, $m, $d], Jdn::toGregorian($jdn));
    }

    public function test_round_trip_over_a_wide_span(): void
    {
        for ($jdn = 1721426; $jdn <= 5373484; $jdn += 997) { // years 1 .. 9999
            [$y, $m, $d] = Jdn::toGregorian($jdn);
            $this->assertSame($jdn, Jdn::fromGregorian($y, $m, $d));
        }
    }
}
