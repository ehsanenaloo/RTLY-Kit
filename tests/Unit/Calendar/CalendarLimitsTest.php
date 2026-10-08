<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\CalendarLimits;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Holiday\IranHolidays;

/**
 * Range guards, DoS regressions, make() string semantics and API parity
 * of the calendar classes (CalendarLimits and every calendar entry point).
 */
final class CalendarLimitsTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    /* ---------------- 2. bounds ---------------- */

    /** @return array<string, array{class-string, int, int}> */
    public static function calendars(): array
    {
        return [
            'jalali' => [Jalali::class, Jalali::MIN_YEAR, Jalali::MAX_YEAR],
            'hijri'  => [Hijri::class, Hijri::MIN_YEAR, Hijri::MAX_YEAR],
            'hebrew' => [Hebrew::class, Hebrew::MIN_YEAR, Hebrew::MAX_YEAR],
        ];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('calendars')]
    public function test_extreme_years_map_into_gregorian_1_to_9999_and_roundtrip(string $class, int $min, int $max): void
    {
        $first = $class::create($min, 1, 1, 0, 0, 0, $this->utc);
        $lastMonth = $class === Hebrew::class ? Hebrew::monthsInYear($max) : 12;
        $lastDay = match ($class) {
            Jalali::class => Jalali::daysInMonth($max, 12),
            Hijri::class => Hijri::daysInMonth($max, 12),
            default => Hebrew::daysInMonth($max, $lastMonth),
        };
        $last = $class::create($max, $lastMonth, $lastDay, 23, 59, 59, $this->utc);

        $this->assertGreaterThanOrEqual(1, (int) $first->toGregorian()->format('Y'));
        $this->assertLessThanOrEqual(9999, (int) $last->toGregorian()->format('Y'));

        foreach ([$first, $last] as $d) {
            $back = $class::make($d->toGregorian(), $this->utc);
            $this->assertSame($d->toDateTimeString(), $back->toDateTimeString());
        }

        // one step past either end is rejected
        $this->expectException(InvalidDateException::class);
        $last->addSeconds(1);
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('calendars')]
    public function test_years_outside_bounds_throw(string $class, int $min, int $max): void
    {
        foreach ([$min - 1, $max + 1, 9999, 99999, 9000000, 0, -1] as $year) {
            if ($year >= $min && $year <= $max) {
                continue;
            }
            $this->assertFalse($class::isValid($year, 1, 1), "{$class} {$year}");
            try {
                $class::create($year, 1, 1);
                $this->fail("{$class} accepted year {$year}");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_year_boundary_gregorian_dates(): void
    {
        $this->assertSame([-620, 1, 1], $this->ymdArr(Jalali::make(new DateTimeImmutable('0001-03-21', $this->utc))));
        $this->assertSame([9377, 12, 29], $this->ymdArr(Jalali::make(new DateTimeImmutable('9999-03-19', $this->utc))));
        // Dates beyond the supported calendar range are rejected, not wrapped
        foreach ([
            fn () => Jalali::make(new DateTimeImmutable('0001-01-01')),
            fn () => Jalali::make(new DateTimeImmutable('9999-12-31')),
            fn () => Hijri::make(new DateTimeImmutable('0001-01-01')),
            fn () => Hijri::make(new DateTimeImmutable('9999-12-31')),
            fn () => Hebrew::make(new DateTimeImmutable('0001-01-01')),
            fn () => Hebrew::make(new DateTimeImmutable('9999-12-31')),
            fn () => Jalali::make(new DateTimeImmutable('@253402300800')),
            fn () => Jalali::make(new DateTimeImmutable('-0005-01-01')),
        ] as $i => $fn) {
            try {
                $fn();
                $this->fail("case {$i} did not throw");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('calendars')]
    public function test_shifting_past_the_edges_throws(string $class, int $min, int $max): void
    {
        $edge = $class::create($max, 1, 1, 0, 0, 0, $this->utc);
        foreach ([
            fn () => $edge->addYears(2),
            fn () => $edge->addMonths(40),
            fn () => $edge->addDays(900),
            fn () => $edge->addHours(24 * 900),
            fn () => $edge->addMinutes(60 * 24 * 900),
            fn () => $edge->addSeconds(86400 * 900),
        ] as $i => $fn) {
            try {
                $fn();
                $this->fail("{$class} shift {$i} did not throw");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
        $low = $class::create($min, 12, 1, 0, 0, 0, $this->utc);
        $this->expectException(InvalidDateException::class);
        $low->subYears(2);
    }

    /**
     * @return array<string, array{Closure}>
     */
    public static function entryPoints(): array
    {
        $big = [PHP_INT_MAX, PHP_INT_MIN];
        $cases = [];
        $utc = new DateTimeZone('UTC');
        foreach ([Jalali::class => [1403, 1, 1], Hijri::class => [1446, 1, 1], Hebrew::class => [5785, 1, 1]] as $class => $ymd) {
            $short = substr(strrchr($class, '\\') ?: $class, 1);
            foreach ($big as $n) {
                $tag = $n === PHP_INT_MAX ? 'max' : 'min';
                $cases["{$short} create year {$tag}"] = [fn () => $class::create($n, 1, 1)];
                $cases["{$short} create month {$tag}"] = [fn () => $class::create($ymd[0], $n, 1)];
                $cases["{$short} create day {$tag}"] = [fn () => $class::create($ymd[0], 1, $n)];
                $cases["{$short} create hour {$tag}"] = [fn () => $class::create($ymd[0], 1, 1, $n)];
                $cases["{$short} make int {$tag}"] = [fn () => $class::make($n)];
                $cases["{$short} isValid {$tag}"] = [fn () => $class::isValid($n, $n, $n)];
                $cases["{$short} daysInMonth {$tag}"] = [fn () => $class::daysInMonth($n, 1)];
                $cases["{$short} isLeapYear {$tag}"] = [fn () => $class::isLeapYear($n)];
                $cases["{$short} daysInYear {$tag}"] = [fn () => $class::daysInYear($n)];
                $cases["{$short} g2x {$tag}"] = [fn () => match ($class) {
                    Jalali::class => Jalali::gregorianToJalali($n, 1, 1),
                    Hijri::class => Hijri::gregorianToHijri($n, 1, 1),
                    default => Hebrew::gregorianToHebrew($n, 1, 1),
                }];
                $cases["{$short} x2g {$tag}"] = [fn () => match ($class) {
                    Jalali::class => Jalali::jalaliToGregorian($n, 1, 1),
                    Hijri::class => Hijri::hijriToGregorian($n, 1, 1),
                    default => Hebrew::hebrewToGregorian($n, 1, 1),
                }];
                foreach (['addDays', 'subDays', 'addMonths', 'subMonths', 'addYears', 'subYears',
                    'addHours', 'subHours', 'addMinutes', 'subMinutes', 'addSeconds', 'subSeconds'] as $method) {
                    $cases["{$short} {$method} {$tag}"] = [fn () => $class::create($ymd[0], $ymd[1], $ymd[2], 0, 0, 0, $utc)->{$method}($n)];
                }
            }
            foreach (['99999-01-01', '-99999-01-01', '9999999999-01-01', "x\0y", '   ', '', '99999/1/1', '١٤٠٣/١٣/٤٥'] as $str) {
                $cases["{$short} make string ".addcslashes($str, "\0")] = [fn () => $class::make($str)];
            }
            $cases["{$short} variant"] = [fn () => $class === Hijri::class ? Hijri::create(PHP_INT_MAX, 1, 1, 0, 0, 0, null, HijriVariant::Tabular) : throw new InvalidDateException('n/a')];
        }
        foreach ($big as $n) {
            $tag = $n === PHP_INT_MAX ? 'max' : 'min';
            $cases["Jalali createFromFormat {$tag}"] = [fn () => Jalali::createFromFormat('Y-m-d', "{$n}-01-01")];
            $cases["IranHolidays all {$tag}"] = [fn () => IranHolidays::all($n)];
            $cases["IranHolidays allTitles {$tag}"] = [fn () => IranHolidays::allTitles($n)];
            $cases["IranHolidays allFixed {$tag}"] = [fn () => IranHolidays::allFixed($n)];
            $cases["IranHolidays isHoliday {$tag}"] = [fn () => IranHolidays::isHoliday($n, 1, 1)];
        }

        return $cases;
    }

    #[DataProvider('entryPoints')]
    public function test_entry_points_only_throw_rtlykit_exceptions(Closure $call): void
    {
        set_error_handler(static function (int $no, string $str): never {
            throw new \ErrorException($str, 0, $no);
        });
        try {
            $call();
            $this->addToAssertionCount(1); // returning normally (e.g. isValid() === false) is fine
        } catch (RtlyKitException) {
            $this->addToAssertionCount(1);
        } finally {
            restore_error_handler();
        }
    }

    public function test_make_empty_and_null(): void
    {
        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            foreach (['', '   ', "\t\n"] as $blank) {
                try {
                    $class::make($blank);
                    $this->fail("{$class} accepted blank string");
                } catch (InvalidDateException) {
                    $this->addToAssertionCount(1);
                }
            }
            $before = time();
            $now = $class::make(null);
            $this->assertEqualsWithDelta($before, $now->getTimestamp(), 5);
        }
    }

    public function test_hijri_and_hebrew_parity(): void
    {
        $h = Hijri::create(1446, 9, 1, 23, 30, 0, $this->utc);
        $this->assertSame('1446/09/02 01:30:00', $h->addHours(2)->format());
        $this->assertSame('1446/09/01 23:00:00', $h->subMinutes(30)->format());
        $this->assertSame('1446/09/01 23:30:15', $h->addSeconds(15)->format());
        $this->assertSame('1446/08/29 23:30:00', $h->subHours(24)->format());
        $this->assertSame(6, $h->getDayOfWeek()); // Saturday 2025-03-01, Sunday-first
        $this->assertSame('1446-09-01T23:30:00+00:00', $h->format('c'));
        $this->assertSame('11:30 PM', $h->format('h:i A', 'en'));
        $this->assertSame('0', Hijri::create(1446, 1, 1)->format('z'));
        $this->assertSame((string) array_sum(array_map(static fn (int $m): int => Hijri::daysInMonth(1446, $m), range(1, 8))), $h->format('z'));
        $this->assertSame('', $h->format('S'));
        $this->assertSame(Hijri::today($this->utc)->getTimestamp(), Hijri::today($this->utc)->startOfDay()->getTimestamp());

        $e = Hebrew::create(5785, 1, 10, 23, 30, 0, $this->utc);
        $this->assertSame('5785/01/11 01:30:00', $e->addHours(2)->format());
        $this->assertSame('5785/01/10 23:00:00', $e->subMinutes(30)->format());
        $this->assertSame('5785/01/10 23:30:15', $e->addSeconds(15)->format());
        $this->assertSame(6, $e->getDayOfWeek()); // Saturday 2024-10-12
        $this->assertSame('5785-01-10T23:30:00+00:00', $e->format('c'));
        $this->assertSame('9', $e->format('z'));
        $this->assertSame('11:30 pm', $e->format('h:i a'));
        $this->assertSame(0, Hebrew::today($this->utc)->getHour());
    }

    /* ---------------- 6. determinism helpers ---------------- */

    public function test_is_today_uses_one_instant(): void
    {
        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            $a = $class::now();
            $result = $a->isToday();
            $b = $class::now();
            if ($a->toDateString() === $b->toDateString()) { // skip if midnight passed between the calls
                $this->assertTrue($result, $class);
            }
            $this->assertFalse($class::make('2001-01-01')->isToday(), $class);
        }
    }

    /* ---------------- CalendarLimits ---------------- */

    public function test_calendar_limits_is_a_static_only_utility(): void
    {
        $ref = new \ReflectionClass(CalendarLimits::class);

        $this->assertFalse($ref->isInstantiable());
        $this->assertTrue($ref->getConstructor()?->isPrivate());
    }

    /* ---------------- helpers ---------------- */

    /**
     * @param Jalali|Hijri|Hebrew $d
     * @return array{int, int, int}
     */
    private function ymdArr(object $d): array
    {
        return [$d->getYear(), $d->getMonth(), $d->getDay()];
    }
}
