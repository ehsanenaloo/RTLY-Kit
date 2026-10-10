<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\HijriVariant;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Contracts\CalendarDate;
use RtlyKit\Exceptions\InvalidDateException;

/**
 * serialize() keeps only the instant; unserialize() rebuilds every field
 * through the constructor checks, so a tampered payload cannot create an
 * object outside the supported range.
 */
final class CalendarSerializationTest extends TestCase
{
    private const INSTANT = '2024-10-03 12:34:56.123456';

    /** @return iterable<string, array{CalendarDate}> */
    public static function dates(): iterable
    {
        $zones = [
            'tehran' => new DateTimeZone('Asia/Tehran'),
            'offset' => new DateTimeZone('-05:30'),
            'utc' => new DateTimeZone('UTC'),
        ];

        foreach ($zones as $name => $zone) {
            $instant = new DateTimeImmutable(self::INSTANT, $zone);
            yield "jalali {$name}" => [Jalali::make($instant)];
            yield "hijri {$name}" => [Hijri::make($instant)];
            yield "hijri tabular {$name}" => [Hijri::make($instant, null, HijriVariant::Tabular)];
            yield "hebrew {$name}" => [Hebrew::make($instant)];
        }
        yield 'jalali first day' => [Jalali::make(new DateTimeImmutable('0001-03-21 00:00:00', $zones['utc']))];
        yield 'hijri last day' => [Hijri::make(new DateTimeImmutable('9999-09-30 23:59:59.999999', $zones['utc']))];
    }

    #[DataProvider('dates')]
    public function test_round_trip_keeps_instant_zone_microseconds_and_variant(CalendarDate $date): void
    {
        $copy = unserialize(serialize($date));

        self::assertInstanceOf($date::class, $copy);
        self::assertNotSame($date, $copy);
        self::assertTrue($date->eq($copy));
        self::assertSame($date->format('c u'), $copy->format('c u'));
        self::assertSame($date->getTimezone()->getName(), $copy->getTimezone()->getName());
        self::assertSame($date->getYear().'/'.$date->getMonth().'/'.$date->getDay(), $copy->getYear().'/'.$copy->getMonth().'/'.$copy->getDay());
        if ($date instanceof Hijri) {
            self::assertInstanceOf(Hijri::class, $copy);
            self::assertSame($date->getVariant(), $copy->getVariant());
        }
    }

    public function test_the_payload_is_a_small_versioned_array_with_the_instant_only(): void
    {
        $jalali = Jalali::make(new DateTimeImmutable(self::INSTANT, new DateTimeZone('Asia/Tehran')));
        self::assertSame(
            ['v' => 1, 'instant' => '2024-10-03T12:34:56.123456+03:30', 'timezone' => 'Asia/Tehran'],
            $jalali->__serialize(),
        );

        $hijri = Hijri::make(new DateTimeImmutable(self::INSTANT, new DateTimeZone('UTC')), null, HijriVariant::Tabular);
        self::assertSame(
            ['v' => 1, 'instant' => '2024-10-03T12:34:56.123456+00:00', 'timezone' => 'UTC', 'variant' => 'tabular'],
            $hijri->__serialize(),
        );

        self::assertStringNotContainsString('year', serialize($jalali));
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $payload
     */
    private static function payload(string $class, array $payload): string
    {
        $body = '';
        foreach ($payload as $key => $value) {
            $body .= serialize($key).serialize($value);
        }

        return sprintf('O:%d:"%s":%d:{%s}', strlen($class), $class, count($payload), $body);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function tampered(): iterable
    {
        $ok = ['v' => 1, 'instant' => '2024-10-03T12:34:56.123456+00:00', 'timezone' => 'UTC', 'variant' => 'umm_al_qura'];

        yield 'empty payload' => [[]];
        yield 'missing version' => [['instant' => $ok['instant'], 'timezone' => 'UTC', 'variant' => 'umm_al_qura']];
        yield 'wrong version' => [['v' => 2] + $ok];
        yield 'version as text' => [['v' => '1'] + $ok];
        yield 'missing instant' => [['v' => 1, 'timezone' => 'UTC', 'variant' => 'umm_al_qura']];
        yield 'missing timezone' => [['v' => 1, 'instant' => $ok['instant'], 'variant' => 'umm_al_qura']];
        yield 'instant is an int' => [['instant' => 123] + $ok];
        yield 'instant is an array' => [['instant' => ['2024']] + $ok];
        yield 'instant is null' => [['instant' => null] + $ok];
        yield 'zone is an int' => [['timezone' => 3] + $ok];
        yield 'year 99999' => [['instant' => '99999-01-01T00:00:00.000000+00:00'] + $ok];
        yield 'year 0000' => [['instant' => '0000-01-01T00:00:00.000000+00:00'] + $ok];
        yield 'before every calendar starts' => [['instant' => '0001-01-01T00:00:00.000000+00:00'] + $ok];
        yield 'garbage text' => [['instant' => 'garbage'] + $ok];
        yield 'no microseconds' => [['instant' => '2024-10-03T12:34:56+00:00'] + $ok];
        yield 'impossible day' => [['instant' => '2024-02-30T00:00:00.000000+00:00'] + $ok];
        yield 'impossible hour' => [['instant' => '2024-02-10T25:00:00.000000+00:00'] + $ok];
        yield 'trailing text' => [['instant' => '2024-10-03T12:34:56.123456+00:00 x'] + $ok];
        yield 'unknown zone' => [['timezone' => 'Mars/Base'] + $ok];
        yield 'long zone' => [['timezone' => str_repeat('A', 500)] + $ok];
        yield 'zone with NUL' => [['timezone' => "UTC\0"] + $ok];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('tampered')]
    public function test_tampered_payloads_throw_invalid_date_exception(array $payload): void
    {
        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            try {
                unserialize(self::payload($class, $payload));
                self::fail("{$class} accepted a tampered payload");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_hijri_needs_a_known_variant(): void
    {
        $base = ['v' => 1, 'instant' => '2024-10-03T12:34:56.123456+00:00', 'timezone' => 'UTC'];

        foreach ([[], ['variant' => 'moon'], ['variant' => 7], ['variant' => null]] as $extra) {
            try {
                unserialize(self::payload(Hijri::class, $base + $extra));
                self::fail('expected an exception');
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }

        $copy = unserialize(self::payload(Hijri::class, $base + ['variant' => 'tabular']));
        self::assertInstanceOf(Hijri::class, $copy);
        self::assertSame(HijriVariant::Tabular, $copy->getVariant());
    }

    public function test_a_valid_hand_written_payload_is_accepted(): void
    {
        $copy = unserialize(self::payload(Jalali::class, ['v' => 1, 'instant' => '2024-03-20T00:00:00.000000+00:00', 'timezone' => 'UTC']));

        self::assertInstanceOf(Jalali::class, $copy);
        self::assertSame('1403/01/01 00:00:00', (string) $copy);
    }

    public function test_the_older_property_layout_still_unserialises_and_is_checked(): void
    {
        $old = new DateTimeImmutable('2024-03-20 00:00:00', new DateTimeZone('UTC'));
        $jalaliKey = "\0".Jalali::class."\0gregorian";
        $copy = unserialize(sprintf('O:%d:"%s":1:{%s%s}', strlen(Jalali::class), Jalali::class, serialize($jalaliKey), serialize($old)));
        self::assertInstanceOf(Jalali::class, $copy);
        self::assertSame('1403/01/01', $copy->toDateString());

        $hijriCopy = unserialize(sprintf(
            'O:%d:"%s":2:{%s%s%s%s}',
            strlen(Hijri::class),
            Hijri::class,
            serialize("\0".Hijri::class."\0gregorian"),
            serialize($old),
            serialize("\0".Hijri::class."\0variant"),
            serialize(HijriVariant::Tabular),
        ));
        self::assertInstanceOf(Hijri::class, $hijriCopy);
        self::assertSame(HijriVariant::Tabular, $hijriCopy->getVariant());

        $this->expectException(InvalidDateException::class);
        unserialize(sprintf(
            'O:%d:"%s":1:{%s%s}',
            strlen(Jalali::class),
            Jalali::class,
            serialize($jalaliKey),
            serialize(new DateTimeImmutable('+10000-01-01', new DateTimeZone('UTC'))),
        ));
    }

    public function test_an_existing_object_cannot_be_overwritten_through_unserialize(): void
    {
        $date = Jalali::create(1403, 1, 1);

        $this->expectException(InvalidDateException::class);
        $date->__unserialize(['v' => 1, 'instant' => '2024-03-20T00:00:00.000000+00:00', 'timezone' => 'UTC']);
    }

    public function test_cloning_keeps_a_valid_equal_object(): void
    {
        foreach ([Jalali::create(1403, 1, 1), Hijri::create(1446, 9, 1, 0, 0, 0, null, HijriVariant::Tabular), Hebrew::create(5785, 1, 1)] as $date) {
            $clone = clone $date;
            self::assertNotSame($date, $clone);
            self::assertTrue($date->eq($clone));
            self::assertSame((string) $date, (string) $clone);
        }
    }
}
