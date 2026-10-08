<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Exceptions;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Exceptions\RtlyKitThrowable;
use RtlyKit\Exceptions\UnsupportedLocaleException;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;
use RtlyKit\Prayer\PrayerTimes;

final class RtlyKitExceptionTest extends TestCase
{
    /**
     * @return array<string, array{0: Closure(): mixed, 1: class-string<\Throwable>}>
     */
    public static function throwers(): array
    {
        return [
            'invalid number words' => [fn () => NumberToWords::fromWords('foo'), InvalidNumberException::class],
            'fractional float' => [fn () => NumberToWords::convert(1.5), InvalidNumberException::class],
            'bad separator input' => [fn () => Format::withSeparator('abc'), InvalidNumberException::class],
            'negative ordinal' => [fn () => Format::ordinal(-1), InvalidNumberException::class],
            'unsupported locale' => [fn () => NumberToWords::convert(1, 'xx'), UnsupportedLocaleException::class],
            'unknown city' => [fn () => PrayerTimes::forCity('atlantis'), InvalidPrayerConfigException::class],
            'unknown method' => [fn () => new PrayerTimes(0.0, 0.0, 'nope'), InvalidPrayerConfigException::class],
            'bad asr factor' => [fn () => new PrayerTimes(0.0, 0.0, 'MWL', 3), InvalidPrayerConfigException::class],
            'invalid date' => [fn () => Jalali::create(1403, 13, 1), InvalidDateException::class],
        ];
    }

    /**
     * @param Closure(): mixed $thrower
     * @param class-string<\Throwable> $specific
     */
    #[DataProvider('throwers')]
    public function test_every_library_exception_is_catchable_via_single_base(Closure $thrower, string $specific): void
    {
        try {
            $thrower();
            self::fail('Expected an exception.');
        } catch (RtlyKitException $e) {
            self::assertInstanceOf($specific, $e);
            self::assertInstanceOf(RtlyKitThrowable::class, $e);
            // Backward compatibility: still an \InvalidArgumentException.
            self::assertInstanceOf(InvalidArgumentException::class, $e);
        }
    }
}
