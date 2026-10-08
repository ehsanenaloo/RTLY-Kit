<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Known-answer tests for Hebrew calendar paths (format tokens, comparisons,
 * arithmetic borrow rules, invalid-month guards).
 * 2024-03-20 (Wednesday) = 1403/01/01 Jalali = 1445/09/10 Hijri = 10 Adar II 5784.
 */
final class HebrewTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    /** @return array<string, array{int, string}> */
    public static function roshHashanah(): array
    {
        return [
            '5783' => [5783, '2022-09-26'],
            '5784' => [5784, '2023-09-16'],
            '5785' => [5785, '2024-10-03'],
            '5786' => [5786, '2025-09-23'],
            '5787' => [5787, '2026-09-12'],
        ];
    }

    #[DataProvider('roshHashanah')]
    public function test_rosh_hashanah_known_dates(int $year, string $gregorian): void
    {
        $h = Hebrew::create($year, 1, 1);
        $this->assertSame($gregorian, $h->toGregorian()->format('Y-m-d'));

        $back = Hebrew::make(new DateTimeImmutable($gregorian));
        $this->assertSame([$year, 1, 1], [$back->getYear(), $back->getMonth(), $back->getDay()]);
    }

    public function test_passover_and_other_holidays(): void
    {
        // 5785 is a regular year: Nisan = month 7
        $this->assertSame('2025-04-13', Hebrew::create(5785, 7, 15)->toGregorian()->format('Y-m-d'));
        // 5784 is a leap year: Nisan = month 8
        $this->assertSame('2024-04-23', Hebrew::create(5784, 8, 15)->toGregorian()->format('Y-m-d'));
        // Yom Kippur 5785, 25 Kislev 5785 (Hanukkah starts the evening before)
        $this->assertSame('2024-10-12', Hebrew::create(5785, 1, 10)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2024-12-26', Hebrew::create(5785, 3, 25)->toGregorian()->format('Y-m-d'));
    }

    public function test_gregorian_to_hebrew_known(): void
    {
        $h = Hebrew::make('2026-10-07');
        $this->assertSame([5787, 1, 26], [$h->getYear(), $h->getMonth(), $h->getDay()]);

        $h = Hebrew::make('2025-04-13');
        $this->assertSame([5785, 7, 15], [$h->getYear(), $h->getMonth(), $h->getDay()]);
    }

    public function test_leap_years_and_lengths(): void
    {
        foreach ([5784, 5787] as $y) {
            $this->assertTrue(Hebrew::isLeapYear($y), (string) $y);
            $this->assertSame(13, Hebrew::monthsInYear($y));
        }
        foreach ([5783, 5785, 5786] as $y) {
            $this->assertFalse(Hebrew::isLeapYear($y), (string) $y);
        }
        $this->assertSame(355, Hebrew::daysInYear(5785));
        $this->assertSame(354, Hebrew::daysInYear(5786));
        $this->assertSame(30, Hebrew::daysInMonth(5785, 2)); // complete year: Cheshvan 30
        $this->assertSame(29, Hebrew::daysInMonth(5786, 2));
        $this->assertSame(30, Hebrew::daysInMonth(5784, 6)); // Adar I
        $this->assertSame(29, Hebrew::daysInMonth(5784, 7)); // Adar II
    }

    public function test_year_lengths_always_valid_and_roundtrip(): void
    {
        $valid = [353, 354, 355, 383, 384, 385];
        for ($y = 5700; $y <= 5900; $y++) {
            $len = Hebrew::daysInYear($y);
            $this->assertContains($len, $valid, (string) $y);
            $sum = 0;
            for ($m = 1; $m <= Hebrew::monthsInYear($y); $m++) {
                $sum += Hebrew::daysInMonth($y, $m);
            }
            $this->assertSame($len, $sum, (string) $y);
        }

        // Contiguity: every Gregorian day maps to the next Hebrew day, round-trips.
        $d = new DateTimeImmutable('2020-01-01');
        $prev = null;
        for ($i = 0; $i < 3000; $i++, $d = $d->modify('+1 day')) {
            $h = Hebrew::make($d);
            $this->assertSame($d->format('Y-m-d'), Hebrew::create($h->getYear(), $h->getMonth(), $h->getDay())->toGregorian()->format('Y-m-d'));
            if ($prev !== null) {
                $this->assertTrue(
                    ($h->getDay() === $prev->getDay() + 1 && $h->getMonth() === $prev->getMonth() && $h->getYear() === $prev->getYear())
                    || $h->getDay() === 1,
                );
            }
            $prev = $h;
        }
    }

    public function test_invalid_dates_throw(): void
    {
        foreach ([[5785, 13, 1], [5784, 14, 1], [5786, 2, 30], [5785, 1, 31], [5785, 1, 0], [0, 1, 1], [5785, 0, 1]] as [$y, $m, $d]) {
            $this->assertFalse(Hebrew::isValid($y, $m, $d));
            try {
                Hebrew::create($y, $m, $d);
                $this->fail("expected exception for {$y}/{$m}/{$d}");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertTrue(Hebrew::isValid(5784, 13, 29));
    }

    public function test_dates_before_epoch_throw(): void
    {
        $this->expectException(InvalidDateException::class);
        Hebrew::make('-4000-01-01');
    }

    public function test_month_names(): void
    {
        $this->assertSame('Adar', Hebrew::monthName(5785, 6));
        $this->assertSame('Adar I', Hebrew::monthName(5784, 6));
        $this->assertSame('Adar II', Hebrew::monthName(5784, 7));
        $this->assertSame('Nisan', Hebrew::monthName(5785, 7));
        $this->assertSame('Nisan', Hebrew::monthName(5784, 8));
        $this->assertSame('Elul', Hebrew::monthName(5784, 13));
        $this->assertSame('תשרי', Hebrew::monthName(5785, 1, 'he'));
        $this->assertSame('אדר', Hebrew::monthName(5785, 6, 'he'));
    }

    public function test_format_tokens(): void
    {
        $h = Hebrew::create(5785, 7, 15, 9, 5, 3);
        $this->assertSame('5785/07/15 09:05:03', $h->format());
        $this->assertSame('15 Nisan 5785', $h->format('j F Y'));
        $this->assertSame('30', $h->format('t'));
        $this->assertSame('0', $h->format('L'));
        $this->assertSame('Sunday', $h->format('l')); // 2025-04-13 is a Sunday
        $this->assertSame('Y 5785', $h->format('\Y Y'));
        $this->assertSame('5785/07/15', (string) $h->toDateString());
    }

    public function test_add_months_years(): void
    {
        $h = Hebrew::create(5784, 5, 30); // 30 Shevat 5784
        $this->assertSame([5784, 6, 30], self::ymd($h->addMonths(1))); // Adar I has 30 days
        $this->assertSame([5784, 7, 29], self::ymd($h->addMonths(2))); // Adar II clamped
        $this->assertSame([5785, 1, 29], self::ymd(Hebrew::create(5784, 13, 29)->addMonths(1)));
        $this->assertSame([5784, 13, 29], self::ymd(Hebrew::create(5785, 1, 29)->subMonths(1)));
        $this->assertSame([5785, 6, 15], self::ymd(Hebrew::create(5784, 6, 15)->addYears(1))); // Adar I -> Adar
        $this->assertSame([5785, 6, 15], self::ymd(Hebrew::create(5784, 7, 15)->addYears(1))); // Adar II -> Adar
        $this->assertSame([5787, 7, 15], self::ymd(Hebrew::create(5785, 6, 15)->addYears(2))); // Adar -> Adar II
        $this->assertSame([5787, 8, 1], self::ymd(Hebrew::create(5785, 7, 1)->addYears(2))); // Nisan stays Nisan
        $this->assertSame([5786, 1, 1], self::ymd(Hebrew::create(5785, 1, 1)->addYears(1)));
    }

    public function test_start_end_and_days(): void
    {
        $h = Hebrew::create(5785, 7, 15, 12, 0, 0);
        $this->assertSame('5785/07/01 00:00:00', $h->startOfMonth()->toDateTimeString());
        $this->assertSame('5785/07/30 23:59:59', $h->endOfMonth()->toDateTimeString());
        $this->assertSame('5785/01/01 00:00:00', $h->startOfYear()->toDateTimeString());
        $this->assertSame('5785/12/29 23:59:59', $h->endOfYear()->toDateTimeString());
        $this->assertSame('5784/13/29 23:59:59', Hebrew::create(5784, 1, 1)->endOfYear()->toDateTimeString());
        $this->assertSame('5785/07/16 12:00:00', $h->addDays(1)->toDateTimeString());
        $this->assertSame('5785/07/14 00:00:00', $h->subDays(1)->startOfDay()->toDateTimeString());
        $this->assertSame('5785/07/15 23:59:59', $h->endOfDay()->toDateTimeString());
    }

    public function test_comparison_and_timezone(): void
    {
        $tz = new DateTimeZone('Asia/Jerusalem');
        $a = Hebrew::create(5785, 1, 1, 0, 0, 0, $tz);
        $b = $a->addDays(1);
        $this->assertSame('Asia/Jerusalem', $a->getTimezone()->getName());
        $this->assertTrue($a->lt($b));
        $this->assertTrue($b->gt($a));
        $this->assertTrue($a->lt($b->toGregorian()));
        $this->assertTrue($a->eq($a));
        $this->assertTrue($a->ne($b));
        $this->assertTrue($a->lte($a) && $a->gte($a));
        $this->assertSame($a, Hebrew::make($a));
    }

    /* ---------------- 1. Hebrew DoS ---------------- */

    public function test_hebrew_add_months_is_bounded_work(): void
    {
        $start = microtime(true);
        foreach ([PHP_INT_MAX, PHP_INT_MIN, 10 ** 12, -(10 ** 12)] as $n) {
            try {
                Hebrew::create(5785, 1, 1)->addMonths($n);
                $this->fail("expected InvalidDateException for {$n}");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach ([PHP_INT_MAX, PHP_INT_MIN] as $n) {
            try {
                Hebrew::create(5785, 1, 1)->addYears($n);
                $this->fail('expected InvalidDateException');
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
        // Generous bound: the point is "not effectively unbounded", and shared CI runners can be slow.
        $this->assertLessThan(5.0, microtime(true) - $start);
    }

    public function test_hebrew_add_months_matches_stepwise_walk(): void
    {
        $base = Hebrew::create(5784, 1, 15);
        $walk = $base;
        for ($i = 1; $i <= 300; $i++) {
            $walk = $walk->addMonths(1);
            $jump = $base->addMonths($i);
            $this->assertSame(
                [$walk->getYear(), $walk->getMonth()],
                [$jump->getYear(), $jump->getMonth()],
                "+{$i} months",
            );
        }
        // sub is the mirror image, and a full Metonic cycle is exactly 19 years
        $this->assertSame('5784/1/15', $this->ymdOf($base->addMonths(235)->subMonths(235)));
        $this->assertSame('5803/1/15', $this->ymdOf($base->addMonths(235)));
        $this->assertSame('5803/1/15', $this->ymdOf($base->addYears(19)));
        $this->assertSame('5765/1/15', $this->ymdOf($base->subMonths(235)));
    }

    public function test_hebrew_make_semantics(): void
    {
        $this->assertSame('2024-10-12', Hebrew::make('5785/01/10', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2024-10-12', Hebrew::make('5785-1-10 00:00', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2024-10-12', Hebrew::make('۵۷۸۵/۰۱/۱۰', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame([5785, 1, 10], $this->ymdArr(Hebrew::make('2024-10-12', $this->utc)));
        $this->expectException(InvalidDateException::class);
        Hebrew::make('5785/13/01');
    }

    /* ---------------- Hebrew::format ---------------- */

    public function test_hebrew_format_every_token_afternoon(): void
    {
        $h = Hebrew::make('2024-03-20 15:05:09', $this->utc());
        $tokens = ['y', 'm', 'n', 'd', 'j', 'H', 'G', 'i', 's', 'F', 'M', 'N', 'z', 'a', 'A', 'g', 'h', 'S', 'W',
            'c', 'r', 'U', 'e', 'T', 'P', 'p', 'O', 'Z', 'I', 'u', 'v', 't', 'L', 'w', 'l', 'x'];

        $expected = [
            'y' => '84', 'm' => '07', 'n' => '7', 'd' => '10', 'j' => '10', 'H' => '15', 'G' => '15', 'i' => '05',
            's' => '09', 'F' => 'Adar II', 'M' => 'Adar II', 'N' => '3',
            'z' => '186', // 30+29+29+29+30+30 days before Adar II, plus 9 days into it (zero-based)
            'a' => 'pm', 'A' => 'PM', 'g' => '3', 'h' => '03', 'S' => '', 'W' => '12',
            'c' => '5784-07-10T15:05:09+00:00', 'r' => 'Wed, 20 Mar 2024 15:05:09 +0000',
            'U' => '1710947109', 'e' => 'UTC', 'T' => 'UTC', 'P' => '+00:00', 'p' => 'Z', 'O' => '+0000',
            'Z' => '0', 'I' => '0', 'u' => '000000', 'v' => '000',
            't' => '29', 'L' => '1', 'w' => '3', 'l' => 'Wednesday', 'x' => 'x',
        ];

        foreach ($tokens as $t) {
            $this->assertSame($expected[$t], $h->format($t), "token {$t}");
        }
    }

    public function test_hebrew_format_twelve_hour_clock_at_midnight_and_escapes(): void
    {
        $h = Hebrew::make('2024-03-20 00:05:09', $this->utc());

        $this->assertSame('12 12 am AM', $h->format('g h a A'));
        $this->assertSame('Y 10', $h->format('\\Y d'));
        $this->assertSame('10', $h->format('d\\'), 'a trailing backslash is dropped');
    }

    public function test_hebrew_diff_in_months_borrows_when_day_of_month_not_reached(): void
    {
        $a = Hebrew::create(5784, 1, 20);
        $b = Hebrew::create(5784, 3, 10);

        $this->assertSame(1, $a->diffInMonths($b));
        $this->assertSame(1, $b->diffInMonths($a, false)); // sign is (this - other)
        $this->assertSame(2, $a->diffInMonths(Hebrew::create(5784, 3, 20)));
    }

    public function test_hebrew_diff_in_years_borrows_when_anniversary_not_reached(): void
    {
        $a = Hebrew::create(5784, 1, 20);

        $this->assertSame(0, $a->diffInYears(Hebrew::create(5785, 1, 10)));
        $this->assertSame(1, $a->diffInYears(Hebrew::create(5785, 1, 20)));
        $this->assertSame(1, Hebrew::create(5785, 1, 10)->diffInYears(Hebrew::create(5784, 1, 5)));
    }

    /* ---------------- Hebrew / Hijri guards ---------------- */

    public function test_hebrew_add_months_before_year_one_is_rejected_with_library_exception(): void
    {
        $this->expectException(InvalidDateException::class);
        Hebrew::create(3762, 1, 1)->addMonths(-100000);
    }

    public function test_hebrew_days_in_month_known_values_and_invalid_month(): void
    {
        $this->assertSame(29, Hebrew::daysInMonth(5784, 7));  // Adar II
        $this->assertSame(30, Hebrew::daysInMonth(5784, 6));  // Adar I
        $this->assertSame(29, Hebrew::daysInMonth(5785, 6));  // Adar in a non-leap year
        $this->assertSame(13, Hebrew::monthsInYear(5784));

        $this->expectException(InvalidDateException::class);
        Hebrew::daysInMonth(5785, 13); // 5785 has only 12 months
    }

    public function test_hebrew_month_name_rejects_month_beyond_year_length(): void
    {
        $this->assertSame('Adar II', Hebrew::monthName(5784, 7));

        $this->expectException(InvalidDateException::class);
        Hebrew::monthName(5784, 14);
    }

    public function test_hebrew_gregorian_year_one_maps_to_am_3761_and_year_zero_is_rejected(): void
    {
        $this->assertSame(3761, Hebrew::gregorianToHebrew(1, 1, 1)[0], '1 CE January is still AM 3761');

        $this->expectException(InvalidDateException::class);
        Hebrew::gregorianToHebrew(0, 12, 31);
    }

    public function test_hebrew_diff(): void
    {
        $a = Hebrew::create(5785, 1, 1, 0, 0, 0, $this->utc); // 2024-10-03
        $b = Hebrew::create(5786, 1, 1, 0, 0, 0, $this->utc);

        $this->assertSame('2024-10-03', $a->toGregorian()->format('Y-m-d'));
        $this->assertSame(355, $a->diffInDays($b));
        $this->assertSame(-355, $a->diffInDays($b, false));
        $this->assertSame(355, $b->diffInDays($a, false));

        // 5785 is a regular year (12 months), 5784 a leap year (13).
        $this->assertSame(12, $a->diffInMonths($b));
        $this->assertSame(1, $a->diffInYears($b));
        $this->assertSame(-1, $a->diffInYears($b, false));
        $this->assertSame(11, $a->diffInMonths($b->subDays(1)));
        $this->assertSame(0, $a->diffInYears($b->subDays(1)));
    }

    public function test_hebrew_diff_across_leap_year_counts_adar_i_and_ii(): void
    {
        $this->assertTrue(Hebrew::isLeapYear(5784));
        $a = Hebrew::create(5784, 1, 1, 0, 0, 0, $this->utc);
        $b = Hebrew::create(5785, 1, 1, 0, 0, 0, $this->utc);

        $this->assertSame(13, $a->diffInMonths($b));
        $this->assertSame(1, $a->diffInYears($b));
        $this->assertSame(0, $a->diffInYears($b->subDays(1)));
    }

    public function test_hebrew_between_past_future_today(): void
    {
        $d = Hebrew::create(5785, 1, 10, 0, 0, 0, $this->utc);
        $lo = Hebrew::create(5785, 1, 1, 0, 0, 0, $this->utc);
        $hi = Hebrew::create(5785, 2, 1, 0, 0, 0, $this->utc);

        $this->assertTrue($d->between($lo, $hi));
        $this->assertTrue($d->between($hi, $lo));
        $this->assertFalse($d->between($d, $hi, false));
        $this->assertTrue($d->between(new DateTimeImmutable('2024-10-01', $this->utc), new DateTimeImmutable('2024-12-01', $this->utc)));

        $this->assertTrue(Hebrew::create(5700, 1, 1)->isPast());
        $this->assertTrue(Hebrew::create(6000, 1, 1)->isFuture());
        $a = Hebrew::now();
        $isToday = $a->isToday();
        if ($a->toDateString() === Hebrew::now()->toDateString()) { // midnight-safe: skip if the day rolled over
            $this->assertTrue($isToday);
        }
        $this->assertFalse(Hebrew::create(5700, 1, 1)->isToday());
    }

    public function test_hebrew_diff_accepts_datetime(): void
    {
        $a = Hebrew::create(5785, 1, 1, 0, 0, 0, $this->utc);
        $this->assertSame(2, $a->diffInDays(new DateTimeImmutable('2024-10-05', $this->utc)));
        $this->assertSame(0, $a->diffInMonths(new DateTimeImmutable('2024-10-05', $this->utc)));
    }

    /** @return array{int, int, int} */
    private static function ymd(Hebrew $h): array
    {
        return [$h->getYear(), $h->getMonth(), $h->getDay()];
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

    /**
     * @param Jalali|Hijri|Hebrew $d
     */
    private function ymdOf(object $d): string
    {
        return implode('/', $this->ymdArr($d));
    }

    private function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
