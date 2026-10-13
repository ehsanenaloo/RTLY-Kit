<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel\Casts;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Laravel\Casts\JalaliCast;

/**
 * Eloquent calls set() on every cached cast object when it builds the dirty
 * attributes; reading an attribute must not change the stored text.
 */
final class JalaliCastDirtyTest extends TestCase
{
    private function model(string $cast, string $raw): Model
    {
        $model = new class () extends Model {
            public string $castDef = '';

            public function getCasts(): array
            {
                return ['at' => $this->castDef];
            }
        };
        $model->castDef = $cast;
        $model->setRawAttributes(['at' => $raw, 'title' => 'a'], true);

        return $model;
    }

    /** @return iterable<string, array{string, string}> */
    public static function storedTexts(): iterable
    {
        yield 'micro, no arguments' => [JalaliCast::class, '2024-03-20 12:00:00.123456'];
        yield 'short fraction, utc 6' => [JalaliCast::class.':UTC,6', '2024-03-20 12:00:00.5'];
        yield 'iso with T and Z' => [JalaliCast::class.':UTC', '2024-03-20T12:00:00Z'];
        yield 'date only' => [JalaliCast::class, '2024-03-20'];
        yield 'plain' => [JalaliCast::class, '2024-03-20 12:00:00'];
    }

    #[DataProvider('storedTexts')]
    public function test_reading_does_not_make_the_attribute_dirty(string $cast, string $raw): void
    {
        $model = $this->model($cast, $raw);

        self::assertInstanceOf(Jalali::class, $model->at);
        $model->title = 'b';

        self::assertSame(['title' => 'b'], $model->getDirty());
        self::assertSame($raw, $model->getAttributes()['at']);
    }

    public function test_assigning_another_moment_is_dirty(): void
    {
        $model = $this->model(JalaliCast::class.':UTC,6', '2024-03-20 12:00:00.123456');
        $model->at;
        $model->at = Jalali::make(new \DateTimeImmutable('2024-03-20 12:00:00.123457', new \DateTimeZone('UTC')));

        self::assertSame('2024-03-20 12:00:00.123457', $model->getDirty()['at']);
    }

    public function test_assigning_the_same_moment_with_less_precision_is_dirty(): void
    {
        $model = $this->model(JalaliCast::class, '2024-03-20 12:00:00.123456');
        $model->at = Jalali::make(new \DateTimeImmutable('2024-03-20 12:00:00', new \DateTimeZone('UTC')));

        self::assertSame('2024-03-20 12:00:00', $model->getDirty()['at']);
    }

    public function test_unreadable_stored_text_does_not_hide_a_new_value(): void
    {
        $model = $this->model(JalaliCast::class, 'garbage');
        $model->at = Jalali::create(1403, 1, 1);

        self::assertSame('2024-03-20 00:00:00', $model->getDirty()['at']);
    }
}
