<?php

declare(strict_types=1);

namespace RtlyKit\Prayer;

use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Text\Utf8;

/**
 * The convention that fixes the Fajr, Maghrib and Isha times of a prayer-times calculation.
 *
 * Immutable. The built-in methods are the presets {@see self::tehran()}, {@see self::mwl()},
 * {@see self::isna()}, {@see self::egypt()}, {@see self::makkah()} and {@see self::karachi()}; their
 * sources and the evidence behind each are in resources/data/SOURCES.md and in
 * {@see self::$verification} / {@see self::$source}. A caller who follows another authority builds
 * its own method and passes it to {@see PrayerTimes}:
 *
 *     new CalculationMethod(name: 'Local', fajrAngle: 16.0, ishaAngle: 14.0);
 *     new CalculationMethod(name: 'Gulf', fajrAngle: 19.5, ishaMinutes: 90.0, ramadanIshaMinutes: 120.0);
 *
 * A custom method is the caller's responsibility: only the ranges are checked, never whether the
 * numbers match any authority. Isha is given either as an angle or as minutes after Maghrib. Maghrib
 * is sunset unless a Maghrib angle is given (the Tehran convention).
 *
 * @throws InvalidPrayerConfigException from the constructor for a value outside its range
 */
final class CalculationMethod
{
    /** Largest accepted length of the name and of the source text. */
    public const MAX_NAME_LENGTH = 64;

    public const MAX_SOURCE_LENGTH = 300;

    /** Accepted ranges, in degrees and minutes. */
    public const MIN_ANGLE = 1.0;

    public const MAX_ANGLE = 30.0;

    public const MAX_MAGHRIB_ANGLE = 10.0;

    public const MAX_ISHA_MINUTES = 240.0;

    /** The keys {@see self::fromArray()} accepts. */
    private const KEYS = ['name', 'fajr_angle', 'isha_angle', 'isha_minutes', 'maghrib_angle', 'ramadan_isha_minutes', 'source'];

    /**
     * @param string  $name               short name, 1 to 64 characters, no control characters
     * @param float   $fajrAngle          sun depression for Fajr, 1 to 30 degrees
     * @param ?float  $ishaAngle          sun depression for Isha, 1 to 30 degrees (give this or $ishaMinutes)
     * @param ?float  $ishaMinutes        Isha this many minutes after Maghrib, more than 0 and at most 240
     * @param ?float  $maghribAngle       sun depression for Maghrib, more than 0 and at most 10 degrees; null means sunset
     * @param ?float  $ramadanIshaMinutes Isha delay in Ramadan (Umm al-Qura calendar), only with $ishaMinutes
     * @param string  $source             where the values come from, at most 300 characters
     */
    public function __construct(
        public readonly string $name,
        public readonly float $fajrAngle,
        public readonly ?float $ishaAngle = null,
        public readonly ?float $ishaMinutes = null,
        public readonly ?float $maghribAngle = null,
        public readonly ?float $ramadanIshaMinutes = null,
        public readonly string $source = '',
        public readonly MethodVerification $verification = MethodVerification::Custom,
    ) {
        if ($name === '' || strlen($name) > self::MAX_NAME_LENGTH || ! Utf8::isValid($name) || preg_match('/[\p{Cc}]/u', $name) === 1) {
            throw self::invalid('The method name must be 1 to '.self::MAX_NAME_LENGTH.' bytes of text without control characters', 'name');
        }
        if (strlen($source) > self::MAX_SOURCE_LENGTH || ! Utf8::isValid($source)) {
            throw self::invalid('The method source must be valid text of at most '.self::MAX_SOURCE_LENGTH.' bytes', 'source');
        }
        self::assertAngle($fajrAngle, 'fajr_angle', self::MIN_ANGLE, self::MAX_ANGLE);

        if (($ishaAngle === null) === ($ishaMinutes === null)) {
            throw self::invalid('Give exactly one of isha_angle and isha_minutes', 'isha_angle');
        }
        if ($ishaAngle !== null) {
            self::assertAngle($ishaAngle, 'isha_angle', self::MIN_ANGLE, self::MAX_ANGLE);
        }
        if ($ishaMinutes !== null) {
            self::assertAngle($ishaMinutes, 'isha_minutes', 0.0, self::MAX_ISHA_MINUTES, true);
        }
        if ($maghribAngle !== null) {
            self::assertAngle($maghribAngle, 'maghrib_angle', 0.0, self::MAX_MAGHRIB_ANGLE, true);
        }
        if ($ramadanIshaMinutes !== null) {
            if ($ishaMinutes === null) {
                throw self::invalid('ramadan_isha_minutes needs isha_minutes', 'ramadan_isha_minutes');
            }
            self::assertAngle($ramadanIshaMinutes, 'ramadan_isha_minutes', 0.0, self::MAX_ISHA_MINUTES, true);
        }
    }

    /**
     * Builds a custom method from a plain array (for configuration files). Unknown keys, missing
     * required keys and values of the wrong type are rejected.
     *
     * Keys: name, fajr_angle (required); isha_angle or isha_minutes (one of them); maghrib_angle,
     * ramadan_isha_minutes, source (optional). The verification is always {@see MethodVerification::Custom}.
     *
     * @param array<array-key, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        foreach (array_keys($config) as $key) {
            if (! is_string($key) || ! in_array($key, self::KEYS, true)) {
                throw self::invalid('Unknown calculation method option: '.Utf8::excerpt((string) $key).'. Allowed: '.implode(', ', self::KEYS), 'method');
            }
        }
        foreach (['name', 'fajr_angle'] as $required) {
            if (! array_key_exists($required, $config)) {
                throw self::invalid("The calculation method option {$required} is required", $required);
            }
        }

        $number = static function (string $key) use ($config): ?float {
            if (! array_key_exists($key, $config) || $config[$key] === null) {
                return null;
            }
            $value = $config[$key];
            if (! is_int($value) && ! is_float($value)) {
                throw self::invalid("The calculation method option {$key} must be a number", $key);
            }

            return (float) $value;
        };
        if (! is_string($config['name'])) {
            throw self::invalid('The calculation method option name must be text', 'name');
        }
        if (isset($config['source']) && ! is_string($config['source'])) {
            throw self::invalid('The calculation method option source must be text', 'source');
        }

        return new self(
            name: $config['name'],
            fajrAngle: $number('fajr_angle') ?? 0.0,
            ishaAngle: $number('isha_angle'),
            ishaMinutes: $number('isha_minutes'),
            maghribAngle: $number('maghrib_angle'),
            ramadanIshaMinutes: $number('ramadan_isha_minutes'),
            source: $config['source'] ?? '',
        );
    }

    /**
     * A built-in method by name: Tehran, MWL, ISNA, Egypt, Makkah or Karachi (case sensitive).
     *
     * @throws InvalidPrayerConfigException for an unknown name
     */
    public static function named(string $name): self
    {
        $methods = self::builtIn();
        if (! isset($methods[$name])) {
            throw new InvalidPrayerConfigException(
                'Unknown calculation method: '.Utf8::excerpt($name).'. Available: '.implode(', ', array_keys($methods)),
            );
        }

        return $methods[$name];
    }

    /**
     * The built-in methods by name, in a fixed order.
     *
     * @return array<string, self>
     */
    public static function builtIn(): array
    {
        return [
            'MWL' => self::mwl(),
            'ISNA' => self::isna(),
            'Egypt' => self::egypt(),
            'Makkah' => self::makkah(),
            'Karachi' => self::karachi(),
            'Tehran' => self::tehran(),
        ];
    }

    /** Muslim World League: Fajr 18, Isha 17 degrees. Corroborated; the League publishes no city timetable we could find. */
    public static function mwl(): self
    {
        return new self('MWL', 18.0, ishaAngle: 17.0, source: 'Muslim World League convention (angles as in praytimes.org, adhan-js and the Diyanet imsak/yatsi columns).', verification: MethodVerification::Corroborated);
    }

    /** ISNA: Fajr 15, Isha 15 degrees. Corroborated (the Fiqh Council of North America names 15 degrees). */
    public static function isna(): self
    {
        return new self('ISNA', 15.0, ishaAngle: 15.0, source: 'Islamic Society of North America convention; the Fiqh Council of North America names 15 degrees.', verification: MethodVerification::Corroborated);
    }

    /** Egypt: Fajr 19.5, Isha 17.5 degrees. Consistent with the Dar al-Ifta Cairo table, which does not name the angle authority. */
    public static function egypt(): self
    {
        return new self('Egypt', 19.5, ishaAngle: 17.5, source: 'Egyptian General Authority of Survey convention; compared with the Dar al-Ifta (Cairo) timetable.', verification: MethodVerification::PublishedTable);
    }

    /** Umm al-Qura, Makkah: Fajr 18.5 degrees, Isha 90 minutes after Maghrib and 120 in Ramadan. Compared with the official table. */
    public static function makkah(): self
    {
        return new self('Makkah', 18.5, ishaMinutes: 90.0, ramadanIshaMinutes: 120.0, source: 'Umm Al-Qura calendar (KACST), compared with its published timetable including Ramadan.', verification: MethodVerification::PublishedTable);
    }

    /** University of Islamic Sciences, Karachi: Fajr 18, Isha 18 degrees. Corroborated; no timetable of the University was found. */
    public static function karachi(): self
    {
        return new self('Karachi', 18.0, ishaAngle: 18.0, source: 'University of Islamic Sciences, Karachi convention (angles as in praytimes.org, adhan-js and a Karachi madrasa timetable).', verification: MethodVerification::Corroborated);
    }

    /**
     * Institute of Geophysics, University of Tehran: Fajr 17.7 degrees, Maghrib at 4.5 degrees, Isha 14 degrees.
     * Fajr, sunrise, Dhuhr and Maghrib were compared with the Institute's tables; those tables have no Isha or Asr column,
     * so the 14 degrees of Isha rest on secondary sources.
     */
    public static function tehran(): self
    {
        return new self('Tehran', 17.7, ishaAngle: 14.0, maghribAngle: 4.5, source: 'Institute of Geophysics, University of Tehran: Fajr, sunrise, Dhuhr and Maghrib compared with its tables; Isha (14 degrees) from secondary sources.', verification: MethodVerification::PublishedTable);
    }

    /**
     * @return array{name: string, fajr_angle: float, isha_angle: ?float, isha_minutes: ?float, maghrib_angle: ?float, ramadan_isha_minutes: ?float, source: string, verification: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'fajr_angle' => $this->fajrAngle,
            'isha_angle' => $this->ishaAngle,
            'isha_minutes' => $this->ishaMinutes,
            'maghrib_angle' => $this->maghribAngle,
            'ramadan_isha_minutes' => $this->ramadanIshaMinutes,
            'source' => $this->source,
            'verification' => $this->verification->value,
        ];
    }

    private static function assertAngle(float $value, string $option, float $min, float $max, bool $minExclusive = false): void
    {
        if (! is_finite($value) || $value > $max || ($minExclusive ? $value <= $min : $value < $min)) {
            throw self::invalid(sprintf('The calculation method option %s must be a number %s %s and at most %s', $option, $minExclusive ? 'above' : 'from', self::trim($min), self::trim($max)), $option);
        }
    }

    private static function trim(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    private static function invalid(string $message, string $option): InvalidPrayerConfigException
    {
        return InvalidPrayerConfigException::because(ErrorCode::InvalidPrayerConfig, $message, ['option' => $option]);
    }
}
