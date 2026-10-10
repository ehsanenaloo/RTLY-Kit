<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Laravel\Facades\Jalali as JalaliFacade;
use RtlyKit\Laravel\RtlyKitServiceProvider;

final class RtlyKitServiceProviderTest extends TestCase
{
    private Container $app;

    protected function setUp(): void
    {
        $this->app = new class () extends Container {
            public function getNamespace(): string
            {
                return 'App\\';
            }
        };
        Container::setInstance($this->app);
        $this->app->instance(ContainerContract::class, $this->app);
        $this->app->instance('validator', new Factory(new Translator(new ArrayLoader(), 'en'), $this->app));

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);

        $provider = new RtlyKitServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }

    /* ---------------- ServiceProvider ---------------- */

    public function test_provider_skips_rules_when_validator_is_not_a_validation_factory(): void
    {
        $app = new class () extends Container {
            public function getNamespace(): string
            {
                return 'App\\';
            }
        };
        $impostor = new \stdClass();
        $app->instance('validator', $impostor);

        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        $provider->boot();

        self::assertSame($impostor, $app->make('validator'), 'foreign validator is left untouched');
    }

    public function test_replacer_passes_text_through_when_instance_is_not_a_validator(): void
    {
        $app = new class () extends Container {
            public function getNamespace(): string
            {
                return 'App\\';
            }
        };
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'), $app);
        $app->instance('validator', $factory);

        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        $provider->boot();

        $replacers = (new \ReflectionProperty(Factory::class, 'replacers'))->getValue($factory);
        self::assertIsArray($replacers);
        self::assertArrayHasKey('national_code', $replacers);

        $out = $replacers['national_code']('The code is bad.', 'code', 'national_code', [], null);
        self::assertSame('The code is bad.', $out);
    }

    public function test_rules_accept_valid_values(): void
    {
        self::assertTrue($this->passes(
            ['n' => '0013542419', 'm' => '09121234567', 'm2' => '09121234567', 'p' => '1593715416'],
            ['n' => 'required|national_code', 'm' => 'iran_mobile', 'm2' => 'mobile', 'p' => 'postal_code'],
        ));
    }

    public function test_rules_reject_invalid_values(): void
    {
        self::assertFalse($this->passes(['n' => '1111111111'], ['n' => 'national_code']));
        self::assertFalse($this->passes(['c' => '0000000000000000'], ['c' => 'bank_card']));
        self::assertFalse($this->passes(['s' => 'IR000000000000000000000000'], ['s' => 'sheba']));
        self::assertFalse($this->passes(['m' => '12345'], ['m' => 'mobile']));
    }

    public function test_non_scalar_input_fails_instead_of_erroring(): void
    {
        self::assertFalse($this->passes(['n' => ['0013542419']], ['n' => 'national_code']));
    }

    public function test_numeric_input_is_accepted(): void
    {
        self::assertTrue($this->passes(['p' => 1593715416], ['p' => 'postal_code']));
    }

    public function test_facade_resolves_jalali(): void
    {
        self::assertInstanceOf(Jalali::class, JalaliFacade::create(1404, 1, 1));
        self::assertSame('1404/01/01', JalaliFacade::create(1404, 1, 1)->format('Y/m/d'));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     */
    private function passes(array $data, array $rules): bool
    {
        /** @var Factory $factory */
        $factory = $this->app['validator'];

        return $factory->make($data, $rules)->passes();
    }
}
