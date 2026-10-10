<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Support\CarbonMacros;

/**
 * register() never replaces a macro that already exists under one of its names,
 * fills in the missing ones, and can be called any number of times.
 */
final class CarbonMacrosCollisionTest extends TestCase
{
    protected function setUp(): void
    {
        Carbon::resetMacros();
        CarbonImmutable::resetMacros();
    }

    protected function tearDown(): void
    {
        Carbon::resetMacros();
        CarbonImmutable::resetMacros();
        CarbonMacros::register();
    }

    public function test_an_existing_macro_is_kept_and_the_rest_are_registered(): void
    {
        Carbon::macro('toJalali', static fn (): string => 'application');
        CarbonImmutable::macro('jformat', static fn (): string => 'application immutable');

        CarbonMacros::register();

        self::assertSame('application', Carbon::parse('2026-03-21 UTC')->toJalali());
        self::assertSame('application immutable', CarbonImmutable::parse('2026-03-21 UTC')->jformat());

        // The macros the application did not define are ours.
        self::assertInstanceOf(Hijri::class, Carbon::parse('2026-03-21 UTC')->toHijri());
        foreach (['createFromJalali', 'toHijri', 'toHebrew', 'createFromHijri', 'createFromHebrew'] as $macro) {
            self::assertTrue(Carbon::hasMacro($macro), "Carbon::{$macro}");
            self::assertTrue(CarbonImmutable::hasMacro($macro), "CarbonImmutable::{$macro}");
        }
    }

    public function test_register_is_safe_to_repeat(): void
    {
        CarbonMacros::register();
        CarbonMacros::register();
        CarbonMacros::register();

        self::assertSame('1405/01/01', Carbon::parse('2026-03-21 UTC')->jformat('Y/m/d'));
        self::assertSame('1405/01/01', CarbonImmutable::parse('2026-03-21 UTC')->jformat('Y/m/d'));
    }
}
