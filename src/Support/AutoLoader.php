<?php

declare(strict_types=1);

namespace RtlyKit\Support;

/**
 * Auto-registers optional integrations (Carbon macros, etc.).
 */
final class AutoLoader
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        // Register Carbon macros if Carbon is present
        CarbonMacros::register();
    }
}
