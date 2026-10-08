<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Support;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use RtlyKit\Support\AutoLoader;
use RtlyKit\Support\CarbonMacros;

final class AutoLoaderTest extends TestCase
{
    public function test_autoloader_boot_registers_macros_once_and_is_idempotent(): void
    {
        $booted = new \ReflectionProperty(AutoLoader::class, 'booted');

        Carbon::resetMacros();
        $this->assertFalse(Carbon::hasMacro('toJalali'));

        $booted->setValue(null, false);
        AutoLoader::boot();

        $this->assertTrue($booted->getValue());
        $this->assertTrue(Carbon::hasMacro('toJalali'));

        // A second boot() must be a no-op: it must not re-register after a reset.
        Carbon::resetMacros();
        AutoLoader::boot();
        $this->assertFalse(Carbon::hasMacro('toJalali'));

        CarbonMacros::register(); // restore for the other tests
        $this->assertTrue(Carbon::hasMacro('toJalali'));
    }
}
