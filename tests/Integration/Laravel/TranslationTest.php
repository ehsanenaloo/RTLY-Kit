<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use RtlyKit\Laravel\RtlyKitServiceProvider;

final class TranslationTest extends TestCase
{
    private Container $app;

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }

    public function test_messages_follow_locale(): void
    {
        $translator = $this->realTranslator('fa');
        $factory = $this->boot($translator);

        self::assertSame('field کد پستی معتبر نیست.', $this->message($factory, 'postal_code'));

        $translator->setLocale('ar');
        self::assertSame('field ليس رمزاً بريدياً إيرانياً صالحاً.', $this->message($factory, 'postal_code'));

        $translator->setLocale('en');
        self::assertSame('The field is not a valid Iranian postal code.', $this->message($factory, 'postal_code'));
    }

    public function test_every_rule_has_a_line_in_every_locale(): void
    {
        $rules = ['national_code', 'sheba', 'bank_card', 'iran_mobile', 'mobile', 'postal_code', 'vehicle_plate'];

        foreach (['fa', 'en', 'ar'] as $locale) {
            $translator = $this->realTranslator($locale);
            $factory = $this->boot($translator);
            foreach ($rules as $rule) {
                self::assertNotSame('rtly-kit::validation.'.$rule, $translator->get('rtly-kit::validation.'.$rule), "$locale/$rule");
                self::assertStringContainsString('field', $this->message($factory, $rule), "$locale/$rule");
            }
        }
    }

    public function test_unknown_locale_uses_fallback_locale(): void
    {
        $translator = $this->realTranslator('de');
        $translator->setFallback('fa');
        $factory = $this->boot($translator);

        self::assertSame('field کد ملی معتبر نیست.', $this->message($factory, 'national_code'));
    }

    public function test_custom_message_and_app_lines_win(): void
    {
        $translator = $this->realTranslator('fa');
        $factory = $this->boot($translator);

        self::assertSame('custom field', $this->message($factory, 'postal_code', ['postal_code' => 'custom :attribute']));

        $translator->addLines(['validation.postal_code' => 'app :attribute'], 'fa');
        self::assertSame('app field', $this->message($factory, 'postal_code'));
    }

    public function test_works_without_namespace_support(): void
    {
        // ArrayLoader ignores namespaces: the English fallback message is used.
        $factory = $this->boot(new Translator(new ArrayLoader(), 'fa'));

        self::assertSame('The field is not a valid Iranian postal code.', $this->message($factory, 'postal_code'));
    }

    private function boot(Translator $translator): Factory
    {
        $this->app = new class () extends Container {
            public function getNamespace(): string
            {
                return 'App\\';
            }
        };
        Container::setInstance($this->app);
        $this->app->instance(ContainerContract::class, $this->app);
        $this->app->instance('translator', $translator);
        $this->app->instance('validator', $factory = new Factory($translator, $this->app));

        $provider = new RtlyKitServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        return $factory;
    }

    private function realTranslator(string $locale): Translator
    {
        return new Translator(new FileLoader(new Filesystem(), sys_get_temp_dir().'/rtly-kit-no-app-lang'), $locale);
    }

    /**
     * @param  array<string, string>  $custom
     */
    private function message(Factory $factory, string $rule, array $custom = []): string
    {
        $v = $factory->make(['field' => 'bad'], ['field' => $rule], $custom);
        self::assertTrue($v->fails());

        return $v->errors()->first('field');
    }
}
