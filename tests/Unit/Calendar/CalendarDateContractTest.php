<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Contracts\CalendarDate;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Jalali, Hijri and Hebrew share one contract; dates of different calendars
 * compare and convert as instants.
 */
final class CalendarDateContractTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    /** @return array<string, array{class-string<CalendarDate>}> */
    public static function calendars(): array
    {
        return [
            'jalali' => [Jalali::class],
            'hijri'  => [Hijri::class],
            'hebrew' => [Hebrew::class],
        ];
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_class_is_final_and_implements_the_contract(string $class): void
    {
        $reflection = new ReflectionClass($class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->implementsInterface(CalendarDate::class));
        $this->assertTrue($reflection->implementsInterface(\Stringable::class));
        foreach ((new ReflectionClass(CalendarDate::class))->getMethods() as $method) {
            $this->assertTrue($reflection->hasMethod($method->getName()), $class.'::'.$method->getName());
            $this->assertSame($method->isStatic(), $reflection->getMethod($method->getName())->isStatic());
        }
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_modifiers_return_the_same_class_and_leave_the_original_untouched(string $class): void
    {
        $date = $class::make('2025-03-21 10:30:00', $this->utc);
        $ts   = $date->getTimestamp();

        foreach (['addDays', 'subDays', 'addHours', 'subHours', 'addMinutes', 'subMinutes', 'addSeconds', 'subSeconds', 'addMonths', 'subMonths', 'addYears', 'subYears'] as $method) {
            $this->assertInstanceOf($class, $date->{$method}(1), $method);
        }
        foreach (['startOfDay', 'endOfDay', 'startOfMonth', 'endOfMonth', 'startOfYear', 'endOfYear'] as $method) {
            $this->assertInstanceOf($class, $date->{$method}(), $method);
        }
        $this->assertSame($ts, $date->getTimestamp());
    }

    /**
     * 2025-03-21 00:00 UTC is 1 Farvardin 1404 (Jalali), 21 Ramadan 1446 (Umm
     * al-Qura: 1 Ramadan 1446 = 2025-03-01) and 21 Adar 5785 (Rosh Hashanah
     * 5785 = 2024-10-03).
     *
     * @return array<string, array{CalendarDate}>
     */
    public static function sameInstant(): array
    {
        $utc = new DateTimeZone('UTC');

        return [
            'jalali' => [Jalali::create(1404, 1, 1, 0, 0, 0, $utc)],
            'hijri'  => [Hijri::create(1446, 9, 21, 0, 0, 0, $utc)],
            'hebrew' => [Hebrew::make('2025-03-21 00:00:00', $utc)],
        ];
    }

    public function test_the_three_fixtures_really_are_one_instant(): void
    {
        $instants = array_map(static fn (array $c): int => $c[0]->getTimestamp(), self::sameInstant());
        $this->assertCount(1, array_unique($instants));
        $this->assertSame((new DateTimeImmutable('2025-03-21 00:00:00', $this->utc))->getTimestamp(), $instants['jalali']);
    }

    #[DataProvider('sameInstant')]
    public function test_cross_calendar_equality_and_ordering(CalendarDate $date): void
    {
        $jalali = Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc);
        $hijri  = Hijri::create(1446, 9, 21, 0, 0, 0, $this->utc);
        $hebrew = Hebrew::make('2025-03-21', $this->utc);
        $plain  = new DateTimeImmutable('2025-03-21 00:00:00', $this->utc);

        foreach ([$jalali, $hijri, $hebrew, $plain, new \DateTime('2025-03-21 00:00:00 UTC')] as $other) {
            $this->assertTrue($date->eq($other));
            $this->assertTrue($date->equals($other));
            $this->assertFalse($date->ne($other));
            $this->assertTrue($date->gte($other));
            $this->assertTrue($date->lte($other));
            $this->assertFalse($date->isBefore($other));
            $this->assertFalse($date->isAfter($other));
            $this->assertTrue($date->between($other, $other));
            $this->assertFalse($date->between($other, $other, false));
        }

        $later = Jalali::create(1404, 1, 2, 0, 0, 0, $this->utc);
        $this->assertTrue($date->isBefore($later));
        $this->assertTrue($date->lt($later));
        $this->assertFalse($date->gt($later));
        $this->assertTrue($later->isAfter($date));
        $this->assertTrue($date->between($later->subDays(2), $later));
        $this->assertTrue($date->between($later, $later->subDays(2)), 'bounds in either order');
        $this->assertTrue($date->isBefore(new DateTimeImmutable('2025-03-22', $this->utc)));
    }

    public function test_diff_in_days_accepts_any_calendar_and_keeps_the_sign(): void
    {
        $jalali = Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc);   // 2025-03-21
        $hijri  = Hijri::create(1446, 9, 1, 0, 0, 0, $this->utc);    // 2025-03-01

        $this->assertSame(20, $jalali->diffInDays($hijri));
        $this->assertSame(20, $hijri->diffInDays($jalali));
        $this->assertSame(20, $jalali->diffInDays($hijri, false), 'this is later: positive');
        $this->assertSame(-20, $hijri->diffInDays($jalali, false), 'this is earlier: negative');
        $this->assertSame(20, $hijri->diffInDays(new DateTimeImmutable('2025-03-21', $this->utc)));
    }

    public function test_make_converts_between_calendars_keeping_the_instant(): void
    {
        $jalali = Jalali::create(1404, 1, 1, 8, 15, 0, $this->utc);

        $hijri  = Hijri::make($jalali);
        $hebrew = Hebrew::make($jalali);

        $this->assertSame([1446, 9, 21], [$hijri->getYear(), $hijri->getMonth(), $hijri->getDay()]);
        $this->assertSame($jalali->getTimestamp(), $hijri->getTimestamp());
        $this->assertSame($jalali->getTimestamp(), $hebrew->getTimestamp());
        $this->assertSame([5785, 21], [$hebrew->getYear(), $hebrew->getDay()]);
        $this->assertSame(1404, Jalali::make($hijri)->getYear());
        $this->assertSame(1404, Jalali::make($hebrew)->getYear());
        $this->assertSame(8, Jalali::make($hijri)->getHour());
    }

    public function test_make_with_a_timezone_converts_an_other_calendar_date(): void
    {
        $jalali = Jalali::create(1404, 1, 1, 23, 0, 0, $this->utc);
        $tehran = Hijri::make($jalali, new DateTimeZone('Asia/Tehran')); // 02:30 on 2025-03-22

        $this->assertSame('Asia/Tehran', $tehran->getTimezone()->getName());
        $this->assertSame($jalali->getTimestamp(), $tehran->getTimestamp());
        $this->assertSame(22, (int) $tehran->toGregorian()->format('j'));
    }

    public function test_diff_in_months_and_years_use_the_receivers_calendar(): void
    {
        $hijri  = Hijri::create(1446, 9, 1, 0, 0, 0, $this->utc);     // 2025-03-01
        $jalali = Jalali::create(1404, 2, 1, 0, 0, 0, $this->utc);    // 2025-04-21 = 23 Shawwal 1446

        $this->assertSame(1, $hijri->diffInMonths($jalali));
        $this->assertSame(0, $hijri->diffInYears($jalali));
        // Same pair seen from Jalali: 1404/01/01 -> 1404/02/01 is one Jalali month.
        $this->assertSame(1, Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc)->diffInMonths($jalali));
        $this->assertSame(1, $jalali->diffInMonths(Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc), false));
        $this->assertSame(-1, Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc)->diffInMonths($jalali, false));
    }

    public function test_out_of_range_inputs_still_throw_only_invalid_date_exception(): void
    {
        $this->expectException(InvalidDateException::class);
        // Hijri reaches 9999-10-01 CE, past the end of Jalali year 9377 (9999-03-20 CE).
        Jalali::make(Hijri::create(Hijri::MAX_YEAR, 12, 1, 0, 0, 0, $this->utc));
    }

    /* ---------------- JSON ---------------- */

    public function test_json_serialises_as_the_date_text(): void
    {
        $this->assertSame('"1405\/01\/01 00:00:00"', json_encode(Jalali::create(1405, 1, 1, 0, 0, 0, $this->utc)));
        $this->assertSame('"1446\/09\/01 13:05:00"', json_encode(Hijri::create(1446, 9, 1, 13, 5, 0, $this->utc)));
        $this->assertSame(
            '{"a":"1404\/01\/01 00:00:00"}',
            json_encode(['a' => Jalali::create(1404, 1, 1, 0, 0, 0, $this->utc)]),
        );
    }

    #[DataProvider('calendars')]
    public function test_json_equals_string_form(string $class): void
    {
        $date = $class::create($class === Jalali::class ? 1404 : ($class === Hijri::class ? 1446 : 5785), 2, 3, 4, 5, 6, $this->utc);

        $this->assertInstanceOf(\JsonSerializable::class, $date);
        $this->assertSame((string) $date, $date->jsonSerialize());
        $this->assertSame(json_encode((string) $date), json_encode($date));
    }

    /* ---------------- non-date strings ---------------- */

    /** @return array<string, array{string}> */
    public static function junkStrings(): array
    {
        return [
            'x' => ['x'], 'a' => ['a'], 'z' => ['z'], 'now2' => ['now2'], 'foo' => ['foo'],
            'blank' => [' '], 'empty' => [''], 'zone' => ['UTC'], 'abbr' => ['EST'],
        ];
    }

    #[DataProvider('junkStrings')]
    public function test_make_rejects_non_date_strings(string $junk): void
    {
        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            try {
                $class::make($junk);
                $this->fail("{$class}::make('{$junk}') should throw");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_make_keeps_supported_relative_strings(): void
    {
        $now = time();
        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            $this->assertLessThanOrEqual(5, abs($class::make('now')->getTimestamp() - $now));
            $this->assertSame(0, $class::make('today')->getHour());
            $this->assertGreaterThan($now, $class::make('tomorrow')->getTimestamp());
            $this->assertGreaterThan($now + 80000, $class::make('+1 day')->getTimestamp());
            $this->assertSame(12, $class::make('noon')->getHour());
            $this->assertSame(1704067200, $class::make('2024-01-01 UTC')->getTimestamp());
        }
    }

    public function test_create_from_format_rejects_non_date_input(): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::createFromFormat('Y/m/d', 'x');
    }
}
