<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel\Casts;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Laravel\Casts\JalaliCast;
use RtlyKit\Tests\Integration\Laravel\Casts\Fixtures\EventModel;

/**
 * JalaliCast against real databases. SQLite always runs. MySQL and PostgreSQL run
 * when RTLY_TEST_MYSQL_HOST / RTLY_TEST_PGSQL_HOST name a server (see the `php-db`
 * service in tools/docker-compose.yml); otherwise those cases are skipped.
 *
 * With RTLY_TEST_REQUIRE_DB=1 (the CI job `test-database`) a missing server or driver
 * fails the test instead of skipping it, so a green run proves the server paths ran.
 */
final class JalaliCastDatabaseTest extends TestCase
{
    private ?Capsule $capsule = null;

    private string $previousZone;

    protected function setUp(): void
    {
        $this->previousZone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousZone);
        if ($this->capsule !== null) {
            $this->capsule->getConnection()->getSchemaBuilder()->dropIfExists('events');
            $this->capsule->getDatabaseManager()->purge();
        }
        Model::unsetConnectionResolver();
    }

    /** @return iterable<string, array{string}> */
    public static function drivers(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
        yield 'pgsql' => ['pgsql'];
    }

    /** @param array<string, mixed> $override */
    private function connect(string $driver, array $override = []): void
    {
        $config = match ($driver) {
            'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'mysql' => ['driver' => 'mysql', 'host' => getenv('RTLY_TEST_MYSQL_HOST') ?: '', 'database' => 'rtly', 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'timezone' => '+00:00'],
            default => ['driver' => 'pgsql', 'host' => getenv('RTLY_TEST_PGSQL_HOST') ?: '', 'database' => 'rtly', 'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer'],
        };
        $config = array_merge($config, $override);
        $required = getenv('RTLY_TEST_REQUIRE_DB') === '1';
        if (($config['host'] ?? 'x') === '') {
            $why = "No {$driver} server (set RTLY_TEST_".strtoupper($driver).'_HOST).';
            $required ? self::fail($why) : self::markTestSkipped($why);
        }
        if (! extension_loaded('pdo_'.$driver)) {
            $why = "The pdo_{$driver} extension is not loaded.";
            $required ? self::fail($why) : self::markTestSkipped($why);
        }

        $capsule = new Capsule();
        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->capsule = $capsule;

        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->dropIfExists('events');
        $schema->create('events', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('a');
            $table->dateTime('at', 6)->nullable();
            $table->timestamp('seen', 6)->nullable();
        });
    }

    /** @param array<string, string> $casts */
    private function model(array $casts): EventModel
    {
        EventModel::$castDef = $casts;

        return new EventModel();
    }

    private function fresh(EventModel $model, int $id): EventModel
    {
        /** @var EventModel $found */
        $found = EventModel::query()->findOrFail($id);

        return $found;
    }

    #[DataProvider('drivers')]
    public function test_utc_storage_keeps_the_instant_through_the_database(string $driver): void
    {
        $this->connect($driver);
        $casts = ['at' => JalaliCast::class.':UTC,6', 'seen' => JalaliCast::class.':UTC,6'];
        $input = new DateTimeImmutable('2025-03-21 10:00:00.123456+03:30');

        foreach (['UTC', 'Asia/Tehran', 'America/New_York'] as $zone) {
            date_default_timezone_set($zone);
            $event = $this->model($casts);
            $event->at = $input;
            $event->seen = $input;
            $event->save();

            $loaded = $this->fresh($event, (int) $event->getKey());
            foreach (['at', 'seen'] as $column) {
                self::assertInstanceOf(Jalali::class, $loaded->{$column}, "{$driver} {$column}");
                self::assertSame($input->format('U.u'), $loaded->{$column}->format('U.u'), "{$driver} {$column} in {$zone}");
            }
        }
    }

    #[DataProvider('drivers')]
    public function test_saving_another_column_leaves_the_stored_fraction_alone(string $driver): void
    {
        $this->connect($driver);
        $casts = ['at' => JalaliCast::class];

        $event = $this->model($casts);
        $event->setRawAttributes(['title' => 'a', 'at' => '2024-03-20 12:00:00.123456']);
        $event->save();

        $loaded = $this->fresh($event, (int) $event->getKey());
        self::assertInstanceOf(Jalali::class, $loaded->at);
        $loaded->title = 'b';
        $loaded->save();

        $stored = (string) $this->capsule?->getConnection()->table('events')->where('id', $event->getKey())->value('at');
        self::assertStringStartsWith('2024-03-20 12:00:00.123456', $stored, $driver);
    }

    #[DataProvider('drivers')]
    public function test_wall_clock_mode_and_null(string $driver): void
    {
        $this->connect($driver);
        date_default_timezone_set('UTC');
        $casts = ['at' => JalaliCast::class];

        $event = $this->model($casts);
        $event->at = Jalali::create(1404, 1, 1, 10, 30, 15);
        $event->save();
        $loaded = $this->fresh($event, (int) $event->getKey());
        self::assertSame('1404/01/01 10:30:15', $loaded->at->format('Y/m/d H:i:s'), $driver);

        $none = $this->model($casts);
        $none->save();
        self::assertNull($this->fresh($none, (int) $none->getKey())->at);

        $loaded->at = null;
        $loaded->save();
        self::assertNull($this->fresh($event, (int) $event->getKey())->at);
    }

    #[DataProvider('drivers')]
    public function test_a_query_by_the_stored_text_finds_the_row(string $driver): void
    {
        $this->connect($driver);
        date_default_timezone_set('Asia/Tehran');
        $casts = ['at' => JalaliCast::class.':UTC'];

        $event = $this->model($casts);
        $event->at = new DateTimeImmutable('2025-03-21 06:30:00', new DateTimeZone('UTC'));
        $event->save();

        $found = EventModel::query()->where('at', '2025-03-21 06:30:00')->first();
        self::assertNotNull($found, $driver);
        self::assertSame('10:00', $found->at->format('H:i'));
    }
    public function test_every_server_is_configured_when_the_run_requires_them(): void
    {
        if (getenv('RTLY_TEST_REQUIRE_DB') !== '1') {
            self::markTestSkipped('Only checked when RTLY_TEST_REQUIRE_DB=1.');
        }

        foreach (['MYSQL' => 'pdo_mysql', 'PGSQL' => 'pdo_pgsql'] as $name => $extension) {
            self::assertNotSame('', (string) getenv('RTLY_TEST_'.$name.'_HOST'), "RTLY_TEST_{$name}_HOST");
            self::assertTrue(extension_loaded($extension), $extension);
        }
    }

    /**
     * A MySQL `timestamp` column converts through the session time zone, so the value
     * is exact only while writer and reader use the same session zone; a `datetime`
     * column does not depend on it. This pins down what the guides say about it.
     */
    public function test_a_mysql_timestamp_column_depends_on_the_session_time_zone(): void
    {
        $this->connect('mysql', ['timezone' => '+05:30']);
        date_default_timezone_set('UTC');
        $casts = ['at' => JalaliCast::class.':UTC,6', 'seen' => JalaliCast::class.':UTC,6'];
        $input = new DateTimeImmutable('2025-03-21 10:00:00.5', new DateTimeZone('UTC'));

        $event = $this->model($casts);
        $event->at = $input;
        $event->seen = $input;
        $event->save();

        $same = $this->fresh($event, (int) $event->getKey());
        self::assertSame($input->format('U.u'), $same->at->format('U.u'));
        self::assertSame($input->format('U.u'), $same->seen->format('U.u'));

        $capsule = $this->capsule;
        self::assertNotNull($capsule);
        $capsule->addConnection(array_merge($capsule->getConnection()->getConfig(), ['timezone' => '+00:00']), 'utc');
        $other = $capsule->getConnection('utc')->table('events')->where('id', $event->getKey())->first();
        self::assertNotNull($other);

        self::assertSame('2025-03-21 10:00:00.500000', $other->at, 'datetime does not depend on the session');
        self::assertSame('2025-03-21 04:30:00.500000', $other->seen, 'timestamp is converted to the session zone');
    }
}
