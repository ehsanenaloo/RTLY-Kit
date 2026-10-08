<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * Known-answer tests for Hijri calendar paths (format tokens, comparisons,
 * arithmetic borrow rules, invalid-month guards).
 * 2024-03-20 (Wednesday) = 1403/01/01 Jalali = 1445/09/10 Hijri = 10 Adar II 5784.
 */
final class HijriTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    public function test_umm_al_qura_known_anchors(): void
    {
        $this->assertSame('2025-03-01', Hijri::create(1446, 9, 1)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2025-06-26', Hijri::create(1447, 1, 1)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2024-04-10', Hijri::create(1445, 10, 1)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2024-07-07', Hijri::create(1446, 1, 1)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2023-03-23', Hijri::create(1444, 9, 1)->toGregorian()->format('Y-m-d'));

        $h = Hijri::make('2025-03-01');
        $this->assertSame([1446, 9, 1], [$h->getYear(), $h->getMonth(), $h->getDay()]);
        $this->assertSame(HijriVariant::UmmAlQura, $h->getVariant());
    }

    public function test_table_boundaries_and_consistency(): void
    {
        $this->assertSame('1882-11-12', Hijri::create(1300, 1, 1)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2077-11-16', Hijri::create(1500, 12, Hijri::daysInMonth(1500, 12))->toGregorian()->format('Y-m-d'));

        for ($y = 1300; $y <= 1500; $y++) {
            $days = Hijri::daysInYear($y);
            $this->assertContains($days, [354, 355], (string) $y);
            $this->assertSame($days === 355, Hijri::isLeapYear($y));
            for ($m = 1; $m <= 12; $m++) {
                $this->assertContains(Hijri::daysInMonth($y, $m), [29, 30]);
            }
        }
    }

    public function test_umm_al_qura_is_contiguous_and_roundtrips(): void
    {
        $d = new DateTimeImmutable('1882-11-12');
        $prev = null;
        // daily walk over the whole table: contiguity; round-trip on every 13th day
        for ($i = 0; $i < 71000; $i++, $d = $d->modify('+1 day')) {
            [$y, $m, $day] = Hijri::gregorianToHijri((int) $d->format('Y'), (int) $d->format('n'), (int) $d->format('j'));
            if ($prev !== null) {
                $ok = ($y === $prev[0] && $m === $prev[1] && $day === $prev[2] + 1)
                    || $day === 1 && (($m === $prev[1] + 1 && $y === $prev[0]) || ($m === 1 && $prev[1] === 12 && $y === $prev[0] + 1));
                $this->assertTrue($ok, $d->format('Y-m-d'));
            }
            if ($i % 13 === 0 && $y >= 1300 && $y <= 1500) {
                $this->assertSame(
                    $d->format('Y-n-j'),
                    implode('-', Hijri::hijriToGregorian($y, $m, $day)),
                );
            }
            $prev = [$y, $m, $day];
        }
    }

    public function test_tabular_variant(): void
    {
        // 1 Muharram 1 AH (civil epoch) = Friday 622-07-19 proleptic Gregorian
        $this->assertSame([622, 7, 19], Hijri::hijriToGregorian(1, 1, 1, HijriVariant::Tabular));
        $this->assertSame([1, 1, 1], Hijri::gregorianToHijri(622, 7, 19, HijriVariant::Tabular));

        $t = Hijri::create(1446, 9, 1, variant: HijriVariant::Tabular);
        $this->assertSame(HijriVariant::Tabular, $t->getVariant());
        $this->assertSame(HijriVariant::Tabular, $t->addDays(1)->getVariant());
        $this->assertSame(HijriVariant::Tabular, $t->addMonths(1)->getVariant());

        // 11 leap years per 30-year cycle, 10631 days
        $leaps = 0;
        $total = 0;
        for ($y = 1; $y <= 30; $y++) {
            $leaps += Hijri::isLeapYear($y, HijriVariant::Tabular) ? 1 : 0;
            $total += Hijri::daysInYear($y, HijriVariant::Tabular);
        }
        $this->assertSame(11, $leaps);
        $this->assertSame(10631, $total);

        for ($jd = 0; $jd < 6000; $jd++) {
            $g = (new DateTimeImmutable('1990-01-01'))->modify("+{$jd} days");
            [$y, $m, $d] = Hijri::gregorianToHijri((int) $g->format('Y'), (int) $g->format('n'), (int) $g->format('j'), HijriVariant::Tabular);
            $this->assertSame($g->format('Y-n-j'), implode('-', Hijri::hijriToGregorian($y, $m, $d, HijriVariant::Tabular)));
        }
    }

    public function test_outside_table_falls_back_to_tabular(): void
    {
        $this->assertFalse(Hijri::hasUmmAlQuraData(1299));
        $this->assertTrue(Hijri::hasUmmAlQuraData(1446));
        $h = Hijri::make('1700-01-01');
        $this->assertLessThan(1300, $h->getYear());
        $round = Hijri::create($h->getYear(), $h->getMonth(), $h->getDay());
        $this->assertSame('1700-01-01', $round->toGregorian()->format('Y-m-d'));
    }

    public function test_validation(): void
    {
        $this->assertTrue(Hijri::isValid(1446, 9, 29));
        $this->assertFalse(Hijri::isValid(1446, 13, 1));
        $this->assertFalse(Hijri::isValid(1446, 0, 1));
        $this->assertFalse(Hijri::isValid(0, 1, 1));
        $this->assertFalse(Hijri::isValid(1446, 1, 31));
        $this->assertFalse(Hijri::isValid(1446, 1, 0));

        $this->expectException(InvalidDateException::class);
        Hijri::create(1446, 2, 31);
    }

    public function test_invalid_time_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        Hijri::create(1446, 1, 1, 24);
    }

    public function test_comparisons_accept_same_type_and_datetime(): void
    {
        $a = Hijri::create(1446, 9, 1, 0, 0, 0, new DateTimeZone('UTC'));
        $b = $a->addDays(1);
        $this->assertTrue($a->eq($a));
        $this->assertTrue($a->lt($b));
        $this->assertTrue($b->gt($a));
        $this->assertTrue($a->ne($b));
        $this->assertTrue($a->lte($b) && $b->gte($a));
        $this->assertTrue($a->lt($b->toGregorian()));
        $this->assertTrue($a->eq($a->toGregorian()));
    }

    public function test_add_months_years_clamp(): void
    {
        $found = false;
        for ($m = 1; $m < 12; $m++) {
            if (Hijri::daysInMonth(1446, $m) === 30 && Hijri::daysInMonth(1446, $m + 1) === 29) {
                $r = Hijri::create(1446, $m, 30)->addMonths(1);
                $this->assertSame([1446, $m + 1, 29], [$r->getYear(), $r->getMonth(), $r->getDay()]);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        $r = Hijri::create(1446, 12, 1)->addMonths(1);
        $this->assertSame([1447, 1, 1], [$r->getYear(), $r->getMonth(), $r->getDay()]);
        $r = Hijri::create(1447, 1, 1)->subMonths(1);
        $this->assertSame([1446, 12, 1], [$r->getYear(), $r->getMonth(), $r->getDay()]);
        $r = Hijri::create(1446, 9, 5)->addYears(2);
        $this->assertSame([1448, 9, 5], [$r->getYear(), $r->getMonth(), $r->getDay()]);
        $r = Hijri::create(1446, 9, 5)->subYears(1);
        $this->assertSame([1445, 9, 5], [$r->getYear(), $r->getMonth(), $r->getDay()]);
        $r = Hijri::create(1446, 9, 5)->addMonths(-24);
        $this->assertSame([1444, 9, 5], [$r->getYear(), $r->getMonth(), $r->getDay()]);
    }

    public function test_start_end_of_periods(): void
    {
        $h = Hijri::create(1446, 9, 10, 13, 30, 0);
        $this->assertSame('1446/09/01 00:00:00', $h->startOfMonth()->toDateTimeString());
        $this->assertSame('1446/09/29 23:59:59', $h->endOfMonth()->toDateTimeString()); // Ramadan 1446 has 29 days (Eid 2025-03-30)
        $this->assertSame('1446/01/01 00:00:00', $h->startOfYear()->toDateTimeString());
        $last = Hijri::daysInMonth(1446, 12);
        $this->assertSame(sprintf('1446/12/%02d 23:59:59', $last), $h->endOfYear()->toDateTimeString());
        $this->assertSame('1446/09/10 00:00:00', $h->startOfDay()->toDateTimeString());
        $this->assertSame('1446/09/10 23:59:59', $h->endOfDay()->toDateTimeString());
    }

    public function test_format_and_names(): void
    {
        $h = Hijri::create(1446, 9, 1, 8, 5, 9);
        $this->assertSame('1446/09/01 08:05:09', $h->format());
        $this->assertSame('1446/09/01 08:05:09', (string) $h);
        $this->assertSame('1 رمضان 1446', $h->format('j F Y'));
        $this->assertSame('1 Ramadan 1446', $h->format('j F Y', 'en'));
        $this->assertSame('۱ رمضان ۱۴۴۶', $h->format('j F Y', 'fa', 'persian'));
        $this->assertSame('١٤٤٦/٠٩/٠١', $h->format('Y/m/d', 'ar', 'arabic'));
        $this->assertSame('Saturday', $h->format('l', 'en')); // 2025-03-01
        $this->assertSame('6', $h->format('w'));
        $this->assertSame('29', $h->format('t'));
        $this->assertSame('Y1446', $h->format('\YY'));
        $this->assertSame('رمضان', Hijri::monthName(9));
        $this->assertSame('ذیحجه', Hijri::monthName(12, 'fa'));
        $this->assertSame('Dhu al-Hijjah', Hijri::monthName(12, 'en'));
    }

    public function test_make_variants_and_timezone(): void
    {
        $tz = new DateTimeZone('Asia/Riyadh');
        $h = Hijri::make(new DateTimeImmutable('2025-03-01 10:00:00', new DateTimeZone('UTC')), $tz);
        $this->assertSame('Asia/Riyadh', $h->getTimezone()->getName());
        $this->assertSame($h, Hijri::make($h));
        $this->assertSame(HijriVariant::Tabular, Hijri::now(null, HijriVariant::Tabular)->getVariant());
        $this->assertSame(1446, Hijri::make(1740787200)->getYear()); // 2025-03-01 UTC
    }

    public function test_hijri_make_semantics(): void
    {
        $this->assertSame('2025-03-01', Hijri::make('1446/09/01', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2025-03-01', Hijri::make('1446-9-1', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2025-03-01', Hijri::make('۱۴۴۶/۰۹/۰۱', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame([1446, 9, 1], $this->ymdArr(Hijri::make('2025-03-01', $this->utc)));
        $this->assertSame(HijriVariant::Tabular, Hijri::make('1446/09/01', $this->utc, HijriVariant::Tabular)->getVariant());
        $this->expectException(InvalidDateException::class);
        Hijri::make('1446/13/01');
    }

    /* ---------------- 7. Hijri table bounds ---------------- */

    public function test_umm_al_qura_table_bounds_are_1300_to_1500(): void
    {
        foreach ([1299 => false, 1300 => true, 1317 => true, 1318 => true, 1500 => true, 1501 => false] as $year => $expected) {
            $this->assertSame($expected, Hijri::hasUmmAlQuraData($year), (string) $year);
        }
        $this->assertTrue(Hijri::create(1446, 9, 1)->usesUmmAlQuraTable());
        $this->assertFalse(Hijri::create(1446, 9, 1, 0, 0, 0, null, HijriVariant::Tabular)->usesUmmAlQuraTable());
        // Outside the table creation still works (tabular extrapolation) and is flagged
        $far = Hijri::create(1600, 1, 1);
        $this->assertFalse($far->usesUmmAlQuraTable());
        $this->assertSame([1600, 1, 1], $this->ymdArr($far));
        $this->assertTrue(Hijri::isValid(1, 1, 1));
    }

    /* ---------------- Hijri::format ---------------- */

    public function test_hijri_format_every_token_afternoon(): void
    {
        $h = Hijri::make('2024-03-20 15:05:09', $this->utc());
        $expected = [
            'y' => '45', 'm' => '09', 'n' => '9', 'd' => '10', 'j' => '10', 'H' => '15', 'G' => '15', 'i' => '05',
            's' => '09', 'F' => 'Ramadan', 'M' => 'Ramadan', 'N' => '3',
            'z' => '245', // 1 Muharram 1445 = 2023-07-19; 2024-03-20 is 245 days later
            'a' => 'pm', 'A' => 'PM', 'g' => '3', 'h' => '03', 'S' => '', 'W' => '12',
            'c' => '1445-09-10T15:05:09+00:00', 'r' => 'Wed, 20 Mar 2024 15:05:09 +0000',
            'U' => '1710947109', 'e' => 'UTC', 'T' => 'UTC', 'P' => '+00:00', 'p' => 'Z', 'O' => '+0000',
            'Z' => '0', 'I' => '0', 'u' => '000000', 'v' => '000',
            't' => '30', 'L' => '0', 'w' => '3', 'l' => 'Wednesday', 'x' => 'x',
        ];

        foreach ($expected as $token => $want) {
            $this->assertSame($want, $h->format($token, 'en'), "token {$token}");
        }
    }

    public function test_hijri_meridiem_and_names_are_localised(): void
    {
        $pm = Hijri::make('2024-03-20 15:05:09', $this->utc());
        $am = Hijri::make('2024-03-20 03:05:09', $this->utc());

        $this->assertSame('م م', $pm->format('a A', 'ar'));
        $this->assertSame('ص ص', $am->format('a A', 'ar'));
        $this->assertSame('ب.ظ ب.ظ', $pm->format('a A', 'fa'));
        $this->assertSame('ق.ظ ق.ظ', $am->format('a A', 'fa'));
        $this->assertSame('الأربعاء رمضان', $pm->format('l F', 'ar'));
    }

    /* ---------------- diff borrow rules ---------------- */

    public function test_hijri_diff_in_months_borrows_when_day_of_month_not_reached(): void
    {
        $a = Hijri::create(1445, 1, 20);
        $b = Hijri::create(1445, 3, 10);

        $this->assertSame(1, $a->diffInMonths($b));   // 2 calendar months apart, but day 10 < day 20
        $this->assertSame(1, $b->diffInMonths($a));   // absolute by default
        $this->assertSame(1, $b->diffInMonths($a, false)); // sign is (this - other)
        $this->assertSame(-1, $a->diffInMonths($b, false));
        $this->assertSame(2, $a->diffInMonths(Hijri::create(1445, 3, 20)));
    }

    public function test_hijri_month_name_rejects_out_of_range_months(): void
    {
        $this->assertSame('Ramadan', Hijri::monthName(9, 'en'));

        foreach ([0, 13] as $m) {
            try {
                Hijri::monthName($m);
                $this->fail("month {$m} must be rejected");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_hijri_days_in_month_invalid_month_is_rejected(): void
    {
        $this->assertSame(30, Hijri::daysInMonth(1445, 9));
        $this->assertSame(29, Hijri::daysInMonth(1445, 10));

        foreach ([0, 13, -1] as $m) {
            try {
                Hijri::daysInMonth(1445, $m);
                $this->fail("month {$m} must be rejected");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_hijri_diff_in_days_and_sign(): void
    {
        $a = Hijri::create(1446, 9, 1, 0, 0, 0, $this->utc);
        $b = Hijri::create(1446, 10, 1, 0, 0, 0, $this->utc);

        $this->assertSame(29, $a->diffInDays($b));
        $this->assertSame(29, $b->diffInDays($a));
        $this->assertSame(-29, $a->diffInDays($b, false));
        $this->assertSame(29, $b->diffInDays($a, false));
        $this->assertSame(1, $a->diffInDays(new DateTimeImmutable('2025-03-02 00:00:00', $this->utc)));
    }

    public function test_hijri_diff_in_months_and_years(): void
    {
        $a = Hijri::create(1446, 9, 1, 0, 0, 0, $this->utc);
        $b = Hijri::create(1448, 9, 1, 0, 0, 0, $this->utc);

        $this->assertSame(24, $a->diffInMonths($b));
        $this->assertSame(2, $a->diffInYears($b));
        $this->assertSame(-24, $a->diffInMonths($b, false));
        $this->assertSame(24, $b->diffInMonths($a, false));
        $this->assertSame(-2, $a->diffInYears($b, false));

        // One day short of a month / a year.
        $this->assertSame(23, $a->diffInMonths($b->subDays(1)));
        $this->assertSame(1, $a->diffInYears($b->subDays(1)));
        $this->assertSame(0, $a->diffInMonths($a->addDays(28)));
    }

    public function test_hijri_variant_is_respected_for_foreign_instances(): void
    {
        $tab = Hijri::make('2025-03-01', $this->utc, HijriVariant::Tabular);
        $uq = Hijri::make('2025-05-01', $this->utc);

        $this->assertSame(61, $tab->diffInDays($uq));
        $this->assertSame(2, $tab->diffInMonths($uq));
        $this->assertSame(2, $tab->diffInMonths(new DateTimeImmutable('2025-05-01', $this->utc)));
    }

    public function test_hijri_between_past_future(): void
    {
        $d = Hijri::create(1446, 9, 15, 0, 0, 0, $this->utc);
        $lo = Hijri::create(1446, 9, 1, 0, 0, 0, $this->utc);
        $hi = Hijri::create(1446, 10, 1, 0, 0, 0, $this->utc);

        $this->assertTrue($d->between($lo, $hi));
        $this->assertTrue($d->between($hi, $lo));
        $this->assertTrue($d->between($d, $hi));
        $this->assertFalse($d->between($d, $hi, false));
        $this->assertTrue($d->between(new DateTimeImmutable('2025-03-01', $this->utc), new DateTimeImmutable('2025-05-01', $this->utc)));
        $this->assertFalse($lo->between($d, $hi));

        $this->assertTrue(Hijri::create(1440, 1, 1)->isPast());
        $this->assertFalse(Hijri::create(1440, 1, 1)->isFuture());
        $this->assertTrue(Hijri::create(1500, 1, 1)->isFuture());
        $this->assertFalse(Hijri::create(1500, 1, 1)->isPast());
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

    private function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
