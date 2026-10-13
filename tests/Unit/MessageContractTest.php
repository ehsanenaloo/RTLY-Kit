<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Laravel\Casts\JalaliCast;
use RtlyKit\Prayer\PrayerTimes;
use RtlyKit\Support\CarbonMacros;
use RtlyKit\Text\Slugify;
use RtlyKit\Validation\NationalCode;

/** Exact messages and contexts of errors that callers read or log. */
final class MessageContractTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function badInstants(): iterable
    {
        yield 'text in front' => ['x2024-10-03T12:34:56.123456+00:00', 'Cannot unserialize the date: the instant is not an ISO 8601 date-time.'];
        yield 'text behind' => ['2024-10-03T12:34:56.123456+00:00 x', 'Cannot unserialize the date: the instant is not an ISO 8601 date-time.'];
        yield 'newline behind' => ["2024-10-03T12:34:56.123456+00:00\n", 'Cannot unserialize the date: the instant is not an ISO 8601 date-time.'];
        yield 'no fraction' => ['2024-10-03T12:34:56+00:00', 'Cannot unserialize the date: the instant is not an ISO 8601 date-time.'];
        yield 'impossible day' => ['2024-02-30T00:00:00.000000+00:00', 'Cannot unserialize the date: the instant is not a real date-time.'];
    }

    #[DataProvider('badInstants')]
    public function test_unserialize_messages(string $instant, string $message): void
    {
        $payload = ['v' => 1, 'instant' => $instant, 'timezone' => 'UTC'];
        $body = '';
        foreach ($payload as $key => $value) {
            $body .= serialize($key).serialize($value);
        }

        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            try {
                unserialize(sprintf('O:%d:"%s":3:{%s}', strlen($class), $class, $body));
                self::fail('Expected an InvalidDateException.');
            } catch (InvalidDateException $e) {
                self::assertSame($message, $e->getMessage(), $class);
            }
        }
    }

    public function test_prayer_configuration_messages(): void
    {
        $cases = [
            [fn () => new PrayerTimes(91.0, 0.0), 'The latitude must be between -90 and 90 degrees', 'latitude'],
            [fn () => new PrayerTimes(0.0, 181.0), 'The longitude must be between -180 and 180 degrees', 'longitude'],
            [fn () => new PrayerTimes(0.0, 0.0, 'Tehran', 1, null, 20001.0), 'The elevation must be between -20000 and 20000 metres', 'elevation'],
        ];
        foreach ($cases as [$call, $message, $option]) {
            try {
                $call();
                self::fail('Expected an InvalidPrayerConfigException.');
            } catch (InvalidPrayerConfigException $e) {
                self::assertSame($message, $e->getMessage());
                self::assertSame(['option' => $option], $e->getContext());
            }
        }
    }

    public function test_prayer_lookup_messages_show_a_clean_short_excerpt(): void
    {
        try {
            PrayerTimes::forCity("bad\ncity");
            self::fail('Expected an exception.');
        } catch (InvalidPrayerConfigException $e) {
            self::assertSame('Unknown city: bad?city', $e->getMessage());
        }

        try {
            new PrayerTimes(0.0, 0.0, 'Nope');
            self::fail('Expected an exception.');
        } catch (InvalidPrayerConfigException $e) {
            self::assertSame('Unknown calculation method: Nope. Available: MWL, ISNA, Egypt, Makkah, Karachi, Tehran', $e->getMessage());
        }

    }

    public function test_time_zone_messages(): void
    {
        foreach ([["Bad\0Zone", 'Unknown timezone: BadZone'], ['Mars/Base', 'Unknown timezone: Mars/Base'], ["Tab\there", 'Unknown timezone: Tab?here']] as [$zone, $message]) {
            try {
                CarbonMacros::zone($zone);
                self::fail('Expected an InvalidDateException.');
            } catch (InvalidDateException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    public function test_cast_constructor_errors_carry_their_context(): void
    {
        try {
            new JalaliCast('Mars/Base');
            self::fail('Expected an InvalidDateException.');
        } catch (InvalidDateException $e) {
            self::assertSame(['argument' => 'storageTimezone'], $e->getContext());
            self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
        }

        try {
            new JalaliCast('UTC', 7);
            self::fail('Expected an InvalidDateException.');
        } catch (InvalidDateException $e) {
            self::assertSame(['argument' => 'precision'], $e->getContext());
        }
    }

    public function test_cast_precision_limits_are_exact(): void
    {
        foreach ([0, 6, '0', '6'] as $ok) {
            self::assertInstanceOf(JalaliCast::class, new JalaliCast('UTC', $ok));
        }
        foreach ([-1, 7, '7', "6\n", '06', ' 6', ''] as $bad) {
            try {
                new JalaliCast('UTC', $bad);
                self::fail('Expected an InvalidDateException for '.var_export($bad, true));
            } catch (InvalidDateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_slugify_length_error_context(): void
    {
        try {
            Slugify::make(str_repeat('a', 4097));
            self::fail('Expected an exception.');
        } catch (RtlyKitException $e) {
            self::assertSame('The text to slugify is too long.', $e->getMessage());
            self::assertSame(['argument' => 'text', 'limit' => 4096], $e->getContext());
        }
    }

    public function test_national_code_of_a_negative_number_is_not_padded(): void
    {
        $result = NationalCode::validate(-5);

        self::assertSame(['invalid_format'], $result->errors());
        self::assertSame('5', $result->details()['normalized']);
        self::assertSame('0000000005', NationalCode::validate(5)->details()['normalized']);
    }

    public function test_is_past_and_is_future_are_never_both_true(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        foreach ([Jalali::class, Hijri::class, Hebrew::class] as $class) {
            $date = $class::make($now);
            usleep(1000);
            self::assertTrue($date->isPast());
            self::assertFalse($date->isFuture());
        }
    }
}
