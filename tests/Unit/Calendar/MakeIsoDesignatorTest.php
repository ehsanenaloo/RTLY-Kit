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
 * make() reads the common ISO-8601 shapes as the calendar's own date: a space,
 * T or t before the time, H:i[:s[.u]], and an optional Z / +HH:MM / +HHMM / +HH
 * designator that sets the zone of the result.
 */
final class MakeIsoDesignatorTest extends TestCase
{
    /** @return iterable<string, array{class-string<CalendarDate>, string, string}> class, own year and the Gregorian date of its 1st of the first month used below */
    public static function calendars(): iterable
    {
        // Jalali 1403/01/01 = 2024-03-20, Hijri 1446/09/01 = 2025-03-01, Hebrew 5785/01/01 = 2024-10-03.
        yield 'Jalali' => [Jalali::class, '1403/01/01', '2024-03-20'];
        yield 'Hijri' => [Hijri::class, '1446/09/01', '2025-03-01'];
        yield 'Hebrew' => [Hebrew::class, '5785/01/01', '2024-10-03'];
    }

    /** @return iterable<string, array{class-string<CalendarDate>, string, string}> */
    public static function acceptedShapes(): iterable
    {
        // text suffix after the date => expected Gregorian ISO-8601 (c) suffix after the date
        $shapes = [
            'T with offset colon' => ['T10:00:00+03:30', 'T10:00:00+03:30'],
            'T with offset no colon' => ['T10:00:00+0330', 'T10:00:00+03:30'],
            'T with hour offset' => ['T10:00:00+03', 'T10:00:00+03:00'],
            'T with negative offset' => ['T10:00:00-05:00', 'T10:00:00-05:00'],
            'space with Z' => [' 10:00:00Z', 'T10:00:00+00:00'],
            'lowercase t' => ['t10:00', 'T10:00:00+00:00'],
            'lowercase z' => ['T10:00:00z', 'T10:00:00+00:00'],
            'one-digit hour' => [' 9:05', 'T09:05:00+00:00'],
            'minutes with offset' => ['T10:00+03:30', 'T10:00:00+03:30'],
            'fraction with Z' => ['T10:00:00.25Z', 'T10:00:00+00:00'],
            'plain T no zone' => ['T10:00:00', 'T10:00:00+00:00'],
            'max offset' => ['T10:00:00+14:00', 'T10:00:00+14:00'],
            'min offset' => ['T10:00:00-14:00', 'T10:00:00-14:00'],
        ];
        foreach (self::calendars() as $name => [$class, $date, $greg]) {
            foreach ($shapes as $label => [$text, $expected]) {
                yield "{$name}: {$label}" => [$class, $date.$text, $greg.$expected];
            }
        }
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('acceptedShapes')]
    public function test_accepted_shapes_read_as_the_own_date(string $class, string $text, string $expected): void
    {
        // A designator sets the zone itself; the others are read in the zone given.
        $hasDesignator = preg_match('/(Z|z|[+-]\d{2}(:?\d{2})?)$/', $text) === 1;
        $date = $class::make($text, $hasDesignator ? null : new DateTimeZone('UTC'));

        self::assertSame($expected, $date->toGregorian()->format('Y-m-d\TH:i:sP'));
    }

    public function test_known_answers_for_jalali(): void
    {
        $offset = Jalali::make('1403-01-01T10:00:00+03:30');
        self::assertSame('2024-03-20 10:00:00 +03:30', $offset->toGregorian()->format('Y-m-d H:i:s P'));
        self::assertSame('1403/01/01 10:00:00', $offset->toDateTimeString());

        $z = Jalali::make('1403/01/01 10:00:00Z');
        self::assertSame('2024-03-20 10:00:00', $z->toGregorian()->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $z->getTimezone()->getName());

        self::assertSame('2024-03-20 10:00:00', Jalali::make('1403-01-01t10:00', new DateTimeZone('UTC'))->toGregorian()->format('Y-m-d H:i:s'));
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_the_designator_is_read_first_and_the_timezone_argument_converts(string $class, string $date, string $greg): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');
        $result = $class::make($date.'T10:00:00Z', $tehran);

        self::assertSame('Asia/Tehran', $result->getTimezone()->getName());
        self::assertSame((new DateTimeImmutable($greg.' 10:00:00 UTC'))->getTimestamp(), $result->getTimestamp());
        self::assertSame('13:30:00', $result->toGregorian()->format('H:i:s'));

        // Without a designator the argument is the zone the text is read in.
        $plain = $class::make($date.'T10:00:00', $tehran);
        self::assertSame('10:00:00', $plain->toGregorian()->format('H:i:s'));
        self::assertSame('Asia/Tehran', $plain->getTimezone()->getName());
    }

    /** @param class-string<CalendarDate> $class */
    #[DataProvider('calendars')]
    public function test_invalid_clock_values_and_offsets_throw(string $class, string $date): void
    {
        foreach (['T24:00:00', 'T10:60:00', 'T10:00:60', 'T25:00', 'T10:00:00+14:30', 'T10:00:00+15:00', 'T10:00:00-1500', 'T10:00:00+03:60', 'T10:00:00+99'] as $suffix) {
            try {
                $class::make($date.$suffix);
                self::fail("{$class}::make('{$date}{$suffix}') should throw");
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_hijri_variant_null_keeps_and_explicit_variants_apply(): void
    {
        $utc = new DateTimeZone('UTC');
        $uq = Hijri::make('2025-03-01 10:00:00', $utc);
        self::assertSame(HijriVariant::UmmAlQura, $uq->getVariant());

        $tabular = Hijri::make($uq, null, HijriVariant::Tabular);
        self::assertSame(HijriVariant::Tabular, $tabular->getVariant());

        // null keeps the instance's variant, in the same zone or another.
        self::assertSame($tabular, Hijri::make($tabular));
        self::assertSame($tabular, Hijri::make($tabular, null, null));
        self::assertSame(HijriVariant::Tabular, Hijri::make($tabular, new DateTimeZone('Asia/Tehran'))->getVariant());

        // An explicit UmmAlQura converts a Tabular instance back.
        $back = Hijri::make($tabular, null, HijriVariant::UmmAlQura);
        self::assertSame(HijriVariant::UmmAlQura, $back->getVariant());
        self::assertSame($uq->getTimestamp(), $back->getTimestamp());
        self::assertSame($uq->toDateString(), $back->toDateString());

        // The same explicit variant is not a conversion.
        self::assertSame($uq, Hijri::make($uq, null, HijriVariant::UmmAlQura));
        self::assertSame($tabular, Hijri::make($tabular, null, HijriVariant::Tabular));

        // Other input types: null means UmmAlQura; an own-date string takes the given variant.
        self::assertSame(HijriVariant::UmmAlQura, Hijri::make('2025-03-01', $utc)->getVariant());
        self::assertSame(HijriVariant::Tabular, Hijri::make('1446/09/01', $utc, HijriVariant::Tabular)->getVariant());
        self::assertSame(HijriVariant::Tabular, Hijri::make('1446-09-01T10:00:00Z', null, HijriVariant::Tabular)->getVariant());
    }
}
