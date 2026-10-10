<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel\Casts;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Laravel\Casts\JalaliCast;

final class JalaliCastTest extends TestCase
{
    public function test_get_exposes_jalali(): void
    {
        $j = (new JalaliCast())->get($this->model(), 'at', '2025-03-21 10:30:15', []);

        self::assertInstanceOf(Jalali::class, $j);
        self::assertSame('1404/01/01 10:30:15', $j->format('Y/m/d H:i:s'));
    }

    public function test_get_null(): void
    {
        self::assertNull((new JalaliCast())->get($this->model(), 'at', null, []));
    }

    public function test_set_stores_gregorian(): void
    {
        $cast = new JalaliCast();
        $m = $this->model();

        self::assertSame('2025-03-21 10:30:15', $cast->set($m, 'at', Jalali::create(1404, 1, 1, 10, 30, 15), []));
        self::assertSame('2025-03-21 10:30:15', $cast->set($m, 'at', new \DateTimeImmutable('2025-03-21 10:30:15'), []));
        self::assertSame('2025-03-21 00:00:00', $cast->set($m, 'at', '1404/01/01', []));
        self::assertSame('2025-03-21 08:05:00', $cast->set($m, 'at', '۱۴۰۴-۰۱-۰۱ 08:05', []));
        self::assertSame('2025-03-21 10:30:15', $cast->set($m, 'at', '2025-03-21 10:30:15', []));
        self::assertNull($cast->set($m, 'at', null, []));
    }

    public function test_invalid_jalali_string_throws(): void
    {
        $this->expectException(InvalidDateException::class);
        (new JalaliCast())->set($this->model(), 'at', '1404/13/01', []);
    }

    public function test_round_trip_through_model_attribute(): void
    {
        $model = new class () extends Model {
            protected $guarded = [];

            protected $casts = ['at' => JalaliCast::class];
        };
        $model->at = '1404/01/01 10:00';

        self::assertSame('2025-03-21 10:00:00', $model->getAttributes()['at']);
        self::assertSame('1404/01/01', $model->at->format('Y/m/d'));
    }

    /* ---------------- JalaliCast ---------------- */

    public function test_cast_get_returns_null_only_for_missing_values_and_rejects_other_types(): void
    {
        $cast = new JalaliCast();

        self::assertNull($cast->get($this->model(), 'at', '', []));
        self::assertNull($cast->get($this->model(), 'at', null, []));

        foreach ([1.5, ['2024-03-20'], true, false, new \stdClass()] as $bad) {
            try {
                $cast->get($this->model(), 'published_at', $bad, []);
                self::fail('unsupported stored value must be rejected');
            } catch (InvalidDateException $e) {
                self::assertSame("Cannot read the value of 'published_at' as a date.", $e->getMessage());
                self::assertSame(['attribute' => 'published_at', 'type' => get_debug_type($bad)], $e->getContext());
            }
        }
    }

    public function test_get_reads_the_stored_value_as_gregorian_whatever_the_year(): void
    {
        $cast = new JalaliCast();
        $m = $this->model();

        // A Gregorian year below 1700 must not be read as a Jalali year.
        foreach (['1600-01-01 00:00:00', '1650-05-05 10:00:00', '1699-12-31 23:59:59', '1700-01-01 00:00:00', '1500-06-06 06:00:00'] as $stored) {
            $j = $cast->get($m, 'at', $stored, []);
            self::assertInstanceOf(Jalali::class, $j);
            self::assertSame($stored, $j->toGregorian()->format('Y-m-d H:i:s'), $stored);
        }
    }

    public function test_historical_gregorian_dates_survive_a_round_trip(): void
    {
        $cast = new JalaliCast();
        $m = $this->model();

        foreach (['1600-01-01 00:00:00', '1650-05-05 10:00:00', '1699-12-31 23:59:59', '0900-01-01 00:00:00'] as $greg) {
            $stored = $cast->set($m, 'at', new \DateTimeImmutable($greg), []);
            self::assertSame($greg, $stored);
            $back = $cast->get($m, 'at', $stored, []);
            self::assertSame($greg, $back?->toGregorian()->format('Y-m-d H:i:s'));
            self::assertSame($stored, $cast->set($m, 'at', $back, []));
        }
    }

    public function test_get_rejects_an_unreadable_stored_string(): void
    {
        $this->expectException(InvalidDateException::class);
        (new JalaliCast())->get($this->model(), 'at', 'not a date', []);
    }

    public function test_cast_set_rejects_unsupported_types_naming_the_attribute(): void
    {
        $cast = new JalaliCast();

        foreach ([1.5, ['x'], true] as $bad) {
            try {
                $cast->set($this->model(), 'published_at', $bad, []);
                self::fail('unsupported value must be rejected');
            } catch (InvalidDateException $e) {
                self::assertStringContainsString('published_at', $e->getMessage());
            }
        }
    }

    public function test_model_to_array_and_to_json_render_jalali_text(): void
    {
        $model = new class () extends Model {
            protected $guarded = [];

            protected $casts = ['at' => JalaliCast::class];
        };
        $model->at = '1404/01/01 10:00';

        $this->assertSame('{"at":"1404\/01\/01 10:00:00"}', $model->toJson());
        $this->assertSame('1404/01/01 10:00:00', json_decode($model->toJson(), true)['at']);
        $this->assertSame('1404/01/01 10:00:00', (string) $model->toArray()['at']);
    }

    public function test_non_date_string_is_rejected(): void
    {
        $this->expectException(InvalidDateException::class);
        (new JalaliCast())->set($this->model(), 'at', 'x', []);
    }
    private function model(): Model
    {
        return new class () extends Model {};
    }
}
