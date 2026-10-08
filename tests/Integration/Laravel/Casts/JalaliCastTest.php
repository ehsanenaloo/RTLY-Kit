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

    public function test_cast_get_returns_null_for_unsupported_types(): void
    {
        $cast = new JalaliCast();

        self::assertNull($cast->get($this->model(), 'at', 1.5, []));
        self::assertNull($cast->get($this->model(), 'at', ['2024-03-20'], []));
        self::assertNull($cast->get($this->model(), 'at', '', []));
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
