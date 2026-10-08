<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Holiday;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Holiday\HolidayData;
use RtlyKit\Holiday\HolidaySource;
use RtlyKit\Holiday\HolidayTitles;
use RtlyKit\Validation\DataTables;

/**
 * Integrity of resources/data/iran-official-holidays.php and known-answer rows read from its sources
 * (the yearly official calendar of the University of Tehran Calendar Center, see the official-holidays research notes).
 */
final class HolidayDataTest extends TestCase
{
    public function test_the_shipped_table_covers_1380_to_1405_without_gaps(): void
    {
        self::assertSame(range(1380, 1405), HolidayData::years());
    }

    public function test_statuses_per_year(): void
    {
        foreach (range(1380, 1393) as $year) {
            self::assertSame(HolidaySource::Reported, HolidayData::year($year)['source'] ?? null, "year $year");
        }
        // 1395 is the weakest basis (PDF digits plus one news list), so it is reported, not official.
        self::assertSame(HolidaySource::Official, HolidayData::year(1394)['source'] ?? null);
        self::assertSame(HolidaySource::Reported, HolidayData::year(1395)['source'] ?? null);
        foreach (range(1396, 1405) as $year) {
            self::assertSame(HolidaySource::Official, HolidayData::year($year)['source'] ?? null, "year $year");
        }
    }

    public function test_unknown_years_are_absent(): void
    {
        self::assertNull(HolidayData::year(1379));
        self::assertNull(HolidayData::year(1406));
        self::assertNull(HolidayData::year(0));
    }

    public function test_every_recorded_date_exists_and_every_title_is_known(): void
    {
        $known = HolidayTitles::knownIslamic();

        foreach (HolidayData::years() as $year) {
            $data = HolidayData::year($year);
            self::assertNotNull($data);
            self::assertNotSame([], $data['holidays'], "year $year");

            foreach ($data['holidays'] as $monthDay => $titles) {
                [$month, $day] = array_map('intval', explode('/', $monthDay));
                self::assertTrue(Jalali::isValid($year, $month, $day), "$year/$monthDay");
                foreach ($titles as $title) {
                    self::assertContains($title, $known, "$year/$monthDay");
                }
            }
        }
    }

    public function test_every_year_lists_each_one_off_holiday_at_most_twice(): void
    {
        // Titles can repeat within a Jalali year only when a Hijri year ends inside it (e.g. Eid al-Fitr in 1405).
        foreach (HolidayData::years() as $year) {
            $counts = [];
            foreach (HolidayData::year($year)['holidays'] ?? [] as $titles) {
                foreach ($titles as $title) {
                    $counts[$title] = ($counts[$title] ?? 0) + 1;
                }
            }
            foreach ($counts as $title => $count) {
                self::assertLessThanOrEqual(2, $count, "$year $title");
            }
        }
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function knownRows(): array
    {
        return [
            // Official calendar of 1405: 1 Shawwal 1447 is the first day of the year, the Umm al-Qura estimate is 1404/12/29.
            'Eid al-Fitr 1447 AH' => [1405, '1/1', 'عید فطر'],
            'second day of Eid al-Fitr 1447 AH' => [1405, '1/2', 'تعطیل عید فطر'],
            'Eid al-Fitr 1448 AH in the same Jalali year' => [1405, '12/19', 'عید فطر'],
            'Imam Reza 1405' => [1405, '5/22', 'شهادت امام رضا'],
            'Eid al-Fitr 1446 AH' => [1404, '1/11', 'عید فطر'],
            'Ashura 1446 AH' => [1404, '4/15', 'عاشورای حسینی'],
            'Imam Reza 1403 (30 Safar)' => [1403, '6/14', 'شهادت امام رضا'],
            'Eid al-Adha 1445 AH' => [1403, '3/28', 'عید قربان'],
            'Eid al-Fitr 1396' => [1396, '4/5', 'عید فطر'],
            'Imam Ali 21 Ramadan 1394' => [1394, '4/17', 'شهادت امام علی'],
            'Eid al-Fitr 1395 (reported)' => [1395, '4/16', 'عید فطر'],
            'Eid al-Fitr 1380 (reported)' => [1380, '9/25', 'عید فطر'],
        ];
    }

    #[DataProvider('knownRows')]
    public function test_known_answer_rows(int $year, string $monthDay, string $title): void
    {
        self::assertContains($title, HolidayData::year($year)['holidays'][$monthDay] ?? []);
    }

    public function test_eid_al_fitr_1447_is_official_on_1405_01_01_but_estimated_on_1404_12_29(): void
    {
        $official = HolidayCalendar::default();
        $estimate = $official->withOfficialData(false);

        self::assertSame(['جشن نوروز', 'عید فطر'], $official->getTitles(1405, 1, 1));
        self::assertSame(['ملی شدن صنعت نفت'], $official->getTitles(1404, 12, 29));

        self::assertSame(['ملی شدن صنعت نفت', 'عید فطر'], $estimate->getTitles(1404, 12, 29));
        self::assertSame(['جشن نوروز', 'تعطیل عید فطر'], $estimate->getTitles(1405, 1, 1));
    }

    public function test_the_table_in_the_file_passes_validation_unchanged(): void
    {
        $raw = DataTables::load('iran-official-holidays');

        self::assertCount(count($raw), HolidayData::validate($raw));
    }

    /* ---------------- validation ---------------- */

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function corruptTables(): array
    {
        $good = [
            'status' => 'official',
            'sources' => [['url' => 'https://example.test/x', 'kind' => 'primary', 'accessed' => '2026-10-08']],
            'holidays' => ['1/1' => ['عید فطر']],
        ];
        $with = static fn (array $patch): array => [1404 => array_replace($good, $patch)];

        return [
            'year is a string' => [['1404' => $good, 'x' => $good]],
            'year zero' => [[0 => $good]],
            'year past the Jalali range' => [[Jalali::MAX_YEAR + 1 => $good]],
            'entry is not an array' => [[1404 => 'official']],
            'unknown status' => [$with(['status' => 'estimated'])],
            'missing status' => [[1404 => ['sources' => $good['sources'], 'holidays' => $good['holidays']]]],
            'no sources' => [$with(['sources' => []])],
            'no holidays' => [$with(['holidays' => []])],
            'holidays not an array' => [$with(['holidays' => 'x'])],
            'month out of range' => [$with(['holidays' => ['13/1' => ['عید فطر']]])],
            'month zero' => [$with(['holidays' => ['0/1' => ['عید فطر']]])],
            'day zero' => [$with(['holidays' => ['1/0' => ['عید فطر']]])],
            'day 32' => [$with(['holidays' => ['1/32' => ['عید فطر']]])],
            'day 31 in month 7' => [$with(['holidays' => ['7/31' => ['عید فطر']]])],
            'day 30 in Esfand of a common year' => [$with(['holidays' => ['12/30' => ['عید فطر']]])],
            'padded key' => [$with(['holidays' => ['01/01' => ['عید فطر']]])],
            'key without slash' => [$with(['holidays' => ['1-1' => ['عید فطر']]])],
            'int key' => [$with(['holidays' => [5 => ['عید فطر']]])],
            'titles not an array' => [$with(['holidays' => ['1/1' => 'عید فطر']])],
            'empty titles' => [$with(['holidays' => ['1/1' => []]])],
            'titles not a list' => [$with(['holidays' => ['1/1' => ['a' => 'عید فطر']]])],
            'unknown title' => [$with(['holidays' => ['1/1' => ['جشن نوروز']]])],
            'non-string title' => [$with(['holidays' => ['1/1' => [5]]])],
            'duplicate title on a day' => [$with(['holidays' => ['1/1' => ['عید فطر', 'عید فطر']]])],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    #[DataProvider('corruptTables')]
    public function test_corrupt_tables_are_rejected_with_data_unavailable(array $raw): void
    {
        try {
            HolidayData::validate($raw);
            self::fail('expected a corrupt-table exception');
        } catch (RtlyKitException $e) {
            self::assertSame(ErrorCode::DataUnavailable, $e->getErrorCode());
            self::assertSame('iran-official-holidays', $e->getContext()['table']);
            self::assertStringContainsString('corrupt', $e->getMessage());
        }
    }

    public function test_a_leap_year_esfand_30_is_accepted_and_years_are_sorted(): void
    {
        $entry = [
            'status' => 'single_source',
            'sources' => [['url' => 'u']],
            'holidays' => ['12/30' => ['عید قربان', 'شهادت امام رضا']],
        ];

        $result = HolidayData::validate([1403 => $entry, 1399 => $entry]);

        self::assertSame([1399, 1403], array_keys($result));
        self::assertSame(HolidaySource::Reported, $result[1403]['source']);
        self::assertSame(['عید قربان', 'شهادت امام رضا'], $result[1403]['holidays']['12/30']);
    }
}
