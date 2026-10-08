<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Support\CarbonMacros;

/**
 * What CarbonMacros::register() installs, and that the Hijri variant chosen by the
 * caller reaches the calendar. 1 Muharram 1447 is 2025-06-26 in Umm al-Qura and
 * 2025-06-27 in the tabular calendar.
 */
final class CarbonMacrosRegistrationTest extends TestCase
{
    private const MACROS = ['toJalali', 'jformat', 'createFromJalali', 'toHijri', 'toHebrew', 'createFromHijri', 'createFromHebrew'];

    protected function tearDown(): void
    {
        CarbonMacros::register();
    }

    public function test_register_installs_every_macro_on_both_carbon_classes(): void
    {
        Carbon::resetMacros();
        CarbonImmutable::resetMacros();
        foreach (self::MACROS as $macro) {
            self::assertFalse(Carbon::hasMacro($macro), $macro);
            self::assertFalse(CarbonImmutable::hasMacro($macro), $macro);
        }

        CarbonMacros::register();

        foreach (self::MACROS as $macro) {
            self::assertTrue(Carbon::hasMacro($macro), "Carbon::{$macro}");
            self::assertTrue(CarbonImmutable::hasMacro($macro), "CarbonImmutable::{$macro}");
        }
    }

    public function test_create_from_hijri_uses_umm_al_qura_by_default_and_the_requested_variant(): void
    {
        CarbonMacros::register();

        $default = Carbon::createFromHijri(1447, 1, 1, 0, 0, 0, 'UTC');
        self::assertSame('2025-06-26', $default->format('Y-m-d'));

        $explicit = Carbon::createFromHijri(1447, 1, 1, 0, 0, 0, 'UTC', HijriVariant::UmmAlQura);
        self::assertSame('2025-06-26', $explicit->format('Y-m-d'));

        $tabular = Carbon::createFromHijri(1447, 1, 1, 0, 0, 0, 'UTC', HijriVariant::Tabular);
        self::assertSame('2025-06-27', $tabular->format('Y-m-d'));

        $immutable = CarbonImmutable::createFromHijri(1447, 1, 1, 0, 0, 0, 'UTC', HijriVariant::Tabular);
        self::assertSame('2025-06-27', $immutable->format('Y-m-d'));
    }

    public function test_to_hijri_uses_umm_al_qura_by_default_and_the_requested_variant(): void
    {
        CarbonMacros::register();
        $date = Carbon::parse('2025-06-26', 'UTC');

        self::assertSame('1447/01/01', $date->toHijri()->toDateString());
        self::assertSame('1446/12/29', $date->toHijri(HijriVariant::Tabular)->toDateString());
        self::assertSame(HijriVariant::UmmAlQura, $date->toHijri()->getVariant());
    }

    public function test_create_from_macros_default_to_midnight(): void
    {
        CarbonMacros::register();

        self::assertSame('00:00:00', Carbon::createFromJalali(1405, 1, 1, 0, 0, 0, 'UTC')->format('H:i:s'));
        self::assertSame('00:00:00', Carbon::createFromJalali(1405, 1, 1, tz: 'UTC')->format('H:i:s'));
        self::assertSame('00:00:00', Carbon::createFromHijri(1447, 10, 2, tz: 'UTC')->format('H:i:s'));
        self::assertSame('00:00:00', Carbon::createFromHebrew(5786, 7, 3, tz: 'UTC')->format('H:i:s'));
        self::assertSame('2026-03-21', Carbon::createFromHebrew(5786, 7, 3, tz: 'UTC')->format('Y-m-d'));
        self::assertSame('2026-03-21', Carbon::createFromHijri(1447, 10, 2, tz: 'UTC')->format('Y-m-d'));
    }
}
