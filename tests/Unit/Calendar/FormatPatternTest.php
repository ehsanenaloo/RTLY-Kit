<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Calendar;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;

final class FormatPatternTest extends TestCase
{
    /**
     * @return array<string, array{Closure(string): string, int}>
     */
    public static function calendars(): array
    {
        return [
            'jalali' => [static fn (string $p): string => Jalali::create(1403, 1, 1)->format($p), Jalali::MAX_FORMAT_LENGTH],
            'hijri' => [static fn (string $p): string => Hijri::create(1445, 9, 1)->format($p), Hijri::MAX_FORMAT_LENGTH],
            'hebrew' => [static fn (string $p): string => Hebrew::create(5784, 1, 1)->format($p), Hebrew::MAX_FORMAT_LENGTH],
        ];
    }

    /**
     * @param Closure(string): string $format
     */
    #[DataProvider('calendars')]
    public function test_pattern_cap_is_256_bytes_for_every_calendar(Closure $format, int $max): void
    {
        self::assertSame(256, $max);
        self::assertSame(256, strlen($format(str_repeat('x', 256))));

        try {
            $format(str_repeat('x', 257));
            self::fail('expected exception');
        } catch (InvalidDateException $e) {
            self::assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
            self::assertSame(['argument' => 'format', 'limit' => 256], $e->getContext());
        }
    }

    /**
     * @param Closure(string): string $format
     */
    #[DataProvider('calendars')]
    public function test_trailing_backslash_is_dropped_and_escapes_work(Closure $format, int $max): void
    {
        self::assertSame(256, $max);
        self::assertSame('', $format('\\'));
        self::assertSame('Y', $format('\\Y'));
        self::assertSame('\\', $format('\\\\'));
        self::assertSame($format('Y'), $format('Y\\'));
    }
}
