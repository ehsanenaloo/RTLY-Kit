<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Prayer\CalculationMethod;
use RtlyKit\Prayer\HighLatitudeRule;
use RtlyKit\Prayer\MethodVerification;
use RtlyKit\Prayer\PrayerTimes;

final class CalculationMethodTest extends TestCase
{
    /** @return iterable<string, array{string, float, ?float, ?float, ?float, ?float, MethodVerification}> */
    public static function presets(): iterable
    {
        // name, fajr, isha angle, isha minutes, maghrib angle, ramadan isha minutes, verification
        yield 'MWL' => ['MWL', 18.0, 17.0, null, null, null, MethodVerification::Corroborated];
        yield 'ISNA' => ['ISNA', 15.0, 15.0, null, null, null, MethodVerification::Corroborated];
        yield 'Egypt' => ['Egypt', 19.5, 17.5, null, null, null, MethodVerification::PublishedTable];
        yield 'Makkah' => ['Makkah', 18.5, null, 90.0, null, 120.0, MethodVerification::PublishedTable];
        yield 'Karachi' => ['Karachi', 18.0, 18.0, null, null, null, MethodVerification::Corroborated];
        yield 'Tehran' => ['Tehran', 17.7, 14.0, null, 4.5, null, MethodVerification::PublishedTable];
    }

    #[DataProvider('presets')]
    public function test_the_built_in_methods_keep_their_values(string $name, float $fajr, ?float $isha, ?float $minutes, ?float $maghrib, ?float $ramadan, MethodVerification $verification): void
    {
        $method = CalculationMethod::named($name);

        self::assertSame($name, $method->name);
        self::assertSame($fajr, $method->fajrAngle);
        self::assertSame($isha, $method->ishaAngle);
        self::assertSame($minutes, $method->ishaMinutes);
        self::assertSame($maghrib, $method->maghribAngle);
        self::assertSame($ramadan, $method->ramadanIshaMinutes);
        self::assertSame($verification, $method->verification);
        self::assertNotSame('', $method->source);
        self::assertEquals($method, CalculationMethod::builtIn()[$name]);
    }

    public function test_the_built_in_list_is_fixed(): void
    {
        self::assertSame(['MWL', 'ISNA', 'Egypt', 'Makkah', 'Karachi', 'Tehran'], array_keys(CalculationMethod::builtIn()));
        foreach ([PrayerTimes::METHOD_TEHRAN, PrayerTimes::METHOD_MWL, PrayerTimes::METHOD_ISNA, PrayerTimes::METHOD_EGYPT, PrayerTimes::METHOD_MAKKAH, PrayerTimes::METHOD_KARACHI] as $name) {
            self::assertSame($name, CalculationMethod::named($name)->name);
        }
    }

    public function test_an_unknown_name_lists_the_choices(): void
    {
        try {
            CalculationMethod::named('tehran');
            self::fail('names are case sensitive');
        } catch (InvalidPrayerConfigException $e) {
            self::assertSame('Unknown calculation method: tehran. Available: MWL, ISNA, Egypt, Makkah, Karachi, Tehran', $e->getMessage());
        }
    }

    /** @return iterable<string, array{string, string|CalculationMethod}> */
    public static function equivalents(): iterable
    {
        foreach (CalculationMethod::builtIn() as $name => $object) {
            yield $name => [$name, $object];
        }
    }

    #[DataProvider('equivalents')]
    public function test_a_name_and_its_object_give_the_same_times(string $name, CalculationMethod $object): void
    {
        $zone = new DateTimeZone('Asia/Tehran');
        foreach (['2025-03-21', '2025-03-05', '2025-06-21', '2025-12-21'] as $day) {
            $date = new DateTimeImmutable($day, $zone);
            $byName = new PrayerTimes(35.6892, 51.389, $name, PrayerTimes::ASR_STANDARD, $zone);
            $byObject = new PrayerTimes(35.6892, 51.389, $object, PrayerTimes::ASR_STANDARD, $zone);

            self::assertSame($byName->getTimes($date), $byObject->getTimes($date), "{$name} {$day}");
            self::assertSame($object, $byObject->getCalculationMethod());
            self::assertEquals($object, $byName->getCalculationMethod());
        }
    }

    public function test_a_custom_copy_of_a_preset_gives_the_same_times(): void
    {
        $zone = new DateTimeZone('Europe/London');
        $copy = new CalculationMethod('Mine', 18.0, ishaAngle: 17.0);
        $date = new DateTimeImmutable('2025-03-20', $zone);

        self::assertSame(
            (new PrayerTimes(51.5074, -0.1278, 'MWL', 1, $zone))->getTimes($date),
            (new PrayerTimes(51.5074, -0.1278, $copy, 1, $zone))->getTimes($date),
        );
        self::assertSame(MethodVerification::Custom, $copy->verification);
    }

    public function test_the_ramadan_rule_belongs_to_the_method_not_to_its_name(): void
    {
        $zone = new DateTimeZone('Asia/Riyadh');
        $gulf = new CalculationMethod('Gulf', 18.5, ishaMinutes: 90.0, ramadanIshaMinutes: 120.0);
        $plain = new CalculationMethod('Plain', 18.5, ishaMinutes: 90.0);
        $make = static fn (CalculationMethod $m): PrayerTimes => new PrayerTimes(21.4225, 39.8262, $m, 1, $zone);
        $ramadan = new DateTimeImmutable('2025-03-10', $zone);
        $shawwal = new DateTimeImmutable('2025-04-10', $zone);

        $minutes = static function (array $t): int {
            [$mh, $mm] = array_map('intval', explode(':', $t['maghrib']));
            [$ih, $im] = array_map('intval', explode(':', $t['isha']));

            return ($ih * 60 + $im) - ($mh * 60 + $mm);
        };

        self::assertSame(120, $minutes($make($gulf)->getTimes($ramadan)));
        self::assertSame(90, $minutes($make($gulf)->getTimes($shawwal)));
        self::assertSame(90, $minutes($make($plain)->getTimes($ramadan)));
        self::assertSame($make(CalculationMethod::makkah())->getTimes($ramadan), $make($gulf)->getTimes($ramadan));
    }

    public function test_a_maghrib_angle_and_the_high_latitude_rule_work_with_a_custom_method(): void
    {
        $zone = new DateTimeZone('Europe/Oslo');
        $method = new CalculationMethod('North', 16.0, ishaAngle: 14.0, maghribAngle: 3.0);
        $date = new DateTimeImmutable('2025-06-21', $zone);

        foreach ([HighLatitudeRule::AngleBased, HighLatitudeRule::None] as $rule) {
            $times = (new PrayerTimes(69.65, 18.96, $method, 1, $zone))->withHighLatitudeRule($rule)->getTimes($date);
            self::assertArrayHasKey('maghrib', $times);
        }
        $normal = (new PrayerTimes(59.91, 10.75, $method, 1, $zone))->getTimes($date);
        self::assertNotNull($normal['maghrib']);
        self::assertGreaterThan($normal['maghrib'], $normal['isha'] ?? '99:99');
    }

    public function test_for_city_and_the_helper_take_an_object(): void
    {
        $custom = new CalculationMethod('Local', 17.0, ishaAngle: 15.0);

        self::assertSame($custom, PrayerTimes::forCity('tehran', $custom)->getCalculationMethod());
        self::assertSame(['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'], array_keys(\RtlyKit\prayer_times('tehran', $custom)));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidValues(): iterable
    {
        yield 'fajr below range' => [['fajrAngle' => 0.99], 'fajr_angle'];
        yield 'fajr above range' => [['fajrAngle' => 30.01], 'fajr_angle'];
        yield 'fajr not finite' => [['fajrAngle' => NAN], 'fajr_angle'];
        yield 'fajr infinite' => [['fajrAngle' => INF], 'fajr_angle'];
        yield 'isha angle below range' => [['ishaAngle' => 0.5], 'isha_angle'];
        yield 'isha angle above range' => [['ishaAngle' => 31.0], 'isha_angle'];
        yield 'neither isha form' => [['ishaAngle' => null], 'isha_angle'];
        yield 'both isha forms' => [['ishaMinutes' => 90.0], 'isha_angle'];
        yield 'isha minutes zero' => [['ishaAngle' => null, 'ishaMinutes' => 0.0], 'isha_minutes'];
        yield 'isha minutes too many' => [['ishaAngle' => null, 'ishaMinutes' => 240.01], 'isha_minutes'];
        yield 'maghrib angle zero' => [['maghribAngle' => 0.0], 'maghrib_angle'];
        yield 'maghrib angle too large' => [['maghribAngle' => 10.01], 'maghrib_angle'];
        yield 'ramadan without minutes' => [['ramadanIshaMinutes' => 120.0], 'ramadan_isha_minutes'];
        yield 'ramadan minutes zero' => [['ishaAngle' => null, 'ishaMinutes' => 90.0, 'ramadanIshaMinutes' => 0.0], 'ramadan_isha_minutes'];
        yield 'ramadan minutes too many' => [['ishaAngle' => null, 'ishaMinutes' => 90.0, 'ramadanIshaMinutes' => 241.0], 'ramadan_isha_minutes'];
        yield 'empty name' => [['name' => ''], 'name'];
        yield 'long name' => [['name' => str_repeat('a', 65)], 'name'];
        yield 'control character in the name' => [['name' => "a\nb"], 'name'];
        yield 'invalid UTF-8 in the name' => [['name' => "a\xff"], 'name'];
        yield 'long source' => [['source' => str_repeat('a', 301)], 'source'];
        yield 'invalid UTF-8 in the source' => [['source' => "\xff"], 'source'];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidValues')]
    public function test_values_outside_their_range_are_rejected(array $override, string $option): void
    {
        $arguments = array_merge(['name' => 'X', 'fajrAngle' => 18.0, 'ishaAngle' => 17.0], $override);

        try {
            new CalculationMethod(...$arguments);
            self::fail('Expected an InvalidPrayerConfigException.');
        } catch (InvalidPrayerConfigException $e) {
            self::assertSame(['option' => $option], $e->getContext());
            self::assertSame(ErrorCode::InvalidPrayerConfig, $e->getErrorCode());
        }
    }

    public function test_the_edges_of_the_ranges_are_accepted(): void
    {
        self::assertSame(1.0, (new CalculationMethod('a', 1.0, ishaAngle: 1.0))->fajrAngle);
        self::assertSame(30.0, (new CalculationMethod('a', 30.0, ishaAngle: 30.0))->ishaAngle);
        self::assertSame(240.0, (new CalculationMethod('a', 18.0, ishaMinutes: 240.0, ramadanIshaMinutes: 240.0))->ishaMinutes);
        self::assertSame(10.0, (new CalculationMethod('a', 18.0, ishaAngle: 17.0, maghribAngle: 10.0))->maghribAngle);
        self::assertSame(0.01, (new CalculationMethod('a', 18.0, ishaMinutes: 0.01))->ishaMinutes);
        self::assertSame(str_repeat('é', 32), (new CalculationMethod(str_repeat('é', 32), 18.0, ishaAngle: 17.0))->name);
        self::assertSame(300, strlen((new CalculationMethod('a', 18.0, ishaAngle: 17.0, source: str_repeat('a', 300)))->source));
    }

    public function test_from_array_builds_a_custom_method(): void
    {
        $method = CalculationMethod::fromArray(['name' => 'Local', 'fajr_angle' => 16, 'isha_minutes' => 75, 'ramadan_isha_minutes' => 100.5, 'source' => 'Local committee, 2026']);

        self::assertSame('Local', $method->name);
        self::assertSame(16.0, $method->fajrAngle);
        self::assertNull($method->ishaAngle);
        self::assertSame(75.0, $method->ishaMinutes);
        self::assertSame(100.5, $method->ramadanIshaMinutes);
        self::assertSame('Local committee, 2026', $method->source);
        self::assertSame(MethodVerification::Custom, $method->verification);

        $same = CalculationMethod::fromArray(['name' => 'L', 'fajr_angle' => 18, 'isha_angle' => 17, 'maghrib_angle' => null, 'isha_minutes' => null]);
        self::assertSame(17.0, $same->ishaAngle);
        self::assertNull($same->maghribAngle);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function badArrays(): iterable
    {
        yield 'unknown key' => [['name' => 'a', 'fajr_angle' => 18, 'isha_angle' => 17, 'fajr' => 18], 'Unknown calculation method option: fajr. Allowed: name, fajr_angle, isha_angle, isha_minutes, maghrib_angle, ramadan_isha_minutes, source'];
        yield 'numeric key' => [[0 => 'x', 'name' => 'a', 'fajr_angle' => 18, 'isha_angle' => 17], 'Unknown calculation method option: 0. Allowed: name, fajr_angle, isha_angle, isha_minutes, maghrib_angle, ramadan_isha_minutes, source'];
        yield 'missing name' => [['fajr_angle' => 18, 'isha_angle' => 17], 'The calculation method option name is required'];
        yield 'missing fajr' => [['name' => 'a', 'isha_angle' => 17], 'The calculation method option fajr_angle is required'];
        yield 'text angle' => [['name' => 'a', 'fajr_angle' => '18', 'isha_angle' => 17], 'The calculation method option fajr_angle must be a number'];
        yield 'bool angle' => [['name' => 'a', 'fajr_angle' => 18, 'isha_angle' => true], 'The calculation method option isha_angle must be a number'];
        yield 'array minutes' => [['name' => 'a', 'fajr_angle' => 18, 'isha_minutes' => [90]], 'The calculation method option isha_minutes must be a number'];
        yield 'name not text' => [['name' => 5, 'fajr_angle' => 18, 'isha_angle' => 17], 'The calculation method option name must be text'];
        yield 'source not text' => [['name' => 'a', 'fajr_angle' => 18, 'isha_angle' => 17, 'source' => 5], 'The calculation method option source must be text'];
        yield 'no isha' => [['name' => 'a', 'fajr_angle' => 18], 'Give exactly one of isha_angle and isha_minutes'];
    }

    /** @param array<array-key, mixed> $config */
    #[DataProvider('badArrays')]
    public function test_from_array_rejects_bad_input(array $config, string $message): void
    {
        $this->expectException(InvalidPrayerConfigException::class);
        $this->expectExceptionMessage($message);
        CalculationMethod::fromArray($config);
    }

    public function test_to_array_describes_the_method(): void
    {
        self::assertSame([
            'name' => 'Makkah',
            'fajr_angle' => 18.5,
            'isha_angle' => null,
            'isha_minutes' => 90.0,
            'maghrib_angle' => null,
            'ramadan_isha_minutes' => 120.0,
            'source' => CalculationMethod::makkah()->source,
            'verification' => 'published_table',
        ], CalculationMethod::makkah()->toArray());
    }

    public function test_the_error_for_an_unknown_option_name_is_short(): void
    {
        try {
            CalculationMethod::fromArray(['name' => 'a', 'fajr_angle' => 18, 'isha_angle' => 17, str_repeat('x', 5000) => 1]);
            self::fail('Expected an exception.');
        } catch (InvalidPrayerConfigException $e) {
            self::assertLessThan(200, strlen($e->getMessage()));
        }
    }
}
