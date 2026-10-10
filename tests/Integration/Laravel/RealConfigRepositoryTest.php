<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Laravel\RtlyKitServiceProvider;

/**
 * The provider's config merge against a real Illuminate\Config\Repository (not a stand-in).
 */
final class RealConfigRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    /**
     * @param  array<string, mixed>  $items
     */
    private function boot(array $items): Container
    {
        $app = new class () extends Container {
            public function getNamespace(): string
            {
                return 'App\\';
            }
        };
        $app->instance(ContainerContract::class, $app);
        $app->instance('config', new Repository($items));

        $provider = new RtlyKitServiceProvider($app);
        $provider->register();
        $provider->boot();

        return $app;
    }

    private static function repository(Container $app): Repository
    {
        $config = $app->make('config');
        self::assertInstanceOf(Repository::class, $config);

        return $config;
    }

    public function test_defaults_are_merged_into_an_empty_repository(): void
    {
        $config = self::repository($this->boot([]));

        self::assertSame(
            ['islamic_offset' => 0, 'hijri_month_starts' => [], 'extra' => [], 'removed' => [], 'use_official_data' => true],
            $config->get('rtly-kit.holidays'),
        );
        self::assertSame(0, $config->get('rtly-kit.holidays.islamic_offset'));
    }

    public function test_calendar_built_from_the_merged_defaults_equals_the_default_calendar(): void
    {
        $app = $this->boot([]);

        self::assertEquals(HolidayCalendar::default(), $app->make(HolidayCalendar::class));
        self::assertSame($app->make(HolidayCalendar::class), $app->make(HolidayCalendar::class));
    }

    public function test_user_holidays_override_the_defaults_and_unset_keys_keep_their_default(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => -1, 'extra' => ['1406/01/05' => 'Test day']]]]);
        $config = self::repository($app);

        self::assertSame(-1, $config->get('rtly-kit.holidays.islamic_offset'));
        $calendar = $app->make(HolidayCalendar::class);
        self::assertSame(['Test day'], $calendar->getTitles(1406, 1, 5));
        // Unset keys fall back to the defaults when the calendar is built.
        self::assertTrue($calendar->isHoliday(1406, 1, 1));
        self::assertEquals(
            $calendar,
            HolidayCalendar::fromArray(['islamic_offset' => -1, 'extra' => ['1406/01/05' => 'Test day']]),
        );
    }

    public function test_a_user_value_set_by_dot_key_survives_the_merge(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => ['use_official_data' => false]]]);

        self::assertFalse(self::repository($app)->get('rtly-kit.holidays.use_official_data'));
        self::assertEquals(
            HolidayCalendar::fromArray(['use_official_data' => false]),
            $app->make(HolidayCalendar::class),
        );
    }

    public function test_non_array_holidays_value_is_rejected_when_the_calendar_is_resolved(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => 'oops']]);

        self::assertSame('oops', self::repository($app)->get('rtly-kit.holidays'));
        $this->expectException(InvalidDateException::class);
        $app->make(HolidayCalendar::class);
    }

    public function test_islamic_offset_string_from_env_is_cast_to_int(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => '1']]]);

        self::assertEquals(HolidayCalendar::fromArray(['islamic_offset' => 1]), $app->make(HolidayCalendar::class));
    }

    public function test_bad_islamic_offset_string_is_rejected(): void
    {
        $app = $this->boot(['rtly-kit' => ['holidays' => ['islamic_offset' => 'abc']]]);

        $this->expectException(InvalidDateException::class);
        $app->make(HolidayCalendar::class);
    }
}
