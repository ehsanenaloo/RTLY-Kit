<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use RtlyKit\Laravel\RtlyKitServiceProvider;
use RtlyKit\Support\CarbonMacros;

/**
 * Booting the provider is what installs the Carbon macros in a Laravel app.
 */
final class RtlyKitServiceProviderBootTest extends TestCase
{
    protected function tearDown(): void
    {
        // Leave the macros installed for the other tests of this process.
        CarbonMacros::register();
    }

    public function test_boot_without_a_config_path_does_not_try_to_publish(): void
    {
        // A console container that knows no config path (not a full Laravel application).
        $app = new class () extends Container {
            public function runningInConsole(): bool
            {
                return true;
            }
        };
        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        $provider->boot();

        self::assertTrue(Carbon::hasMacro('toJalali'));
    }

    public function test_boot_registers_the_carbon_macros(): void
    {
        Carbon::resetMacros();
        CarbonImmutable::resetMacros();
        self::assertFalse(Carbon::hasMacro('toJalali'));

        $app = new Container();
        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        $provider->boot();

        foreach (['toJalali', 'jformat', 'createFromJalali', 'toHijri', 'toHebrew', 'createFromHijri', 'createFromHebrew'] as $macro) {
            self::assertTrue(Carbon::hasMacro($macro), "Carbon::{$macro}");
            self::assertTrue(CarbonImmutable::hasMacro($macro), "CarbonImmutable::{$macro}");
        }
        self::assertSame('1405/01/01', Carbon::parse('2026-03-21', 'UTC')->toJalali()->toDateString());
    }
}
