<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RtlyKit\Globals;
use RtlyKit\Laravel\RtlyKitServiceProvider;

final class ProviderRegistersNoGlobalsTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_booting_the_provider_defines_no_global_function(): void
    {
        $app = new class () extends Container {
            public function getNamespace(): string
            {
                return 'App\\';
            }
        };
        $app->instance(ContainerContract::class, $app);
        $app->instance('validator', new Factory(new Translator(new ArrayLoader(), 'en'), $app));

        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        $provider->boot();

        foreach (Globals::NAMES as $name) {
            self::assertFalse(function_exists($name), "the provider must not define global {$name}()");
        }
    }
}
