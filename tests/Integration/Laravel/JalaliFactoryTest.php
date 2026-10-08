<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Laravel\JalaliFactory;

final class JalaliFactoryTest extends TestCase
{
    public function test_jalali_factory_wrapper(): void
    {
        $f = new JalaliFactory();
        $this->assertSame(1403, $f->create(1403, 1, 1)->getYear());
        $this->assertSame(1403, $f->make($f->create(1403, 5, 5))->getYear());
    }

    /* ---------------- JalaliFactory ---------------- */

    public function test_factory_now_and_today_honour_timezone(): void
    {
        $f = new JalaliFactory();
        $tz = new DateTimeZone('Asia/Tehran');

        $now = $f->now($tz);
        self::assertInstanceOf(Jalali::class, $now);
        self::assertSame('Asia/Tehran', $now->getTimezone()->getName());

        $today = $f->today($tz);
        self::assertSame('00:00:00', $today->format('H:i:s'));
        self::assertSame('Asia/Tehran', $today->getTimezone()->getName());
        self::assertLessThanOrEqual(1, $today->diffInDays($now));
    }
}
