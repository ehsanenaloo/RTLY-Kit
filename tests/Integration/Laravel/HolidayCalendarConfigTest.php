<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Holiday\HolidaySource;
use RtlyKit\Laravel\RtlyKitServiceProvider;

/**
 * The provider binds one HolidayCalendar built from `config('rtly-kit.holidays')` and publishes the config file.
 */
final class HolidayCalendarConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    /**
     * @param  array<string, mixed>|null  $config  null = the app has no config repository
     */
    private function boot(?array $config): Container
    {
        $app = new class () extends Container {
            public bool $console = true;

            public function getNamespace(): string
            {
                return 'App\\';
            }

            public function runningInConsole(): bool
            {
                return $this->console;
            }

            public function configPath(string $path = ''): string
            {
                return '/app/config'.($path === '' ? '' : '/'.$path);
            }
        };
        $app->instance(ContainerContract::class, $app);
        if ($config !== null) {
            $app->instance('config', self::repository($config));
        }

        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        $provider->boot();

        return $app;
    }

    /**
     * @param  array<string, mixed>  $items
     */
    private static function repository(array $items): object
    {
        // A minimal stand-in for Illuminate\Config\Repository (which lives in a package this library does not require).
        return new class ($items) {
            /** @param array<string, mixed> $items */
            public function __construct(private array $items) {}

            public function get(string $key, mixed $default = null): mixed
            {
                $value = $this->items;
                foreach (explode('.', $key) as $segment) {
                    if (! is_array($value) || ! array_key_exists($segment, $value)) {
                        return $default;
                    }
                    $value = $value[$segment];
                }

                return $value;
            }

            public function set(string $key, mixed $value = null): void
            {
                $this->items[$key] = $value; // top-level keys only: all the provider needs
            }
        };
    }

    public function test_without_a_config_repository_the_default_calendar_is_bound(): void
    {
        $app = $this->boot(null);

        $calendar = $app->make(HolidayCalendar::class);
        self::assertEquals(HolidayCalendar::default(), $calendar);
        self::assertSame(['جشن نوروز', 'عید فطر'], $calendar->getTitles(1405, 1, 1));
    }

    public function test_the_calendar_is_a_singleton(): void
    {
        $app = $this->boot([]);

        self::assertSame($app->make(HolidayCalendar::class), $app->make(HolidayCalendar::class));
    }

    public function test_packaged_defaults_are_merged_into_the_app_config(): void
    {
        $app = $this->boot([]);

        $config = $app->make('config')->get('rtly-kit.holidays');
        self::assertSame(
            ['islamic_offset' => 0, 'hijri_month_starts' => [], 'extra' => [], 'removed' => [], 'use_official_data' => true],
            $config,
        );
        self::assertEquals(HolidayCalendar::default(), $app->make(HolidayCalendar::class));
    }

    public function test_config_values_build_the_calendar(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => [
            'islamic_offset' => 1,
            'hijri_month_starts' => ['1447-10' => '2026-03-22'],
            'extra' => ['1405/02/03' => 'روز شرکت'],
            'removed' => ['1405/01/12'],
            'use_official_data' => true,
        ]]]);

        $calendar = $app->make(HolidayCalendar::class);

        // month start: official Eid al-Fitr (1405/01/01) moves to 1405/01/02
        self::assertSame(['جشن نوروز'], $calendar->getTitles(1405, 1, 1));
        self::assertSame(['عید نوروز', 'عید فطر'], $calendar->getTitles(1405, 1, 2));
        // extra and removed
        self::assertSame(['روز شرکت'], $calendar->getTitles(1405, 2, 3));
        self::assertFalse($calendar->isHoliday(1405, 1, 12));
        // offset applies to estimated years only: 1406 Tasua estimate 1406/03/24 -> 25
        self::assertSame(['تاسوعای حسینی'], $calendar->getTitles(1406, 3, 25));
        self::assertSame([], $calendar->getTitles(1406, 3, 24));
        self::assertSame(HolidaySource::Official, $calendar->sourceOf(1405));
    }

    public function test_official_data_can_be_switched_off_from_config(): void
    {
        $calendar = $this->boot(['rtly-kit' => ['holidays' => ['use_official_data' => false]]])->make(HolidayCalendar::class);

        self::assertSame(HolidaySource::Estimated, $calendar->sourceOf(1405));
        self::assertSame(['ملی شدن صنعت نفت', 'عید فطر'], $calendar->getTitles(1404, 12, 29));
    }

    public function test_a_partial_published_file_falls_back_to_defaults_for_missing_keys(): void
    {
        $calendar = $this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => -1]]])->make(HolidayCalendar::class);

        self::assertSame(['تاسوعای حسینی'], $calendar->getTitles(1406, 3, 23));
        self::assertSame(['جشن نوروز', 'عید فطر'], $calendar->getTitles(1405, 1, 1));
    }

    public function test_invalid_config_fails_loudly_when_the_calendar_is_resolved(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => 9]]]);

        $this->expectException(InvalidDateException::class);
        $app->make(HolidayCalendar::class);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonArrayHolidays(): array
    {
        return ['string' => ['oops'], 'int' => [1], 'bool' => [true], 'float' => [1.5]];
    }

    #[DataProvider('nonArrayHolidays')]
    public function test_a_non_array_holidays_entry_fails_loudly_when_resolved(mixed $value): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => $value]]);

        try {
            $app->make(HolidayCalendar::class);
            self::fail('expected InvalidDateException');
        } catch (InvalidDateException $e) {
            self::assertStringContainsString('rtly-kit.holidays', $e->getMessage());
            self::assertSame(['option' => 'rtly-kit.holidays'], $e->getContext());
        }
    }

    public function test_a_missing_holidays_entry_uses_the_defaults(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => null]]);

        self::assertEquals(HolidayCalendar::default(), $app->make(HolidayCalendar::class));
    }

    public function test_an_int_like_string_islamic_offset_from_env_is_accepted(): void
    {
        $calendar = $this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => '-1']]])->make(HolidayCalendar::class);

        self::assertSame(['تاسوعای حسینی'], $calendar->getTitles(1406, 3, 23));
        self::assertEquals($this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => -1]]])->make(HolidayCalendar::class), $calendar);
    }

    #[DataProvider('badOffsets')]
    public function test_other_islamic_offset_values_are_rejected_when_resolved(mixed $offset): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => $offset]]]);

        $this->expectException(InvalidDateException::class);
        $app->make(HolidayCalendar::class);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function badOffsets(): array
    {
        return ['float' => [1.0], 'text' => ['abc'], 'decimal string' => ['1.5'], 'empty' => [''], 'too big' => ['4']];
    }

    public function test_the_config_file_is_publishable_with_the_rtly_kit_config_tag(): void
    {
        $this->boot([]);

        $paths = ServiceProvider::pathsToPublish(RtlyKitServiceProvider::class, 'rtly-kit-config');

        self::assertCount(1, $paths);
        self::assertSame('/app/config/rtly-kit.php', array_values($paths)[0]);
        $source = array_key_first($paths);
        self::assertFileExists($source);
        self::assertSame('rtly-kit.php', basename($source));
        self::assertContains('rtly-kit-config', ServiceProvider::publishableGroups());
    }

    public function test_the_shipped_config_file_matches_the_documented_shape(): void
    {
        $config = require RtlyKitServiceProvider::CONFIG_FILE;

        self::assertSame(['holidays'], array_keys($config));
        self::assertSame(
            ['islamic_offset', 'hijri_month_starts', 'extra', 'removed', 'use_official_data'],
            array_keys($config['holidays']),
        );
        self::assertEquals(HolidayCalendar::default(), HolidayCalendar::fromArray($config['holidays']));
    }
}
