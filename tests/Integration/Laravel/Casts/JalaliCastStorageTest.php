<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel\Casts;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Laravel\Casts\JalaliCast;

final class JalaliCastStorageTest extends TestCase
{
    private string $previousZone;

    protected function setUp(): void
    {
        $this->previousZone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousZone);
    }

    /**
     * Assigns $value to a model that declares the cast the way an application
     * does, then reloads a fresh model from the raw attribute.
     *
     * @return array{0: mixed, 1: mixed} the raw stored value and the reloaded attribute
     */
    private function reload(string $cast, mixed $value): array
    {
        $model = new class () extends Model {
            public string $castDef = '';

            public function getCasts(): array
            {
                return ['at' => $this->castDef];
            }
        };
        $model->castDef = $cast;
        $model->at = $value;
        $raw = $model->getAttributes()['at'];

        $fresh = new $model();
        $fresh->castDef = $cast;
        $fresh->setRawAttributes(['at' => $raw]);

        return [$raw, $fresh->at];
    }

    /** @return iterable<string, array{mixed}> */
    public static function inputs(): iterable
    {
        yield 'offset +03:30' => [new DateTimeImmutable('2025-03-21 10:00:00.123456+03:30')];
        yield 'tehran zone' => [new DateTimeImmutable('2025-03-21 10:00:00.5', new DateTimeZone('Asia/Tehran'))];
        yield 'new york summer' => [new DateTimeImmutable('2025-07-01 23:59:59.999999', new DateTimeZone('America/New_York'))];
        yield 'jalali' => [Jalali::make(new DateTimeImmutable('2025-03-21 10:00:00.250000+05:00'))];
        yield 'timestamp' => [1_742_551_200];
    }

    #[DataProvider('inputs')]
    public function test_utc_storage_keeps_the_instant(mixed $input): void
    {
        foreach (['UTC', 'Asia/Tehran', 'America/New_York'] as $appZone) {
            date_default_timezone_set($appZone);
            $expected = Jalali::make($input);
            [$raw, $loaded] = $this->reload(JalaliCast::class.':UTC,6', $input);

            self::assertSame($expected->format('U.u'), $loaded->format('U.u'), "app zone {$appZone}, stored {$raw}");
            self::assertSame($expected->toGregorian()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'), $raw);
        }
    }

    public function test_utc_storage_converts_the_offset_input(): void
    {
        date_default_timezone_set('UTC');
        [$raw, $loaded] = $this->reload(JalaliCast::class.':UTC', new DateTimeImmutable('2025-03-21 10:00:00+03:30'));

        self::assertSame('2025-03-21 06:30:00', $raw);
        self::assertSame('2025-03-21 06:30:00', $loaded->toGregorian()->format('Y-m-d H:i:s'));
    }

    public function test_the_loaded_value_is_shown_in_the_default_zone(): void
    {
        date_default_timezone_set('Asia/Tehran');
        [, $loaded] = $this->reload(JalaliCast::class.':UTC', new DateTimeImmutable('2025-03-21 06:30:00+00:00'));

        self::assertSame('Asia/Tehran', $loaded->getTimezone()->getName());
        self::assertSame('10:00', $loaded->format('H:i'));
    }

    public function test_an_ambiguous_dst_hour_keeps_both_instants(): void
    {
        date_default_timezone_set('America/New_York');
        $first = new DateTimeImmutable('2025-11-02 01:30:00-04:00');
        $second = new DateTimeImmutable('2025-11-02 01:30:00-05:00');

        [$rawFirst, $loadedFirst] = $this->reload(JalaliCast::class.':UTC', $first);
        [$rawSecond, $loadedSecond] = $this->reload(JalaliCast::class.':UTC', $second);

        self::assertSame('2025-11-02 05:30:00', $rawFirst);
        self::assertSame('2025-11-02 06:30:00', $rawSecond);
        self::assertSame($first->getTimestamp(), $loadedFirst->getTimestamp());
        self::assertSame($second->getTimestamp(), $loadedSecond->getTimestamp());
    }

    public function test_an_old_wall_clock_row_is_read_correctly_with_the_zone_it_was_written_in(): void
    {
        date_default_timezone_set('UTC');
        $model = new class () extends Model {};
        $cast = new JalaliCast('Asia/Tehran');

        $loaded = $cast->get($model, 'at', '2025-03-21 10:00:00', []);

        self::assertSame((new DateTimeImmutable('2025-03-21 10:00:00', new DateTimeZone('Asia/Tehran')))->getTimestamp(), $loaded->getTimestamp());
        self::assertSame('2025-03-21 10:00:00', $cast->set($model, 'at', $loaded, []));
    }

    public function test_the_app_argument_follows_the_default_zone(): void
    {
        date_default_timezone_set('Asia/Tehran');
        $input = new DateTimeImmutable('2025-03-21 06:30:00.5+00:00');
        [$raw, $loaded] = $this->reload(JalaliCast::class.':app,3', $input);

        self::assertSame('2025-03-21 10:00:00.500', $raw);
        self::assertSame($input->getTimestamp(), $loaded->getTimestamp());
        self::assertSame('500000', $loaded->format('u'));

        date_default_timezone_set('America/New_York');
        $cast = new JalaliCast('app');
        $model = new class () extends Model {};
        self::assertSame('2025-03-21 02:30:00', $cast->set($model, 'at', $input, []));
        self::assertSame($input->getTimestamp(), $cast->get($model, 'at', '2025-03-21 02:30:00', [])->getTimestamp());
    }

    public function test_precision_controls_the_fraction(): void
    {
        $value = new DateTimeImmutable('2025-03-21 10:00:00.123456', new DateTimeZone('UTC'));

        self::assertSame('2025-03-21 10:00:00', $this->reload(JalaliCast::class, $value)[0]);
        self::assertSame('2025-03-21 10:00:00', $this->reload(JalaliCast::class.':UTC', $value)[0]);
        self::assertSame('2025-03-21 10:00:00.123', $this->reload(JalaliCast::class.':UTC,3', $value)[0]);
        self::assertSame('2025-03-21 10:00:00.123456', $this->reload(JalaliCast::class.':,6', $value)[0]);
        self::assertSame('123456', $this->reload(JalaliCast::class.':UTC,6', $value)[1]->format('u'));
    }

    public function test_default_behaviour_is_unchanged(): void
    {
        date_default_timezone_set('UTC');
        $model = new class () extends Model {};

        self::assertSame('2025-03-21 10:00:00', (new JalaliCast())->set($model, 'at', new DateTimeImmutable('2025-03-21 10:00:00+03:30'), []));
    }

    public function test_null_and_empty_stay_null(): void
    {
        self::assertNull($this->reload(JalaliCast::class.':UTC,6', null)[0]);
        self::assertNull($this->reload(JalaliCast::class.':UTC,6', '')[0]);
    }

    public function test_the_first_and_last_supported_dates_survive_any_storage_zone(): void
    {
        date_default_timezone_set('UTC');
        $first = Jalali::create(Jalali::MIN_YEAR, 1, 1, 0, 0, 0, new DateTimeZone('UTC'));
        $last = Jalali::create(Jalali::MAX_YEAR, 12, 29, 23, 59, 59, new DateTimeZone('UTC'));

        foreach (['Pacific/Kiritimati', 'Pacific/Pago_Pago', '+14:00', '-12:00'] as $zone) {
            foreach ([$first, $last] as $date) {
                [$raw, $loaded] = $this->reload(JalaliCast::class.':'.$zone, $date);

                self::assertSame($date->getTimestamp(), $loaded->getTimestamp(), "{$zone} {$raw}");
            }
        }
    }

    public function test_get_reads_timestamps_and_date_objects(): void
    {
        $cast = new JalaliCast();
        $model = new class () extends Model {};

        self::assertSame(1_742_551_200, $cast->get($model, 'at', 1_742_551_200, [])->getTimestamp());
        self::assertSame(1_742_551_200, $cast->get($model, 'at', new DateTimeImmutable('@1742551200'), [])->getTimestamp());
    }

    #[DataProvider('badArguments')]
    public function test_bad_arguments_throw(string $zone, int|string $precision): void
    {
        $this->expectException(InvalidDateException::class);
        new JalaliCast($zone, $precision);
    }

    /** @return iterable<string, array{string, int|string}> */
    public static function badArguments(): iterable
    {
        yield 'unknown zone' => ['Mars/Base', 0];
        yield 'precision 7' => ['UTC', '7'];
        yield 'precision text' => ['UTC', 'x'];
        yield 'precision negative int' => ['UTC', -1];
        yield 'precision 10' => ['UTC', '10'];
    }

    public function test_oversized_text_fails_with_a_bounded_error(): void
    {
        $cast = new JalaliCast();
        $model = new class () extends Model {};

        foreach (['set', 'get'] as $method) {
            try {
                $cast->{$method}($model, 'at', str_repeat('۱', 200), []);
                self::fail('Expected an exception.');
            } catch (InvalidDateException $e) {
                self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
                self::assertLessThan(200, strlen($e->getMessage()));
            }
        }
    }
}
