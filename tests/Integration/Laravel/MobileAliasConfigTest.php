<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Laravel\RtlyKitServiceProvider;

final class MobileAliasConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }

    /** @param array<string, mixed> $config */
    private function boot(array $config, bool $dropDefaults = false): Factory
    {
        $app = new class () extends Container {
            public function getNamespace(): string
            {
                return 'App\\';
            }
        };
        Container::setInstance($app);
        $app->instance(ContainerContract::class, $app);
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'), $app);
        // A rule of the same name that someone else registered first: it accepts only the text "other".
        $factory->extend('mobile', static fn (string $attribute, mixed $value): bool => $value === 'other');
        $app->instance('validator', $factory);
        $app->instance('config', new Repository($config));

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        if ($dropDefaults) {
            // Without the defaults that register() merged in, the option is missing and the built-in default applies.
            $app->instance('config', new Repository([]));
        }
        $provider->boot();

        return $factory;
    }

    public function test_the_alias_is_registered_by_default_and_replaces_an_earlier_rule(): void
    {
        $factory = $this->boot([]);

        self::assertTrue($factory->make(['m' => '09121234567'], ['m' => 'mobile'])->passes());
        self::assertFalse($factory->make(['m' => 'other'], ['m' => 'mobile'])->passes());
    }

    public function test_the_alias_can_be_turned_off_and_leaves_another_rule_alone(): void
    {
        $factory = $this->boot(['rtly-kit' => ['validation' => ['mobile_alias' => false]]]);

        self::assertTrue($factory->make(['m' => 'other'], ['m' => 'mobile'])->passes());
        self::assertFalse($factory->make(['m' => '09121234567'], ['m' => 'mobile'])->passes());
        self::assertTrue($factory->make(['m' => '09121234567'], ['m' => 'iran_mobile'])->passes());
    }

    public function test_boolean_like_strings_from_env_are_read(): void
    {
        foreach (['false' => false, '0' => false, 'off' => false, 'true' => true, '1' => true] as $text => $enabled) {
            $factory = $this->boot(['rtly-kit' => ['validation' => ['mobile_alias' => (string) $text]]]);
            self::assertSame($enabled, ! $factory->make(['m' => 'other'], ['m' => 'mobile'])->passes(), (string) $text);
        }
    }

    public function test_a_missing_option_means_the_alias_is_on(): void
    {
        $factory = $this->boot([], true);

        self::assertTrue($factory->make(['m' => '09121234567'], ['m' => 'mobile'])->passes());
    }

    public function test_integers_are_read_as_booleans_and_other_types_are_rejected(): void
    {
        $on = $this->boot(['rtly-kit' => ['validation' => ['mobile_alias' => 1]]]);
        self::assertTrue($on->make(['m' => '09121234567'], ['m' => 'mobile'])->passes());

        $off = $this->boot(['rtly-kit' => ['validation' => ['mobile_alias' => 0]]]);
        self::assertTrue($off->make(['m' => 'other'], ['m' => 'mobile'])->passes());

        foreach ([1.0, [true], null] as $bad) {
            if ($bad === null) {
                continue;
            }
            try {
                $this->boot(['rtly-kit' => ['validation' => ['mobile_alias' => $bad]]]);
                self::fail('Expected an InvalidDateException for '.get_debug_type($bad));
            } catch (InvalidDateException $e) {
                self::assertSame(['option' => 'rtly-kit.validation.mobile_alias'], $e->getContext());
            }
        }
    }

    public function test_a_value_that_is_not_a_boolean_is_rejected(): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('rtly-kit.validation.mobile_alias');
        $this->boot(['rtly-kit' => ['validation' => ['mobile_alias' => 'maybe']]]);
    }
}
