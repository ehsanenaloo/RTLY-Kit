<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Support\CarbonMacros;

final class CarbonMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Carbon::class)) {
            $this->markTestSkipped('nesbot/carbon not installed');
        }

        CarbonMacros::register();
    }

    public function test_to_hijri_macro(): void
    {
        $this->needCarbon();

        $h = Carbon::parse('2025-03-01', 'UTC')->toHijri();
        $this->assertSame('1446/09/01', $h->toDateString());
        $this->assertSame(HijriVariant::UmmAlQura, $h->getVariant());

        $hi = CarbonImmutable::parse('2025-03-01', 'UTC')->toHijri(HijriVariant::Tabular);
        $this->assertSame(HijriVariant::Tabular, $hi->getVariant());
        $this->assertSame('2025-03-01', $hi->toGregorian()->format('Y-m-d'));
    }

    public function test_to_hebrew_macro(): void
    {
        $this->needCarbon();

        $this->assertSame('5785/01/01', Carbon::parse('2024-10-03', 'UTC')->toHebrew()->toDateString());
        $this->assertSame('5785/01/01', CarbonImmutable::parse('2024-10-03', 'UTC')->toHebrew()->toDateString());
    }

    public function test_create_from_hijri_returns_calling_class(): void
    {
        $this->needCarbon();

        $c = Carbon::createFromHijri(1446, 9, 1);
        $this->assertInstanceOf(Carbon::class, $c);
        $this->assertNotInstanceOf(CarbonImmutable::class, $c);
        $this->assertSame('2025-03-01 00:00:00', $c->format('Y-m-d H:i:s'));

        $ci = CarbonImmutable::createFromHijri(1446, 9, 1, 8, 30, 15, 'Asia/Riyadh');
        $this->assertInstanceOf(CarbonImmutable::class, $ci);
        $this->assertSame('2025-03-01 08:30:15', $ci->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Riyadh', $ci->getTimezone()->getName());

        $t = Carbon::createFromHijri(1446, 9, 1, 0, 0, 0, new DateTimeZone('UTC'), HijriVariant::Tabular);
        $this->assertSame('1446/09/01', $t->toHijri(HijriVariant::Tabular)->toDateString());
    }

    public function test_create_from_hebrew_returns_calling_class(): void
    {
        $this->needCarbon();

        $c = Carbon::createFromHebrew(5785, 1, 1);
        $this->assertInstanceOf(Carbon::class, $c);
        $this->assertSame('2024-10-03 00:00:00', $c->format('Y-m-d H:i:s'));

        $ci = CarbonImmutable::createFromHebrew(5785, 1, 1, 12, 0, 0, 'Asia/Jerusalem');
        $this->assertInstanceOf(CarbonImmutable::class, $ci);
        $this->assertSame('2024-10-03 12:00:00', $ci->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Jerusalem', $ci->getTimezone()->getName());
    }

    public function test_create_from_macros_reject_invalid_input(): void
    {
        $this->needCarbon();

        $this->expectException(InvalidDateException::class);
        Carbon::createFromHebrew(5785, 14, 1);
    }

    public function test_create_from_hijri_rejects_bad_timezone(): void
    {
        $this->needCarbon();

        $this->expectException(InvalidDateException::class);
        CarbonImmutable::createFromHijri(1446, 9, 1, 0, 0, 0, 'Not/AZone');
    }

    public function test_immutability_of_carbon_sources(): void
    {
        $this->needCarbon();

        $src = CarbonImmutable::parse('2025-03-01 10:00:00', 'UTC');
        $src->toHijri();
        $src->toHebrew();
        $this->assertSame('2025-03-01 10:00:00', $src->format('Y-m-d H:i:s'));
    }

    public function test_to_jalali(): void
    {
        $j = Carbon::parse('2024-03-20 10:00:00', 'UTC')->toJalali();
        $this->assertSame('1403/01/01', $j->toDateString());

        $ji = CarbonImmutable::parse('2025-03-21', 'UTC')->toJalali();
        $this->assertSame('1404/01/01', $ji->toDateString());
    }

    public function test_jformat(): void
    {
        $this->assertSame('1403/01/01', Carbon::parse('2024-03-20', 'UTC')->jformat('Y/m/d'));
        $this->assertSame('چهارشنبه 1 فروردین 1403', CarbonImmutable::parse('2024-03-20', 'UTC')->jformat('l j F Y'));
    }

    public function test_create_from_jalali_without_tz(): void
    {
        $c = Carbon::createFromJalali(1403, 1, 1);
        $this->assertInstanceOf(Carbon::class, $c);
        $this->assertSame('2024-03-20 00:00:00', $c->format('Y-m-d H:i:s'));

        $ci = CarbonImmutable::createFromJalali(1404, 1, 1, 8, 30);
        $this->assertInstanceOf(CarbonImmutable::class, $ci);
        $this->assertSame('2025-03-21 08:30:00', $ci->format('Y-m-d H:i:s'));
    }

    public function test_create_from_jalali_accepts_timezone_string(): void
    {
        $c = Carbon::createFromJalali(1403, 1, 1, 12, 0, 0, 'Asia/Tehran');
        $this->assertSame('Asia/Tehran', $c->getTimezone()->getName());
        $this->assertSame('2024-03-20 08:30:00', $c->copy()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_create_from_jalali_accepts_timezone_object(): void
    {
        $c = CarbonImmutable::createFromJalali(1403, 1, 1, 12, 0, 0, new DateTimeZone('Asia/Tehran'));
        $this->assertSame('Asia/Tehran', $c->getTimezone()->getName());
        $this->assertSame('2024-03-20 08:30:00', $c->utc()->format('Y-m-d H:i:s'));
    }

    public function test_create_from_jalali_invalid_timezone_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        Carbon::createFromJalali(1403, 1, 1, 0, 0, 0, 'Not/AZone');
    }

    public function test_zone_helper(): void
    {
        $this->assertNull(CarbonMacros::zone(null));
        $tz = new DateTimeZone('UTC');
        $this->assertSame($tz, CarbonMacros::zone($tz));
        $this->assertSame('Asia/Tehran', CarbonMacros::zone('Asia/Tehran')?->getName());
    }

    /* ---------------- Support ---------------- */

    public function test_carbon_macros_target_rejects_non_carbon_classes(): void
    {
        $this->assertSame(Carbon::class, CarbonMacros::target(Carbon::class));

        $this->expectException(RtlyKitException::class);
        CarbonMacros::target(\stdClass::class);
    }

    /* ---------------------------------------------------------------- Carbon */

    private function needCarbon(): void
    {
        if (! class_exists(Carbon::class)) {
            $this->markTestSkipped('nesbot/carbon not installed');
        }
        CarbonMacros::register();
    }
}
