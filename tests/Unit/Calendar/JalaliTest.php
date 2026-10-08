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
 * Known-answer contract for the arithmetic 33-year Jalali <-> Gregorian conversion.
 *
 * Expected values come from the calendar definition (leap years at residues
 * 1, 5, 9, 13, 17, 22, 26, 30 mod 33) and were cross-checked day by day
 * against the previous implementation over the whole supported range.
 * Also covers format tokens, boundaries, comparisons and arithmetic.
 * 2024-03-20 (Wednesday) = 1403/01/01 Jalali = 1445/09/10 Hijri = 10 Adar II 5784.
 */
final class JalaliTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    public function test_now_returns_instance(): void
    {
        $jalali = Jalali::now();

        $this->assertInstanceOf(Jalali::class, $jalali);
        $this->assertGreaterThan(1300, $jalali->getYear());
    }

    public function test_create_valid_date(): void
    {
        $jalali = Jalali::create(1403, 1, 1);

        $this->assertSame(1403, $jalali->getYear());
        $this->assertSame(1, $jalali->getMonth());
        $this->assertSame(1, $jalali->getDay());
    }

    public function test_invalid_date_throws_exception(): void
    {
        $this->expectException(InvalidDateException::class);

        Jalali::create(1403, 13, 1);
    }

    public function test_format(): void
    {
        $jalali = Jalali::create(1403, 7, 15, 14, 30, 0);

        $this->assertSame('1403/07/15 14:30:00', $jalali->format());
        $this->assertSame('1403-7-15', $jalali->format('Y-n-j'));
    }

    public function test_leap_year(): void
    {
        $this->assertTrue(Jalali::isLeapYear(1403));
        $this->assertFalse(Jalali::isLeapYear(1404));
    }

    public function test_days_in_month(): void
    {
        $this->assertSame(31, Jalali::daysInMonth(1403, 1));
        $this->assertSame(31, Jalali::daysInMonth(1403, 6));
        $this->assertSame(30, Jalali::daysInMonth(1403, 7));
        $this->assertSame(30, Jalali::daysInMonth(1403, 12)); // leap
        $this->assertSame(29, Jalali::daysInMonth(1404, 12)); // non-leap
    }

    public function test_add_and_sub_days(): void
    {
        $date = Jalali::create(1403, 1, 1);

        $this->assertSame('1403/01/02', $date->addDays(1)->toDateString());
        $this->assertSame('1402/12/29', $date->subDays(1)->toDateString());
    }

    public function test_add_months(): void
    {
        $date = Jalali::create(1403, 1, 31);

        $this->assertSame('1403/02/31', $date->addMonths(1)->toDateString());
    }

    public function test_start_and_end_of_month(): void
    {
        $date = Jalali::create(1403, 7, 15, 12, 0, 0);

        $this->assertSame('1403/07/01 00:00:00', (string) $date->startOfMonth());
        $this->assertSame('1403/07/30 23:59:59', (string) $date->endOfMonth());
    }

    public function test_comparison(): void
    {
        $a = Jalali::create(1403, 1, 1);
        $b = Jalali::create(1403, 1, 2);

        $this->assertTrue($a->lt($b));
        $this->assertTrue($b->gt($a));
        $this->assertTrue($a->eq($a));
        $this->assertTrue($a->between($a, $b));
    }

    public function test_gregorian_conversion_roundtrip(): void
    {
        $original = [2025, 3, 21]; // Nowruz 1404

        [$jy, $jm, $jd] = Jalali::gregorianToJalali(...$original);
        [$gy, $gm, $gd] = Jalali::jalaliToGregorian($jy, $jm, $jd);

        $this->assertSame($original[0], $gy);
        $this->assertSame($original[1], $gm);
        $this->assertSame($original[2], $gd);
    }

    public function test_to_gregorian(): void
    {
        $jalali = Jalali::create(1403, 1, 1);
        $gregorian = $jalali->toGregorian();

        $this->assertSame('2024-03-20', $gregorian->format('Y-m-d'));
    }

    public function test_nowruz_conversion(): void
    {
        $j = Jalali::create(1403, 1, 1);
        $this->assertSame('2024-03-20', $j->toGregorian()->format('Y-m-d'));

        $j2 = Jalali::create(1404, 1, 1);
        $this->assertSame('2025-03-21', $j2->toGregorian()->format('Y-m-d'));
    }

    public function test_roundtrip_many_dates(): void
    {
        $dates = [
            [2024, 1, 1],
            [2024, 3, 20],
            [2024, 6, 15],
            [2024, 12, 31],
            [2025, 3, 21],
            [2000, 1, 1],
            [1990, 6, 1],
        ];

        foreach ($dates as [$y, $m, $d]) {
            [$jy, $jm, $jd] = Jalali::gregorianToJalali($y, $m, $d);
            [$gy, $gm, $gd] = Jalali::jalaliToGregorian($jy, $jm, $jd);
            $this->assertSame([$y, $m, $d], [$gy, $gm, $gd], "Failed for $y-$m-$d");
        }
    }

    public function test_add_months_year_boundary(): void
    {
        $d = Jalali::create(1403, 11, 15);
        $this->assertSame('1404/02/15', $d->addMonths(3)->toDateString());
    }

    public function test_end_of_esfand_leap(): void
    {
        $d = Jalali::create(1403, 12, 1);
        $this->assertSame('1403/12/30 23:59:59', (string) $d->endOfMonth());
    }

    public function test_end_of_esfand_non_leap(): void
    {
        $d = Jalali::create(1404, 12, 1);
        $this->assertSame('1404/12/29 23:59:59', (string) $d->endOfMonth());
    }

    public function test_diff_in_days(): void
    {
        $a = Jalali::create(1403, 1, 1);
        $b = Jalali::create(1403, 1, 11);
        $this->assertSame(10, $a->diffInDays($b));
    }

    public function test_is_today(): void
    {
        $a = Jalali::now();
        $isToday = $a->isToday();
        if ($a->toDateString() === Jalali::now()->toDateString()) { // midnight-safe: skip if the day rolled over
            $this->assertTrue($isToday);
        }
        $this->assertFalse(Jalali::create(1400, 1, 1)->isToday());
    }

    public function test_immutable(): void
    {
        $a = Jalali::create(1403, 1, 1);
        $b = $a->addDays(5);
        $this->assertSame('1403/01/01', $a->toDateString());
        $this->assertSame('1403/01/06', $b->toDateString());
    }

    public function test_invalid_create_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::create(1403, 0, 1);
    }

    /**
     * @return array<string, array{int, int, int, string}>
     */
    public static function nowruzAnchors(): array
    {
        return [
            '1399' => [1399, 1, 1, '2020-03-20'],
            '1400' => [1400, 1, 1, '2021-03-21'],
            '1401' => [1401, 1, 1, '2022-03-21'],
            '1402' => [1402, 1, 1, '2023-03-21'],
            '1403' => [1403, 1, 1, '2024-03-20'],
            '1404' => [1404, 1, 1, '2025-03-21'],
            'esfand 30 of leap 1399' => [1399, 12, 30, '2021-03-20'],
            'esfand 30 of leap 1403' => [1403, 12, 30, '2025-03-20'],
            'unix epoch' => [1348, 10, 11, '1970-01-01'],
            'y2k' => [1378, 10, 11, '2000-01-01'],
        ];
    }

    #[DataProvider('nowruzAnchors')]
    public function test_known_anchors(int $jy, int $jm, int $jd, string $greg): void
    {
        $this->assertSame($greg, Jalali::create($jy, $jm, $jd)->toGregorian()->format('Y-m-d'));
        $g = explode('-', $greg);
        $this->assertSame([$jy, $jm, $jd], Jalali::gregorianToJalali((int) $g[0], (int) $g[1], (int) $g[2]));
    }

    public function test_leap_years(): void
    {
        foreach ([1399, 1403, 1408] as $leap) {
            $this->assertTrue(Jalali::isLeapYear($leap), (string) $leap);
        }
        foreach ([1400, 1401, 1402, 1404] as $common) {
            $this->assertFalse(Jalali::isLeapYear($common), (string) $common);
        }
        $this->assertSame(30, Jalali::daysInMonth(1399, 12));
        $this->assertSame(29, Jalali::daysInMonth(1400, 12));
    }

    public function test_leap_year_negative_follows_33_year_cycle(): void
    {
        // -32 = 1 (mod 33) -> leap; -1 = 32 (mod 33) -> not leap
        $this->assertTrue(Jalali::isLeapYear(-32));
        $this->assertFalse(Jalali::isLeapYear(-1));
        $this->assertSame(Jalali::isLeapYear(1403), Jalali::isLeapYear(1403 - 33 * 100));
    }

    public function test_create_rejects_bad_time_fields(): void
    {
        foreach ([[24, 0, 0], [0, 60, 0], [0, 0, 60], [-1, 0, 0], [0, -1, 0], [0, 0, -1]] as [$h, $i, $s]) {
            try {
                Jalali::create(1403, 1, 1, $h, $i, $s);
                $this->fail("Expected exception for {$h}:{$i}:{$s}");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('1403/01/01 23:59:59', (string) Jalali::create(1403, 1, 1, 23, 59, 59));
    }

    public function test_create_from_format(): void
    {
        $this->assertSame('1403/01/01 10:30:00', (string) Jalali::createFromFormat('Y/m/d H:i', '1403/01/01 10:30'));
        $this->assertSame('1403/01/01', Jalali::createFromFormat('Y/m/d', '۱۴۰۳/۰۱/۰۱')->toDateString());
        // Valid in Jalali although "February 31" does not exist in Gregorian
        $this->assertSame('1403/02/31', Jalali::createFromFormat('Y/m/d', '1403/02/31')->toDateString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function badFormats(): array
    {
        return [
            'trailing data'  => ['Y/m/d', '1403/01/01x'],
            'month 13'       => ['Y/m/d', '1403/13/01'],
            'day 32'         => ['Y/m/d', '1403/01/32'],
            'esfand 30 common year' => ['Y/m/d', '1404/12/30'],
            'mehr 31'        => ['Y/m/d', '1403/07/31'],
            'bad hour'       => ['Y/m/d H:i', '1403/01/01 25:00'],
            'no date fields' => ['H:i', '10:30'],
            'garbage'        => ['Y/m/d', 'hello'],
        ];
    }

    #[DataProvider('badFormats')]
    public function test_create_from_format_rejects(string $format, string $value): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::createFromFormat($format, $value);
    }

    public function test_make_with_jalali_respects_timezone(): void
    {
        $utc = Jalali::create(1403, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));
        $teh = Jalali::make($utc, new DateTimeZone('Asia/Tehran'));

        $this->assertSame($utc->getTimestamp(), $teh->getTimestamp());
        $this->assertSame('Asia/Tehran', $teh->getTimezone()->getName());
        $this->assertSame('1403/01/01 03:30:00', (string) $teh);
        $this->assertSame($utc, Jalali::make($utc));
    }

    public function test_diff_in_days_is_calendar_correct_across_dst(): void
    {
        $berlin = new DateTimeZone('Europe/Berlin');
        // 2024-03-30 12:00 -> 2024-03-31 12:00 is only 23 elapsed hours (DST starts)
        $a = Jalali::create(1403, 1, 11, 12, 0, 0, $berlin);
        $b = Jalali::create(1403, 1, 12, 12, 0, 0, $berlin);

        $this->assertSame('2024-03-30', $a->toGregorian()->format('Y-m-d'));
        $this->assertSame(1, $a->diffInDays($b));
        $this->assertSame(1, $b->diffInDays($a));
        $this->assertSame(-1, $a->diffInDays($b, false));
        $this->assertSame(1, $b->diffInDays($a, false));
        $this->assertSame(0, $a->diffInDays($a));
    }

    public function test_diff_in_days_across_leap_esfand(): void
    {
        $a = Jalali::create(1403, 12, 29);
        $b = Jalali::create(1404, 1, 1);
        $this->assertSame(2, $a->diffInDays($b)); // 1403 is leap: 29, 30, then 1/1
    }

    public function test_diff_in_months_honors_day_component(): void
    {
        $a = Jalali::create(1403, 1, 15);

        $this->assertSame(1, $a->diffInMonths(Jalali::create(1403, 3, 14)));
        $this->assertSame(2, $a->diffInMonths(Jalali::create(1403, 3, 15)));
        $this->assertSame(1, $a->diffInMonths(Jalali::create(1403, 3, 15, 0, 0, 0)->subDays(1)));
        $this->assertSame(0, $a->diffInMonths(Jalali::create(1403, 2, 14)));
        $this->assertSame(1, $a->diffInMonths(Jalali::create(1403, 2, 15)));
        // time of day counts
        $this->assertSame(0, Jalali::create(1403, 1, 15, 10)->diffInMonths(Jalali::create(1403, 2, 15, 9)));
    }

    public function test_diff_in_months_sign(): void
    {
        $early = Jalali::create(1403, 1, 15);
        $late  = Jalali::create(1403, 3, 15);

        $this->assertSame(2, $late->diffInMonths($early));
        $this->assertSame(2, $early->diffInMonths($late));
        $this->assertSame(2, $late->diffInMonths($early, false));
        $this->assertSame(-2, $early->diffInMonths($late, false));
    }

    public function test_diff_in_years_honors_month_and_day(): void
    {
        $a = Jalali::create(1403, 1, 1);

        $this->assertSame(1, $a->diffInYears(Jalali::create(1404, 12, 29)));
        $this->assertSame(2, $a->diffInYears(Jalali::create(1405, 1, 1)));
        $this->assertSame(0, Jalali::create(1403, 1, 1, 10)->diffInYears(Jalali::create(1404, 1, 1, 9)));
        $this->assertSame(-2, $a->diffInYears(Jalali::create(1405, 1, 1), false));
        $this->assertSame(2, Jalali::create(1405, 1, 1)->diffInYears($a, false));
    }

    public function test_diff_accepts_datetime(): void
    {
        $a = Jalali::create(1403, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));
        $this->assertSame(1, $a->diffInDays(new DateTimeImmutable('2024-03-21 00:00:00', new DateTimeZone('UTC'))));
        $this->assertSame(1, $a->diffInMonths(new DateTimeImmutable('2024-04-20 00:00:00', new DateTimeZone('UTC'))));
    }

    /* ---------------------------- format ---------------------------- */

    public function test_format_names_and_weekday(): void
    {
        // 1403/01/01 = Wednesday 2024-03-20
        $j = Jalali::create(1403, 1, 1, 13, 5, 9, new DateTimeZone('UTC'));

        $this->assertSame('چهارشنبه 1 فروردین 1403', $j->format('l j F Y'));
        $this->assertSame('فروردین', $j->format('M'));
        $this->assertSame('چ', $j->format('D'));
        $this->assertSame('4', $j->format('w'));
        $this->assertSame('5', $j->format('N'));
        $this->assertSame('31', $j->format('t'));
        $this->assertSame('0', $j->format('z'));
        $this->assertSame('1', $j->format('L'));
        $this->assertSame('03', $j->format('y'));
    }

    public function test_format_all_month_and_weekday_names(): void
    {
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        foreach ($months as $i => $name) {
            $this->assertSame($name, Jalali::create(1404, $i + 1, 1)->format('F'));
        }

        // Saturday 2024-03-23 = 1403/01/04 ... Friday = 1403/01/10
        $days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        foreach ($days as $i => $name) {
            $d = Jalali::create(1403, 1, 4 + $i);
            $this->assertSame($name, $d->format('l'));
            $this->assertSame((string) $i, $d->format('w'));
        }
    }

    public function test_format_day_of_year_and_month_length(): void
    {
        $this->assertSame('185', Jalali::create(1403, 6, 31)->format('z'));
        $this->assertSame('186', Jalali::create(1403, 7, 1)->format('z'));
        $this->assertSame('365', Jalali::create(1403, 12, 30)->format('z'));
        $this->assertSame('364', Jalali::create(1404, 12, 29)->format('z'));
        $this->assertSame('30', Jalali::create(1403, 12, 1)->format('t'));
        $this->assertSame('29', Jalali::create(1404, 12, 1)->format('t'));
        $this->assertSame('0', Jalali::create(1404, 12, 1)->format('L'));
    }

    public function test_format_twelve_hour_clock(): void
    {
        $tz = new DateTimeZone('UTC');

        $midnight = Jalali::create(1403, 1, 1, 0, 7, 0, $tz);
        $this->assertSame('12', $midnight->format('g'));
        $this->assertSame('12:07 ق.ظ', $midnight->format('h:i a'));
        $this->assertSame('قبل از ظهر', $midnight->format('A'));

        $afternoon = Jalali::create(1403, 1, 1, 13, 5, 0, $tz);
        $this->assertSame('1', $afternoon->format('g'));
        $this->assertSame('01:05 ب.ظ', $afternoon->format('h:i a'));
        $this->assertSame('بعد از ظهر', $afternoon->format('A'));
        $this->assertSame('13', $afternoon->format('G'));

        $this->assertSame('12', Jalali::create(1403, 1, 1, 12, 0, 0, $tz)->format('g'));
    }

    public function test_format_backslash_escaping(): void
    {
        $j = Jalali::create(1403, 1, 1, 8, 0, 0, new DateTimeZone('UTC'));

        $this->assertSame('Ym', $j->format('\Y\m'));
        $this->assertSame('Year 1403', $j->format('\Y\e\a\r Y'));
        $this->assertSame('1403\\', $j->format('Y\\\\'));
        $this->assertSame('1403', $j->format('Y\\'));
        $this->assertSame('1403-01-01T08', $j->format('Y-m-d\TH'));
    }

    public function test_format_does_not_replace_inside_substituted_values(): void
    {
        // The old strtr-based version was safe here; the tokenizer must be too.
        $this->assertSame('اسفند', Jalali::create(1403, 12, 1)->format('F'));
        $this->assertSame('1403/12/01 - اسفند', Jalali::create(1403, 12, 1)->format('Y/m/d - F'));
    }

    public function test_format_timestamp_and_timezone_tokens(): void
    {
        $j = Jalali::create(1403, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));

        $this->assertSame('1710892800', $j->format('U'));
        $this->assertSame('UTC', $j->format('e'));
        $this->assertSame('+03:30', Jalali::make($j, new DateTimeZone('Asia/Tehran'))->format('P'));
    }

    public function test_format_persian_digits(): void
    {
        $j = Jalali::create(1403, 1, 1, 9, 5, 7);

        $this->assertSame('۱۴۰۳/۰۱/۰۱', $j->format('Y/m/d', true));
        $this->assertSame('۱ فروردین ۱۴۰۳', $j->format('j F Y', true));
        $this->assertSame('1403/01/01', $j->format('Y/m/d'));
    }

    public function test_default_format_and_to_string_unchanged(): void
    {
        $j = Jalali::create(1403, 5, 9, 7, 8, 9);

        $this->assertSame('1403/05/09 07:08:09', $j->format());
        $this->assertSame('1403/05/09 07:08:09', (string) $j);
        $this->assertSame('1403/05/09', $j->toDateString());
    }

    /** @return iterable<string, array{int, int, int, int}> */
    public static function nowruzProvider(): iterable
    {
        yield '1399' => [1399, 2020, 3, 20];
        yield '1400' => [1400, 2021, 3, 21];
        yield '1401' => [1401, 2022, 3, 21];
        yield '1402' => [1402, 2023, 3, 21];
        yield '1403' => [1403, 2024, 3, 20];
        yield '1404' => [1404, 2025, 3, 21];
        yield '1405' => [1405, 2026, 3, 21];
        yield '1406' => [1406, 2027, 3, 21];
        yield '1407' => [1407, 2028, 3, 20];
        yield '1408' => [1408, 2029, 3, 20];
        yield '1409' => [1409, 2030, 3, 21];
        yield '1410' => [1410, 2031, 3, 21];
    }

    #[DataProvider('nowruzProvider')]
    public function test_nowruz_both_directions(int $jy, int $gy, int $gm, int $gd): void
    {
        $this->assertSame([$gy, $gm, $gd], Jalali::jalaliToGregorian($jy, 1, 1));
        $this->assertSame([$jy, 1, 1], Jalali::gregorianToJalali($gy, $gm, $gd));
    }

    public function test_day_before_nowruz_is_last_day_of_previous_year(): void
    {
        $this->assertSame([1403, 12, 30], Jalali::gregorianToJalali(2025, 3, 20));
        $this->assertSame([1402, 12, 29], Jalali::gregorianToJalali(2024, 3, 19));
    }

    /** @return iterable<string, array{int, int, int, int}> */
    public static function leapEsfandProvider(): iterable
    {
        yield '1399' => [1399, 2021, 3, 20];
        yield '1403' => [1403, 2025, 3, 20];
        yield '1408' => [1408, 2030, 3, 20];
    }

    #[DataProvider('leapEsfandProvider')]
    public function test_esfand_30_in_leap_years(int $jy, int $gy, int $gm, int $gd): void
    {
        $this->assertTrue(Jalali::isLeapYear($jy));
        $this->assertTrue(Jalali::isValid($jy, 12, 30));
        $this->assertSame([$gy, $gm, $gd], Jalali::jalaliToGregorian($jy, 12, 30));
        $this->assertSame([$jy, 12, 30], Jalali::gregorianToJalali($gy, $gm, $gd));
    }

    public function test_esfand_30_invalid_in_common_year(): void
    {
        $this->assertFalse(Jalali::isValid(1404, 12, 30));
    }

    public function test_year_one_and_around_zero(): void
    {
        $this->assertSame([622, 3, 21], Jalali::jalaliToGregorian(1, 1, 1));
        $this->assertSame([623, 3, 21], Jalali::jalaliToGregorian(1, 12, 30));
        $this->assertSame([621, 3, 21], Jalali::jalaliToGregorian(0, 1, 1));
        $this->assertSame([621, 3, 20], Jalali::jalaliToGregorian(-1, 12, 29));
    }

    public function test_min_year_edges(): void
    {
        $this->assertSame([1, 3, 21], Jalali::jalaliToGregorian(Jalali::MIN_YEAR, 1, 1));
        $this->assertSame([Jalali::MIN_YEAR, 1, 1], Jalali::gregorianToJalali(1, 3, 21));
        $this->assertSame([2, 3, 20], Jalali::jalaliToGregorian(Jalali::MIN_YEAR, 12, 29));
        $this->assertSame([2, 3, 21], Jalali::jalaliToGregorian(Jalali::MIN_YEAR + 1, 1, 1));
        $this->assertSame([Jalali::MIN_YEAR, 12, 29], Jalali::gregorianToJalali(2, 3, 20));
    }

    public function test_max_year_edges(): void
    {
        $this->assertTrue(Jalali::isLeapYear(Jalali::MAX_YEAR));
        $this->assertSame([9998, 3, 20], Jalali::jalaliToGregorian(Jalali::MAX_YEAR, 1, 1));
        $this->assertSame([9999, 3, 19], Jalali::jalaliToGregorian(Jalali::MAX_YEAR, 12, 29));
        $this->assertSame([9999, 3, 20], Jalali::jalaliToGregorian(Jalali::MAX_YEAR, 12, 30));
        $this->assertSame([Jalali::MAX_YEAR, 12, 30], Jalali::gregorianToJalali(9999, 3, 20));
        $this->assertSame([Jalali::MAX_YEAR + 1, 1, 1], Jalali::gregorianToJalali(9999, 3, 21));
        $this->assertSame([9378, 10, 10], Jalali::gregorianToJalali(9999, 12, 31));
    }

    public function test_year_outside_range_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::jalaliToGregorian(Jalali::MAX_YEAR + 1, 1, 1);
    }

    public function test_below_min_year_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        Jalali::jalaliToGregorian(Jalali::MIN_YEAR - 1, 1, 1);
    }

    public function test_round_trip_across_leap_cycle_boundaries(): void
    {
        foreach ([-620, -300, -33, -1, 0, 1, 32, 33, 34, 1000, 1403, 1404, 4000, 9376, 9377] as $year) {
            for ($month = 1; $month <= 12; $month++) {
                foreach ([1, Jalali::daysInMonth($year, $month)] as $day) {
                    [$gy, $gm, $gd] = Jalali::jalaliToGregorian($year, $month, $day);
                    $this->assertSame([$year, $month, $day], Jalali::gregorianToJalali($gy, $gm, $gd));
                }
            }
        }
    }

    public function test_consecutive_days_never_skip_or_repeat(): void
    {
        $prev = Jalali::jalaliToGregorian(1398, 1, 1);
        $prevTs = gmmktime(0, 0, 0, $prev[1], $prev[2], $prev[0]);
        for ($y = 1398; $y <= 1410; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                for ($d = 1; $d <= Jalali::daysInMonth($y, $m); $d++) {
                    if ($y === 1398 && $m === 1 && $d === 1) {
                        continue;
                    }
                    $g = Jalali::jalaliToGregorian($y, $m, $d);
                    $ts = gmmktime(0, 0, 0, $g[1], $g[2], $g[0]);
                    $this->assertSame(86400, $ts - $prevTs, "gap at $y/$m/$d");
                    $prevTs = $ts;
                }
            }
        }
    }

    public function test_format_length_cap(): void
    {
        $date = Jalali::create(1404, 1, 1);

        $this->assertSame(str_repeat('-', 256), $date->format(str_repeat('-', 256)));

        $this->expectException(InvalidDateException::class);
        $date->format(str_repeat('Y', 257));
    }

    /**
     * Every sampled day in the whole supported Jalali range converts back and forth
     * and stays inside Gregorian years 1..9999.
     */
    public function test_jalali_full_range_roundtrip(): void
    {
        for ($y = Jalali::MIN_YEAR; $y <= Jalali::MAX_YEAR; $y++) {
            foreach ([[1, 1], [6, 31], [7, 1], [12, Jalali::daysInMonth($y, 12)]] as [$m, $d]) {
                [$gy, $gm, $gd] = Jalali::jalaliToGregorian($y, $m, $d);
                $this->assertGreaterThanOrEqual(1, $gy);
                $this->assertLessThanOrEqual(9999, $gy);
                $this->assertSame([$y, $m, $d], Jalali::gregorianToJalali($gy, $gm, $gd), "{$y}/{$m}/{$d}");
            }
        }
    }

    /* ---------------- 3. make() string semantics ---------------- */

    public function test_jalali_make_reads_own_calendar_below_1700(): void
    {
        $this->assertSame('2025-03-20', Jalali::make('1403/12/30', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2025-03-20', Jalali::make('۱۴۰۳/۱۲/۳۰', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2025-03-20', Jalali::make('١٤٠٣-١٢-٣٠', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('2025-10-07 10:30:05', Jalali::make('1404/07/15 10:30:05', $this->utc)->toGregorian()->format('Y-m-d H:i:s'));
        $this->assertSame('2025-10-07', Jalali::make('1404-7-15', $this->utc)->toGregorian()->format('Y-m-d'));
        $this->assertSame('1404/07/15', Jalali::make('1404/07/15')->format('Y/m/d')); // the documented example
    }

    public function test_jalali_make_year_1700_and_up_is_gregorian(): void
    {
        $j = Jalali::make('2025-03-01', $this->utc);
        $this->assertSame([1403, 12, 11], $this->ymdArr($j));
        $this->assertSame([1403, 12, 11], $this->ymdArr(Jalali::make('2025/03/01', $this->utc)));
        $this->assertSame([1403, 12, 11], $this->ymdArr(Jalali::make('1 March 2025', $this->utc)));
    }

    public function test_jalali_make_invalid_own_calendar_dates_throw(): void
    {
        foreach (['1404/12/30', '1403/13/01', '1403/00/10', '1403/01/32', '1403/01/01 25:00:00'] as $bad) {
            try {
                Jalali::make($bad);
                $this->fail("{$bad} did not throw");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /* ---------------- 4. API parity ---------------- */

    public function test_jalali_new_accessors(): void
    {
        $j = Jalali::create(1404, 7, 15, 10, 20, 30, $this->utc); // Tuesday 2025-10-07
        $this->assertSame(3, $j->getDayOfWeek()); // Saturday-first: Sat 0, Sun 1, Mon 2, Tue 3
        $this->assertSame((int) $j->format('w'), $j->getDayOfWeek());
        $this->assertSame('مهر', $j->monthName());
        $this->assertSame(366, Jalali::daysInYear(1403));
        $this->assertSame(365, Jalali::daysInYear(1404));
    }

    public function test_jalali_format_extra_tokens(): void
    {
        $j = Jalali::create(1404, 7, 15, 10, 20, 30, $this->utc);
        $this->assertSame('1404-07-15T10:20:30+00:00', $j->format('c'));
        $this->assertSame('Tue, 07 Oct 2025 10:20:30 +0000', $j->format('r'));
        $this->assertSame('41', $j->format('W'));
        $this->assertSame('', $j->format('S'));
        $this->assertSame('15|', $j->format('j|S'));
        $this->assertSame('S', $j->format('\S'));
    }

    /* ---------------- Jalali ---------------- */

    public function test_jalali_time_getters(): void
    {
        $j = Jalali::make('2024-03-20 10:05:09', $this->utc());

        $this->assertSame(10, $j->getHour());
        $this->assertSame(5, $j->getMinute());
        $this->assertSame(9, $j->getSecond());
    }

    public function test_jalali_start_and_end_boundaries(): void
    {
        $j = Jalali::create(1403, 6, 15, 10, 5, 9, $this->utc());

        $this->assertSame('1403/06/15 00:00:00', $j->startOfDay()->format('Y/m/d H:i:s'));
        $this->assertSame('1403/06/15 23:59:59', $j->endOfDay()->format('Y/m/d H:i:s'));
        $this->assertSame('1403/06/01 00:00:00', $j->startOfMonth()->format('Y/m/d H:i:s'));
        $this->assertSame('1403/06/31 23:59:59', $j->endOfMonth()->format('Y/m/d H:i:s'));
        $this->assertSame('1403/01/01 00:00:00', $j->startOfYear()->format('Y/m/d H:i:s'));
        $this->assertSame('1403/12/30 23:59:59', $j->endOfYear()->format('Y/m/d H:i:s')); // 1403 is leap

        $this->assertSame('1404/12/29', Jalali::create(1404, 2, 2)->endOfYear()->format('Y/m/d'));
        $this->assertSame('1404/11/30', Jalali::create(1404, 11, 2)->endOfMonth()->format('Y/m/d'));
    }

    public function test_jalali_boundaries_keep_timezone(): void
    {
        $tz = new DateTimeZone('Asia/Tehran');
        $j = Jalali::create(1403, 6, 15, 10, 5, 9, $tz);

        $this->assertSame('Asia/Tehran', $j->endOfMonth()->getTimezone()->getName());
        $this->assertSame('Asia/Tehran', $j->startOfYear()->getTimezone()->getName());
    }

    public function test_jalali_comparisons(): void
    {
        $a = Jalali::create(1403, 1, 1, 0, 0, 0, $this->utc());
        $b = Jalali::create(1403, 1, 2, 0, 0, 0, $this->utc());

        $this->assertTrue($a->ne($b));
        $this->assertFalse($a->ne($a));
        $this->assertTrue($b->gt($a));
        $this->assertFalse($a->gt($a));
        $this->assertFalse($a->gt($b));
        $this->assertTrue($b->gte($a));
        $this->assertTrue($a->gte($a));
        $this->assertFalse($a->gte($b));
        // DateTimeInterface operands are accepted too
        $this->assertTrue($b->gt($a->toGregorian()));
        $this->assertTrue($a->gte($a->toGregorian()));
    }

    public function test_jalali_between_swaps_reversed_bounds_and_honours_equal_flag(): void
    {
        $lo = Jalali::create(1403, 1, 1, 0, 0, 0, $this->utc());
        $mid = Jalali::create(1403, 1, 5, 0, 0, 0, $this->utc());
        $hi = Jalali::create(1403, 1, 9, 0, 0, 0, $this->utc());

        $this->assertTrue($mid->between($lo, $hi));
        $this->assertTrue($mid->between($hi, $lo), 'reversed bounds are normalised');
        $this->assertTrue($lo->between($hi, $lo));
        $this->assertFalse($lo->between($hi, $lo, false), 'exclusive bounds drop the endpoints');
        $this->assertFalse($hi->between($lo, $hi, false));
        $this->assertTrue($mid->between($hi, $lo, false));
    }

    public function test_jalali_is_past_and_is_future_with_far_dates(): void
    {
        $this->assertTrue(Jalali::create(1300, 1, 1)->isPast());
        $this->assertFalse(Jalali::create(1300, 1, 1)->isFuture());
        $this->assertTrue(Jalali::create(9000, 1, 1)->isFuture());
        $this->assertFalse(Jalali::create(9000, 1, 1)->isPast());
    }

    public function test_jalali_today_is_midnight_of_a_current_day(): void
    {
        $today = Jalali::today($this->utc());

        $this->assertSame('00:00:00', $today->format('H:i:s'));
        $this->assertLessThanOrEqual(1, $today->diffInDays(Jalali::now($this->utc())));
        $this->assertSame('UTC', $today->getTimezone()->getName());
    }

    public function test_jalali_days_in_month_returns_zero_for_invalid_month(): void
    {
        $this->assertSame(0, Jalali::daysInMonth(1403, 0));
        $this->assertSame(0, Jalali::daysInMonth(1403, 13));
        $this->assertSame(31, Jalali::daysInMonth(1403, 6));
        $this->assertSame(30, Jalali::daysInMonth(1403, 7));
        $this->assertSame(30, Jalali::daysInMonth(1403, 12));
        $this->assertSame(29, Jalali::daysInMonth(1404, 12));
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
