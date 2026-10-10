<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Prayer\HighLatitudeRule;
use RtlyKit\Prayer\PrayerTimes;

/**
 * The order of the six times (fajr <= sunrise <= dhuhr <= asr <= maghrib <= isha), the first and last
 * supported day, and the exact UTC offset of zones with historic seconds.
 */
final class PrayerTimesOrderAndRangeTest extends TestCase
{
    private const NAMES = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];

    private static function mins(string $hhmm): int
    {
        return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
    }

    /**
     * Checks the order of the present times of one day. Clock times wrap at midnight, so each step to the
     * next present time is a forward distance: a step backwards shows as a distance near 24 hours (a real step can be up to about 14 hours near the poles, so a time that is earlier by less than 4 hours is caught).
     *
     * @param array<string, ?string> $t
     */
    private function assertInOrder(array $t, string $label): void
    {
        $this->assertNotNull($t['dhuhr'], $label);

        $previous = null;
        foreach (self::NAMES as $name) {
            if ($t[$name] === null) {
                continue;
            }

            $now = self::mins($t[$name]);
            if ($previous !== null) {
                $step = ($now - $previous + 1440) % 1440;
                $this->assertLessThanOrEqual(1200, $step, "{$label}: {$name} {$t[$name]} is before the previous time ".json_encode($t));
            }
            $previous = $now;
        }
    }

    /* ---------------- the order at normal latitudes ---------------- */

    public function test_the_six_times_are_in_order_and_present_in_16_cities_6_methods_2_asr_factors_366_days(): void
    {
        $cities = [
            'Tehran' => [35.6892, 51.389], 'Mecca' => [21.4225, 39.8262], 'Cairo' => [30.0444, 31.2357],
            'Jakarta' => [-6.2088, 106.8456], 'Istanbul' => [41.0082, 28.9784], 'Karachi' => [24.8607, 67.0011],
            'London' => [51.5074, -0.1278], 'New York' => [40.7128, -74.006], 'Sydney' => [-33.8688, 151.2093],
            'Paris' => [48.8566, 2.3522], 'Berlin' => [52.52, 13.405], 'Moscow' => [55.7558, 37.6173],
            'Toronto' => [43.6532, -79.3832], 'Casablanca' => [33.5731, -7.5898],
            'Buenos Aires' => [-34.6037, -58.3816], 'Dhaka' => [23.8103, 90.4125],
        ];
        $methods = [
            PrayerTimes::METHOD_TEHRAN, PrayerTimes::METHOD_MWL, PrayerTimes::METHOD_ISNA,
            PrayerTimes::METHOD_EGYPT, PrayerTimes::METHOD_MAKKAH, PrayerTimes::METHOD_KARACHI,
        ];
        $utc = new DateTimeZone('UTC'); // no DST jump inside a day: clock distances are real distances

        foreach ($cities as $city => [$lat, $lng]) {
            foreach ($methods as $method) {
                foreach ([PrayerTimes::ASR_STANDARD, PrayerTimes::ASR_HANAFI] as $asr) {
                    $pt  = new PrayerTimes($lat, $lng, $method, $asr, $utc);
                    $day = new DateTimeImmutable('2024-01-01 12:00', $utc); // a leap year: 366 days
                    for ($i = 0; $i < 366; $i++, $day = $day->modify('+1 day')) {
                        $label = "{$city} {$method} {$asr} ".$day->format('Y-m-d');
                        $t     = $pt->getTimes($day);
                        $this->assertNotContains(null, $t, $label);
                        $this->assertInOrder($t, $label);
                    }
                }
            }
        }
    }

    /* ---------------- the order near the poles ---------------- */

    public function test_near_the_poles_every_returned_time_keeps_the_order_or_is_null(): void
    {
        $places = [
            'Murmansk' => [68.97, 33.09], 'Longyearbyen' => [78.22, 15.65], '89.9 N' => [89.9, 0.0],
            'McMurdo' => [-77.85, 166.67], '89.9 S' => [-89.9, 30.0], 'Tromso' => [69.65, 18.96],
        ];
        $utc = new DateTimeZone('UTC');

        foreach ($places as $place => [$lat, $lng]) {
            foreach ([PrayerTimes::METHOD_TEHRAN, PrayerTimes::METHOD_MWL, PrayerTimes::METHOD_MAKKAH] as $method) {
                foreach (HighLatitudeRule::cases() as $rule) {
                    $pt  = (new PrayerTimes($lat, $lng, $method, PrayerTimes::ASR_STANDARD, $utc))->withHighLatitudeRule($rule);
                    $day = new DateTimeImmutable('2025-01-01 12:00', $utc);
                    for ($i = 0; $i < 366; $i += 2, $day = $day->modify('+2 day')) {
                        $this->assertInOrder($pt->getTimes($day), "{$place} {$method} {$rule->value} ".$day->format('Y-m-d'));
                    }
                }
            }
        }
    }

    /* ---------------- first and last day ---------------- */

    public function test_a_prayer_after_the_end_of_9999_12_31_does_not_exist(): void
    {
        // UTC, longitude 170 W: solar noon is at about 23:20 UT, so Asr, Maghrib and Isha fall on 10000-01-01.
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(10.0, -170.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);

        $t = $pt->getTimes(new DateTimeImmutable('9999-12-31 12:00', $utc));
        $this->assertNotNull($t['fajr']);
        $this->assertNotNull($t['sunrise']);
        $this->assertEqualsWithDelta(23 * 60 + 20, self::mins($t['dhuhr']), 20, 'Dhuhr stays');
        $this->assertNull($t['asr']);
        $this->assertNull($t['maghrib']);
        $this->assertNull($t['isha']);

        $next = $pt->nextPrayer(new DateTimeImmutable('9999-12-31 22:00', $utc));
        $this->assertNotNull($next);
        $this->assertSame('dhuhr', $next['name']);
        $this->assertSame('9999-12-31', $next['date']);

        $this->assertNull($pt->nextPrayer(new DateTimeImmutable('9999-12-31 23:59', $utc)));
    }

    public function test_next_prayer_never_reports_a_date_after_9999(): void
    {
        $utc = new DateTimeZone('UTC');

        foreach ([[10.0, -170.0], [66.0, -170.0], [60.0, -175.0]] as [$lat, $lng]) {
            $pt   = new PrayerTimes($lat, $lng, PrayerTimes::METHOD_TEHRAN, PrayerTimes::ASR_STANDARD, $utc);
            $from = new DateTimeImmutable('9999-12-30 00:00', $utc);
            for ($i = 0; $i < 95; $i++, $from = $from->modify('+30 minutes')) {
                $next = $pt->nextPrayer($from);
                if ($next !== null) {
                    $this->assertLessThanOrEqual('9999-12-31', $next['date'], "{$lat} {$lng} ".$from->format('c'));
                }
            }
        }
    }

    public function test_a_prayer_before_the_start_of_0001_01_01_does_not_exist(): void
    {
        // UTC, longitude 170 E: solar noon is at about 00:40 UT, so Fajr and Sunrise fall on 0000-12-31.
        $utc = new DateTimeZone('UTC');
        $pt  = new PrayerTimes(10.0, 170.0, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc);

        $t = $pt->getTimes(new DateTimeImmutable('0001-01-01 12:00', $utc));
        $this->assertNull($t['fajr']);
        $this->assertNull($t['sunrise']);
        $this->assertNotNull($t['asr']);
        $this->assertNotNull($t['maghrib']);
        $this->assertNotNull($t['isha']);

        $next = $pt->nextPrayer(new DateTimeImmutable('0001-01-01 00:00', $utc));
        $this->assertNotNull($next);
        $this->assertSame('dhuhr', $next['name']);
        $this->assertSame('0001-01-01', $next['date']);
    }

    /* ---------------- zones with historic seconds ---------------- */

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function localMeanTimeZones(): array
    {
        return [
            'Tehran LMT +3:25:44'   => ['Asia/Tehran', '1900-03-21', 12344],
            'Amsterdam +0:19:32'    => ['Europe/Amsterdam', '1900-06-21', 1172],
            'New York LMT -4:56:02' => ['America/New_York', '1880-03-21', -17762],
        ];
    }

    #[DataProvider('localMeanTimeZones')]
    public function test_a_zone_with_a_sub_minute_offset_is_rounded_not_cut(string $zone, string $date, int $offset): void
    {
        $tz  = new DateTimeZone($zone);
        $utc = new DateTimeZone('UTC');
        $at  = new DateTimeImmutable($date.' 12:00', $utc);

        // The tz database really has this offset at that date, and it is not a whole number of minutes.
        $transitions = $tz->getTransitions($at->getTimestamp() - 86400, $at->getTimestamp());
        $this->assertSame($offset, $transitions[0]['offset']);
        $this->assertNotSame(0, $offset % 60);

        [$lat, $lng] = [35.6892, 51.389];

        $local = (new PrayerTimes($lat, $lng, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $tz))
            ->getTimes(new DateTimeImmutable($date.' 12:00', $tz));
        $base = (new PrayerTimes($lat, $lng, PrayerTimes::METHOD_MWL, PrayerTimes::ASR_STANDARD, $utc))
            ->getTimes($at);

        // Every UTC time is a whole minute: the local time is that minute plus the offset, rounded to the nearest minute.
        $shift = (int) floor($offset / 60 + 0.5);
        foreach (self::NAMES as $name) {
            $this->assertNotNull($base[$name]);
            $expected = (self::mins($base[$name]) + $shift + 1440) % 1440;
            $this->assertSame(sprintf('%02d:%02d', intdiv($expected, 60), $expected % 60), $local[$name], "{$zone} {$name}");
        }
    }
}
