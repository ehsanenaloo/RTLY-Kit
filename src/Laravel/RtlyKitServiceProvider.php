<?php

declare(strict_types=1);

namespace RtlyKit\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Support\CarbonMacros;
use RtlyKit\Validation\BankCard;
use RtlyKit\Validation\Mobile;
use RtlyKit\Validation\NationalCode;
use RtlyKit\Validation\PostalCode;
use RtlyKit\Validation\Sheba;
use RtlyKit\Validation\VehiclePlate;

/**
 * Registers the Jalali factory (used by the `Jalali` facade), Carbon macros
 * and the following validation rules:
 *
 * - `national_code`  Iranian national code (کد ملی)
 * - `sheba`          Iranian Sheba / IBAN (with or without the IR prefix)
 * - `bank_card`      16-digit bank card number (Luhn)
 * - `iran_mobile`    Iranian mobile number; `mobile` is a short alias
 * - `postal_code`    10-digit Iranian postal code
 * - `vehicle_plate`  Iranian vehicle plate, e.g. 12ب345-67
 *
 * Non-scalar input (arrays, objects, null) never reaches the validators and
 * simply fails the rule.
 */
class RtlyKitServiceProvider extends ServiceProvider
{
    public const FACTORY_ABSTRACT = 'rtly-kit.jalali';

    public const CONFIG_FILE = __DIR__.'/../../resources/config/rtly-kit.php';

    public const CONFIG_TAG = 'rtly-kit-config';

    public function register(): void
    {
        $this->app->singleton(self::FACTORY_ABSTRACT, static fn (): JalaliFactory => new JalaliFactory());

        // Defaults for the `rtly-kit` config (published with the `rtly-kit-config` tag), when the app has a config repository.
        if ($this->app->bound('config')) {
            $this->mergeConfigFrom(self::CONFIG_FILE, 'rtly-kit');
        }

        // One HolidayCalendar per app, built lazily from `rtly-kit.holidays`; a plain container gets the defaults.
        $this->app->singleton(HolidayCalendar::class, function (): HolidayCalendar {
            $config = $this->app->bound('config') ? $this->app->make('config') : null;
            $holidays = is_object($config) && method_exists($config, 'get') ? $config->get('rtly-kit.holidays') : null;

            if ($holidays !== null && ! is_array($holidays)) {
                throw InvalidDateException::because(
                    ErrorCode::InvalidArgument,
                    sprintf('Config "rtly-kit.holidays" must be an array, %s given.', get_debug_type($holidays)),
                    ['option' => 'rtly-kit.holidays'],
                );
            }

            return HolidayCalendar::fromArray($holidays ?? []);
        });
    }

    public function boot(): void
    {
        // Register Carbon macros
        CarbonMacros::register();

        // `php artisan vendor:publish --tag=rtly-kit-config` (only an application knows where its config path is).
        if (method_exists($this->app, 'runningInConsole') && method_exists($this->app, 'configPath') && $this->app->runningInConsole()) {
            $this->publishes([self::CONFIG_FILE => $this->app->configPath('rtly-kit.php')], self::CONFIG_TAG);
        }

        // Package messages (fa, en, ar) under the `rtly-kit` namespace; no publish step.
        // Override via lang/vendor/rtly-kit/{locale}/validation.php, the app's own
        // `validation.<rule>` lines, or custom messages.
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'rtly-kit');

        // Register custom validation rules if Validator is available
        if ($this->app->bound('validator')) {
            $this->registerValidationRules();
        }
    }

    private function registerValidationRules(): void
    {
        $validator = $this->app->make('validator');

        if (! $validator instanceof ValidationFactory) {
            return;
        }

        /** @var array<string, array{0: callable(mixed): bool, 1: string}> $rules */
        $rules = [
            'national_code' => [NationalCode::isValid(...), 'The :attribute is not a valid Iranian national code.'],
            'sheba' => [Sheba::isValid(...), 'The :attribute is not a valid Iranian Sheba (IBAN).'],
            'bank_card' => [BankCard::isValid(...), 'The :attribute is not a valid Iranian bank card.'],
            'iran_mobile' => [Mobile::isValid(...), 'The :attribute is not a valid Iranian mobile number.'],
            'mobile' => [Mobile::isValid(...), 'The :attribute is not a valid Iranian mobile number.'],
            'postal_code' => [PostalCode::isValid(...), 'The :attribute is not a valid Iranian postal code.'],
            'vehicle_plate' => [VehiclePlate::isValid(...), 'The :attribute is not a valid Iranian vehicle plate.'],
        ];

        foreach ($rules as $name => [$check, $message]) {
            $validator->extend($name, static function (string $attribute, mixed $value) use ($check): bool {
                // The validators read ints and whole floats themselves (a float is never turned into scientific notation).
                return (is_string($value) || is_int($value) || is_float($value)) && $check($value);
            }, $message);

            // Swap the English fallback for the translated package line, unless the
            // app supplied its own message or no translation is available.
            $validator->replacer($name, static function (string $text, string $attribute, string $rule, array $parameters, mixed $instance) use ($message, $name): string {
                if (! $instance instanceof Validator) {
                    return $text;
                }

                // :attribute has already been substituted in $text at this point.
                $display = $instance->getDisplayableAttribute($attribute);
                if ($text !== str_replace(':attribute', $display, $message)) {
                    return $text;
                }

                // The text equals the English default, which can also be the application's own line. Ask where the text
                // came from instead of trusting the string: an application line (`validation.<rule>`, also in the fallback
                // locale, or `validation.custom.<attribute>.<rule>`) or an inline custom message always wins.
                $translator = $instance->getTranslator();
                if (
                    $translator->get($appKey = 'validation.'.$name) !== $appKey
                    || $translator->get($customKey = 'validation.custom.'.$attribute.'.'.$name) !== $customKey
                    || in_array($message, $instance->customMessages, true)
                ) {
                    return $text;
                }

                $key = 'rtly-kit::validation.'.$name;
                $line = $translator->get($key);

                if (! is_string($line) || $line === $key) {
                    return $text;
                }

                return str_replace(':attribute', $display, $line);
            });
        }
    }
}
