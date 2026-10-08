<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RtlyKit\Laravel\JalaliFactory;

/**
 * JalaliFactory::create() hands its arguments to Jalali::create() unchanged,
 * so omitted time parts mean midnight.
 */
final class JalaliFactoryDefaultsTest extends TestCase
{
    public function test_create_without_a_time_is_midnight(): void
    {
        $date = (new JalaliFactory())->create(1405, 1, 1, timezone: new DateTimeZone('UTC'));

        self::assertSame([0, 0, 0], [$date->getHour(), $date->getMinute(), $date->getSecond()]);
        self::assertSame('1405/01/01 00:00:00', $date->format('Y/m/d H:i:s'));
        self::assertSame('2026-03-21 00:00:00', $date->toGregorian()->format('Y-m-d H:i:s'));
    }

    public function test_create_passes_every_argument_through(): void
    {
        $tehran = new DateTimeZone('Asia/Tehran');
        $date = (new JalaliFactory())->create(1405, 1, 1, 13, 45, 27, $tehran);

        self::assertSame([1405, 1, 1, 13, 45, 27], [
            $date->getYear(), $date->getMonth(), $date->getDay(),
            $date->getHour(), $date->getMinute(), $date->getSecond(),
        ]);
        self::assertSame('Asia/Tehran', $date->getTimezone()->getName());

        // Each time part is passed on separately, in order.
        $partial = (new JalaliFactory())->create(1405, 1, 1, 7);
        self::assertSame('07:00:00', $partial->format('H:i:s'));
        self::assertSame('07:08:00', (new JalaliFactory())->create(1405, 1, 1, 7, 8)->format('H:i:s'));
    }
}
