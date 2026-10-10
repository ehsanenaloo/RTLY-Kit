<?php

declare(strict_types=1);

namespace RtlyKit\Prayer;

use DateTimeImmutable;
use DateTimeZone;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\InvalidPrayerConfigException;

/**
 * Islamic prayer times calculator.
 *
 * Independent implementation from standard astronomical equations: solar
 * declination and equation of time from the low-precision series of Jean
 * Meeus, "Astronomical Algorithms" (the same equations as the NOAA Solar
 * Calculator), the hour angle from spherical trigonometry
 * (cos H = (sin a - sin phi sin delta) / (cos phi cos delta)), Asr from the
 * shadow-length factor, and sunrise/sunset at -0.833 degrees (refraction plus
 * solar semidiameter). Each event is evaluated at the sun position of its own
 * time. The Fajr/Maghrib/Isha angles of each method are published conventions
 * (see resources/data/SOURCES.md), not derived from any particular software.
 * Supports common calculation methods and Asr schools (Standard / Hanafi).
 * Makkah: Isha is 90 minutes after Maghrib, 120 minutes during Ramadan (Umm
 * al-Qura Hijri month 9 of the local calendar date).
 *
 * Accuracy: expect agreement within about a minute of astronomical
 * sunrise/sunset values at low/mid latitudes. At high latitudes an angle may
 * never be reached (e.g. no true night in summer): by default {@see HighLatitudeRule::AngleBased}
 * then derives Fajr/Isha from a portion of the night (only for a time that is
 * missing or outside that bound), and {@see HighLatitudeRule::None} returns null
 * instead. Sunrise, sunset and Maghrib are null only when the sun truly does
 * not cross the horizon that day (or, near the poles, when the sun geometry breaks the
 * order fajr <= sunrise <= dhuhr <= asr <= maghrib <= isha); Dhuhr is always defined. Manual per-prayer
 * corrections: {@see self::withTune()}.
 */
final class PrayerTimes
{
    public const METHOD_TEHRAN  = 'Tehran';
    public const METHOD_MWL     = 'MWL';
    public const METHOD_ISNA    = 'ISNA';
    public const METHOD_EGYPT   = 'Egypt';
    public const METHOD_MAKKAH  = 'Makkah';
    public const METHOD_KARACHI = 'Karachi';

    public const ASR_STANDARD = 1; // Shafi'i, Maliki, Hanbali, Ja'fari
    public const ASR_HANAFI   = 2;

    private const NAMES = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];

    /** Largest manual tune, in minutes, in either direction. */
    public const MAX_TUNE_MINUTES = 30;

    /** Largest accepted |latitude| in degrees. */
    public const MAX_LATITUDE = 90.0;

    /** Largest accepted |longitude| in degrees. */
    public const MAX_LONGITUDE = 180.0;

    /** Largest accepted |elevation| in metres (negative values are treated as 0). */
    public const MAX_ELEVATION = 20000.0;

    /** Umm al-Qura Hijri month of Ramadan. */
    private const RAMADAN = 9;

    /** Makkah: extra Isha delay (minutes after Maghrib) during Ramadan. */
    private const MAKKAH_RAMADAN_ISHA_MINUTES = 120.0;

    private float $latitude;
    private float $longitude;
    private string $method;
    private int $asrFactor;
    private DateTimeZone $timezone;
    private float $elevation;
    private HighLatitudeRule $highLatitudeRule = HighLatitudeRule::AngleBased;

    /** @var array<string, int> manual tune in minutes per prayer name */
    private array $tune = ['fajr' => 0, 'sunrise' => 0, 'dhuhr' => 0, 'asr' => 0, 'maghrib' => 0, 'isha' => 0];

    /** @var array<string, array{fajr: float, isha: float, maghrib?: float, ishaMinutes?: float}> */
    private static array $methods = [
        'MWL'     => ['fajr' => 18.0, 'isha' => 17.0],
        'ISNA'    => ['fajr' => 15.0, 'isha' => 15.0],
        'Egypt'   => ['fajr' => 19.5, 'isha' => 17.5],
        'Makkah'  => ['fajr' => 18.5, 'isha' => 0.0, 'ishaMinutes' => 90.0],
        'Karachi' => ['fajr' => 18.0, 'isha' => 18.0],
        'Tehran'  => ['fajr' => 17.7, 'isha' => 14.0, 'maghrib' => 4.5],
    ];

    /**
     * @param ?DateTimeZone $timezone defaults to date_default_timezone_get()
     * @param float $elevation observer height in metres; lowers the sunrise/sunset
     *                         altitude by the geometric dip 0.0347*sqrt(h) degrees
     *                         (-20000..20000; negative values are treated as 0)
     *
     * @throws InvalidPrayerConfigException on unknown method or Asr factor, a non-finite
     *                                      number, |latitude| > 90, |longitude| > 180 or |elevation| > 20000
     */
    public function __construct(
        float $latitude,
        float $longitude,
        string $method = self::METHOD_TEHRAN,
        int $asrFactor = self::ASR_STANDARD,
        ?DateTimeZone $timezone = null,
        float $elevation = 0.0,
    ) {
        if (! isset(self::$methods[$method])) {
            throw new InvalidPrayerConfigException(
                "Unknown calculation method: {$method}. Available: ".implode(', ', array_keys(self::$methods)),
            );
        }

        if ($asrFactor !== self::ASR_STANDARD && $asrFactor !== self::ASR_HANAFI) {
            throw new InvalidPrayerConfigException("Invalid Asr factor: {$asrFactor} (use 1 or 2)");
        }

        foreach (['latitude' => $latitude, 'longitude' => $longitude, 'elevation' => $elevation] as $name => $value) {
            if (! is_finite($value)) {
                throw InvalidPrayerConfigException::because(
                    ErrorCode::InvalidPrayerConfig,
                    "The {$name} must be a finite number",
                    ['option' => $name],
                );
            }
        }

        if (abs($latitude) > self::MAX_LATITUDE) {
            throw InvalidPrayerConfigException::because(
                ErrorCode::InvalidPrayerConfig,
                'The latitude must be between -'.self::MAX_LATITUDE.' and '.self::MAX_LATITUDE.' degrees',
                ['option' => 'latitude'],
            );
        }

        if (abs($longitude) > self::MAX_LONGITUDE) {
            throw InvalidPrayerConfigException::because(
                ErrorCode::InvalidPrayerConfig,
                'The longitude must be between -'.self::MAX_LONGITUDE.' and '.self::MAX_LONGITUDE.' degrees',
                ['option' => 'longitude'],
            );
        }

        if (abs($elevation) > self::MAX_ELEVATION) {
            throw InvalidPrayerConfigException::because(
                ErrorCode::InvalidPrayerConfig,
                'The elevation must be between -'.(int) self::MAX_ELEVATION.' and '.(int) self::MAX_ELEVATION.' metres',
                ['option' => 'elevation'],
            );
        }

        $this->latitude  = $latitude;
        $this->longitude = $longitude;
        $this->method    = $method;
        $this->asrFactor = $asrFactor;
        $this->timezone  = $timezone ?? new DateTimeZone(date_default_timezone_get());
        $this->elevation = max(0.0, $elevation);
    }

    public static function forCity(string $city, string $method = self::METHOD_TEHRAN, int $asrFactor = self::ASR_STANDARD): self
    {
        $cities = [
            'tehran'   => [35.6892, 51.3890, 'Asia/Tehran'],
            'mashhad'  => [36.2970, 59.6062, 'Asia/Tehran'],
            'isfahan'  => [32.6546, 51.6680, 'Asia/Tehran'],
            'shiraz'   => [29.5918, 52.5837, 'Asia/Tehran'],
            'tabriz'   => [38.0962, 46.2738, 'Asia/Tehran'],
            'qom'      => [34.6416, 50.8746, 'Asia/Tehran'],
            'mecca'    => [21.4225, 39.8262, 'Asia/Riyadh'],
            'medina'   => [24.5247, 39.5692, 'Asia/Riyadh'],
            'riyadh'   => [24.7136, 46.6753, 'Asia/Riyadh'],
            'istanbul' => [41.0082, 28.9784, 'Europe/Istanbul'],
            'cairo'    => [30.0444, 31.2357, 'Africa/Cairo'],
            'dubai'    => [25.2048, 55.2708, 'Asia/Dubai'],
            'baghdad'  => [33.3152, 44.3661, 'Asia/Baghdad'],
            'jakarta'  => [-6.2088, 106.8456, 'Asia/Jakarta'],
        ];

        $key = strtolower(trim($city));
        if (! isset($cities[$key])) {
            throw new InvalidPrayerConfigException("Unknown city: {$city}");
        }

        [$lat, $lng, $tz] = $cities[$key];

        return new self($lat, $lng, $method, $asrFactor, new DateTimeZone($tz));
    }

    /**
     * Prayer times (HH:MM, local time of the calculator's timezone) for the
     * local calendar day of $date. A value is null when the sun never reaches
     * the required angle on that day (high latitudes). A value is also null when it
     * would break the order fajr <= sunrise <= dhuhr <= asr <= maghrib <= isha (as
     * instants, Isha may be after midnight) because the sun geometry is degenerate
     * (Asr near the poles), or when its local date is outside 0001-01-01..9999-12-31
     * (e.g. an Isha after midnight on 9999-12-31). Equal minutes are allowed. The
     * manual tune is applied after these checks. Times are rounded to the nearest
     * minute of the zone's exact UTC offset (a historic offset with seconds is not cut).
     *
     * @return array{fajr: ?string, sunrise: ?string, dhuhr: string, asr: ?string, maghrib: ?string, isha: ?string}
     *
     * @throws InvalidDateException when the local year of $date is outside 1-9999
     */
    public function getTimes(?DateTimeImmutable $date = null): array
    {
        $date = ($date ?? new DateTimeImmutable('now', $this->timezone))->setTimezone($this->timezone);
        $at   = $this->instants($date);

        return [
            'fajr'    => $this->clock($at['fajr']),
            'sunrise' => $this->clock($at['sunrise']),
            'dhuhr'   => $this->local($at['dhuhr']),
            'asr'     => $this->clock($at['asr']),
            'maghrib' => $this->clock($at['maghrib']),
            'isha'    => $this->clock($at['isha']),
        ];
    }

    /**
     * What to do when Fajr/Isha (or Maghrib at a Tehran-style angle) cannot be
     * computed or is implausibly far from sunrise/sunset. Default
     * {@see HighLatitudeRule::AngleBased}; {@see HighLatitudeRule::None} returns
     * null for a time whose angle is never reached. Ordinary latitudes are
     * unaffected: the rule changes only a time that is null or outside its
     * night-portion bound.
     */
    public function withHighLatitudeRule(HighLatitudeRule $rule): self
    {
        $copy                   = clone $this;
        $copy->highLatitudeRule = $rule;

        return $copy;
    }

    /**
     * Manual correction in whole minutes added to the final times, e.g.
     * `['fajr' => 2, 'maghrib' => 3]`. Keys: fajr, sunrise, dhuhr, asr,
     * maghrib, isha; each value an int from -30 to +30. Names not listed get
     * 0, so the call replaces any earlier tune; `[]` clears it. A time that
     * moves across midnight wraps on the clock (23:50 + 20 = 00:10).
     *
     * @param array<string, int> $minutes
     *
     * @throws InvalidPrayerConfigException on an unknown key, a non-int value or a value out of range
     */
    public function withTune(array $minutes): self
    {
        $tune = array_fill_keys(self::NAMES, 0);

        foreach ($minutes as $name => $value) {
            if (! in_array($name, self::NAMES, true)) {
                throw InvalidPrayerConfigException::because(
                    ErrorCode::InvalidPrayerConfig,
                    "Unknown tune key: {$name}. Available: ".implode(', ', self::NAMES),
                    ['key' => (string) $name],
                );
            }

            if (! is_int($value) || abs($value) > self::MAX_TUNE_MINUTES) {
                throw InvalidPrayerConfigException::because(
                    ErrorCode::InvalidPrayerConfig,
                    "Tune for {$name} must be an integer from -".self::MAX_TUNE_MINUTES.' to '.self::MAX_TUNE_MINUTES.' minutes',
                    ['key' => $name],
                );
            }

            $tune[$name] = $value;
        }

        $copy       = clone $this;
        $copy->tune = $tune;

        return $copy;
    }

    /**
     * Every prayer of the local calendar day of $date as a Unix timestamp
     * rounded to the nearest minute (null when it cannot be computed).
     *
     * @return array{fajr: ?int, sunrise: ?int, dhuhr: int, asr: ?int, maghrib: ?int, isha: ?int}
     */
    private function instants(DateTimeImmutable $date): array
    {
        $year = self::assertSupportedYear($date);

        // Midnight UT of the local calendar date; event times are UT hours after it.
        // Built field by field: a string with a 5-digit year would be parsed wrongly.
        $base = (new DateTimeImmutable('@0'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->setDate($year, (int) $date->format('n'), (int) $date->format('j'))
            ->setTime(0, 0)
            ->getTimestamp();

        // In a zone far from its longitude (Pacific/Kiritimati, UTC+14 at 157 W) solar noon of
        // that UT day falls on the neighbouring local date. Move the base by a whole day so that
        // the solar day is the one whose noon is on the requested local date.
        $noonAt = $base + (int) round($this->solarNoon($base / 86400.0 + 2440587.5) * 3600);
        $local  = $noonAt + $this->timezone->getOffset((new DateTimeImmutable('@'.$noonAt)));
        $local -= $base;
        if ($local < 0) {
            $base += 86400;
        } elseif ($local >= 86400) {
            $base -= 86400;
        }

        $h = $this->eventHours($base / 86400.0 + 2440587.5, $date);

        $at = self::enforceOrder([
            'fajr'    => self::instant($base, $h['fajr']),
            'sunrise' => self::instant($base, $h['sunrise']),
            'dhuhr'   => self::instant($base, $h['dhuhr']),
            'asr'     => self::instant($base, $h['asr']),
            'maghrib' => self::instant($base, $h['maghrib']),
            'isha'    => self::instant($base, $h['isha']),
        ]);

        // The manual tune comes last, so it may move a time anywhere; only the sun geometry is checked.
        foreach ($at as $name => $instant) {
            if ($instant === null) {
                continue;
            }
            $instant += $this->tune[$name] * 60;
            // A prayer whose local date is outside 0001-01-01..9999-12-31 does not exist (Dhuhr stays:
            // it is always on the requested date).
            $at[$name] = $name !== 'dhuhr' && ! self::isSupportedYear($this->localStamp($instant)) ? null : $instant;
        }

        /** @var array{fajr: ?int, sunrise: ?int, dhuhr: int, asr: ?int, maghrib: ?int, isha: ?int} $at */
        return $at;
    }

    /**
     * Keeps fajr <= sunrise <= dhuhr <= asr <= maghrib <= isha (as instants; Isha may be after
     * midnight). Near the poles the geometry degenerates (Asr can fall after Maghrib/Isha, a
     * fallback can cross a neighbour): the time that breaks the order becomes null. Equal minutes
     * are allowed and Dhuhr, the anchor, is never removed.
     *
     * @param array<string, ?int> $at
     *
     * @return array<string, ?int>
     */
    private static function enforceOrder(array $at): array
    {
        $dhuhr = $at['dhuhr'];

        if ($at['sunrise'] !== null && $at['sunrise'] > $dhuhr) {
            $at['sunrise'] = null;
        }
        if ($at['fajr'] !== null && $at['fajr'] > ($at['sunrise'] ?? $dhuhr)) {
            $at['fajr'] = null;
        }
        if ($at['maghrib'] !== null && $at['maghrib'] < $dhuhr) {
            $at['maghrib'] = null;
        }
        if ($at['isha'] !== null && $at['isha'] < ($at['maghrib'] ?? $dhuhr)) {
            $at['isha'] = null;
        }
        // Asr is the time whose geometry degenerates, so it yields to Maghrib and Isha, not the reverse.
        if ($at['asr'] !== null && ($at['asr'] < $dhuhr
            || ($at['maghrib'] !== null && $at['asr'] > $at['maghrib'])
            || ($at['isha'] !== null && $at['asr'] > $at['isha']))) {
            $at['asr'] = null;
        }

        return $at;
    }

    private static function isSupportedYear(int $localStamp): bool
    {
        $year = (int) (new DateTimeImmutable('@'.$localStamp))->format('Y');

        return $year >= 1 && $year <= 9999;
    }

    /**
     * @throws InvalidDateException when the local year of $date is outside 1-9999
     */
    private static function assertSupportedYear(DateTimeImmutable $date): int
    {
        $year = (int) $date->format('Y');
        if ($year < 1 || $year > 9999) {
            throw InvalidDateException::because(
                ErrorCode::DateOutOfRange,
                "Prayer times are supported for the years 1-9999 only: {$year}",
                ['year' => $year],
            );
        }

        return $year;
    }

    /** Timestamp of $ut hours after $base, to the nearest minute (null stays null). */
    private static function instant(int $base, ?float $ut): ?int
    {
        return $ut === null ? null : (int) (floor(($base + $ut * 3600) / 60 + 0.5) * 60);
    }

    /**
     * UT hours after 0h UT of the local date (Julian Day $jd0) for each prayer.
     *
     * @return array{fajr: ?float, sunrise: ?float, dhuhr: float, asr: ?float, maghrib: ?float, isha: ?float}
     */
    private function eventHours(float $jd0, DateTimeImmutable $date): array
    {
        $p       = self::$methods[$this->method];
        $horizon = -(0.833 + 0.0347 * sqrt($this->elevation));

        $noon    = $this->solarNoon($jd0);
        $sunrise = $this->eventTime($jd0, $horizon, false);
        $sunset  = $this->eventTime($jd0, $horizon, true);
        $maghrib = isset($p['maghrib']) ? $this->eventTime($jd0, -$p['maghrib'], true) : $sunset;
        $fajr    = $this->eventTime($jd0, -$p['fajr'], false);

        if (isset($p['ishaMinutes'])) {
            $minutes = $this->method === self::METHOD_MAKKAH && $this->isRamadan($date)
                ? self::MAKKAH_RAMADAN_ISHA_MINUTES
                : $p['ishaMinutes'];
            $isha = $maghrib === null ? null : $maghrib + $minutes / 60;
        } else {
            $isha = $this->eventTime($jd0, -$p['isha'], true);
        }

        if ($this->highLatitudeRule !== HighLatitudeRule::None) {
            $nextSunrise = $this->eventTime($jd0 + 1.0, $horizon, false);
            $nextSunrise = $nextSunrise === null ? null : $nextSunrise + 24.0;

            // Without a real horizon crossing the sun is treated as rising and
            // setting at 06:00 and 18:00 solar time, a 12 hour reference night.
            $refRise = $sunrise ?? $noon - 6.0;
            $refSet  = $sunset ?? $noon + 6.0;
            $night   = ($nextSunrise ?? $refRise + 24.0) - $refSet;
            $realNight = $sunset !== null && $nextSunrise !== null;

            $rule = $this->highLatitudeRule;

            $portion = $rule->nightFraction($p['fajr']) * $night;
            if ($fajr === null) {
                $fajr = $refRise - $portion;
            } elseif ($realNight && $sunrise !== null && $sunrise - $fajr > $portion) {
                $fajr = $refRise - $portion;
            }

            if (! isset($p['ishaMinutes'])) {
                $portion = $rule->nightFraction($p['isha']) * $night;
                if ($isha === null) {
                    $isha = $refSet + $portion;
                } elseif ($realNight && $sunset !== null && $isha - $sunset > $portion) {
                    $isha = $refSet + $portion;
                }
            }

            // Tehran-style Maghrib at an angle below the horizon; with the plain
            // sunset (all other methods) there is nothing to adjust.
            if (isset($p['maghrib']) && $sunset !== null) {
                $portion = $rule->nightFraction($p['maghrib']) * $night;
                if ($maghrib === null || ($realNight && $maghrib - $sunset > $portion)) {
                    $maghrib = $sunset + $portion;
                }

                // With a large portion (middle of the night, one seventh) Maghrib and Isha can
                // hit the same limit. Maghrib then falls back to the angle-based portion
                // (angle / 60), which is always shorter than Isha's, so it stays before Isha.
                if ($isha !== null && $maghrib > $isha - 1.0 / 60) {
                    $maghrib = $sunset + HighLatitudeRule::AngleBased->nightFraction($p['maghrib']) * $night;
                }
            }
        }

        return [
            'fajr'    => $fajr,
            'sunrise' => $sunrise,
            'dhuhr'   => $noon,
            'asr'     => $this->asrTime($jd0),
            'maghrib' => $maghrib,
            'isha'    => $isha,
        ];
    }

    /**
     * The next prayer strictly after $from (in the calculator's timezone).
     * After isha it rolls over to tomorrow's fajr; `date` (Y-m-d) tells which
     * day the returned prayer falls on, including a prayer that lands after
     * midnight (high latitudes, tuned times). Yesterday, today and tomorrow
     * are compared as instants. A prayer whose local date would be after 9999-12-31 does not
     * exist: the result is null once no prayer is left up to that day.
     *
     * @return array{name: string, time: string, date: string}|null
     *
     * @throws InvalidDateException when the local year of $from is outside 1-9999
     */
    public function nextPrayer(?DateTimeImmutable $from = null): ?array
    {
        $from    = ($from ?? new DateTimeImmutable('now', $this->timezone))->setTimezone($this->timezone);
        self::assertSupportedYear($from);
        $ts        = $from->getTimestamp();
        $nowMinute = $ts - ((($ts % 60) + 60) % 60); // floor to the minute, also before 1970

        // Yesterday's Isha (or a tuned time) can fall after midnight, and
        // tomorrow's Fajr before it, so the three days are compared as instants.
        $best = null;
        foreach ([$from->modify('-1 day'), $from, $from->modify('+1 day')] as $day) {
            $dayYear = (int) $day->format('Y');
            if ($dayYear < 1 || $dayYear > 9999) {
                continue; // beyond the supported years: no prayer to find there
            }

            foreach ($this->instants($day) as $name => $instant) {
                if ($instant !== null && $instant > $nowMinute && ($best === null || $instant < $best['at'])
                    && self::isSupportedYear($this->localStamp($instant))) {
                    $best = ['name' => $name, 'at' => $instant];
                }
            }
        }

        // Only when no prayer is left up to the end of 9999-12-31 (local).
        if ($best === null) {
            return null;
        }

        $date = (new DateTimeImmutable('@'.$this->localStamp($best['at'])))->format('Y-m-d');

        return ['name' => $best['name'], 'time' => $this->local($best['at']), 'date' => $date];
    }

    /**
     * Whether the local calendar day of $date falls in Ramadan of the Umm al-Qura
     * calendar (Makkah method: Isha is 120 instead of 90 minutes after Maghrib).
     * Dates outside the supported calendar range are treated as non-Ramadan.
     */
    private function isRamadan(DateTimeImmutable $date): bool
    {
        try {
            [, $month] = Hijri::gregorianToHijri((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
            // Unreachable today (the converter accepts every Gregorian year 1-9999); kept as a safety net.
            // @codeCoverageIgnoreStart
        } catch (InvalidDateException) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return $month === self::RAMADAN;
    }

    /** Solar noon, in UT hours after 0h UT of the date with Julian Day $jd0. */
    private function solarNoon(float $jd0): float
    {
        $h = 12 - $this->longitude / 15;
        for ($i = 0; $i < 2; $i++) {
            [, $eot] = $this->sun($jd0 + $h / 24);
            $h       = 12 - $this->longitude / 15 - $eot / 60;
        }

        return $h;
    }

    /**
     * UT hour at which the sun is at $altitude degrees (negative = below the
     * horizon), rising ($evening = false) or setting. The solar position is
     * re-evaluated at the event's own time (two passes), so the drift of
     * declination and equation of time during the day is included.
     *
     * @return ?float null if the sun never reaches that altitude that day
     */
    private function eventTime(float $jd0, float $altitude, bool $evening): ?float
    {
        $h = $this->solarNoon($jd0);
        for ($i = 0; $i < 2; $i++) {
            [$decl, $eot] = $this->sun($jd0 + $h / 24);
            $hourAngle    = $this->hourAngle($altitude, $decl);
            if ($hourAngle === null) {
                return null;
            }
            $noon = 12 - $this->longitude / 15 - $eot / 60;
            $h    = $evening ? $noon + $hourAngle / 15 : $noon - $hourAngle / 15;
        }

        return $h;
    }

    /**
     * Asr: the shadow of an object is `factor + tan|latitude - declination|`
     * times its height at the time, so the sun's altitude is arccot of that.
     */
    private function asrTime(float $jd0): ?float
    {
        $h = $this->solarNoon($jd0);
        for ($i = 0; $i < 2; $i++) {
            [$decl, $eot] = $this->sun($jd0 + $h / 24);
            $zenithAtNoon = abs($this->latitude - $decl);
            if ($zenithAtNoon >= 90.0) {
                return null; // sun stays below the horizon all day: no shadow to measure
            }
            $altitude     = rad2deg(atan(1 / ($this->asrFactor + tan(deg2rad($zenithAtNoon)))));
            $hourAngle    = $this->hourAngle($altitude, $decl);
            if ($hourAngle === null) {
                return null;
            }
            $h = 12 - $this->longitude / 15 - $eot / 60 + $hourAngle / 15;
        }

        return $h;
    }

    /**
     * Low-precision solar position (Meeus, Astronomical Algorithms, chapters
     * 25 and 28; the same equations as the NOAA Solar Calculator).
     *
     * @return array{0: float, 1: float} declination (degrees), equation of time (minutes)
     */
    private function sun(float $jd): array
    {
        $t = ($jd - 2451545.0) / 36525.0; // Julian centuries since J2000.0

        $l0 = $this->fixAngle(280.46646 + 36000.76983 * $t + 0.0003032 * $t * $t); // mean longitude
        $m  = deg2rad($this->fixAngle(357.52911 + 35999.05029 * $t - 0.0001537 * $t * $t)); // mean anomaly
        $e  = 0.016708634 - 0.000042037 * $t - 0.0000001267 * $t * $t; // orbit eccentricity

        // Equation of the centre
        $c = (1.914602 - 0.004817 * $t - 0.000014 * $t * $t) * sin($m)
            + (0.019993 - 0.000101 * $t) * sin(2 * $m)
            + 0.000289 * sin(3 * $m);

        $omega  = deg2rad(125.04 - 1934.136 * $t);
        $lambda = deg2rad($l0 + $c - 0.00569 - 0.00478 * sin($omega)); // apparent longitude

        // Mean obliquity of the ecliptic plus the main nutation term
        $eps0 = 23.0 + (26.0 + (21.448 - $t * (46.815 + $t * (0.00059 - $t * 0.001813))) / 60.0) / 60.0;
        $eps  = deg2rad($eps0 + 0.00256 * cos($omega));

        $decl = rad2deg(asin(sin($eps) * sin($lambda)));

        $y   = tan($eps / 2) ** 2;
        $l0r = deg2rad($l0);
        $eot = $y * sin(2 * $l0r)
            - 2 * $e * sin($m)
            + 4 * $e * $y * sin($m) * cos(2 * $l0r)
            - 0.5 * $y * $y * sin(4 * $l0r)
            - 1.25 * $e * $e * sin(2 * $m);

        return [$decl, 4 * rad2deg($eot)];
    }

    /** Hour angle in degrees for a solar altitude, or null when unreachable. */
    private function hourAngle(float $altitude, float $decl): ?float
    {
        $lat = deg2rad($this->latitude);
        $d   = deg2rad($decl);

        $denominator = cos($lat) * cos($d);
        if (abs($denominator) < 1e-12) {
            return null;
        }

        $cosH = (sin(deg2rad($altitude)) - sin($lat) * sin($d)) / $denominator;
        if ($cosH < -1.0 || $cosH > 1.0) {
            return null;
        }

        return rad2deg(acos($cosH));
    }

    private function fixAngle(float $a): float
    {
        $a = fmod($a, 360.0);

        return $a < 0 ? $a + 360 : $a;
    }

    /**
     * Local HH:MM of a Unix timestamp, using the zone's real UTC offset at that
     * instant, so it stays correct across DST changes.
     */
    private function clock(?int $timestamp): ?string
    {
        return $timestamp === null ? null : $this->local($timestamp);
    }

    private function local(int $timestamp): string
    {
        return (new DateTimeImmutable('@'.$this->localStamp($timestamp)))->format('H:i');
    }

    /**
     * The local wall clock of $timestamp as a timestamp read in UTC, rounded to the nearest minute.
     * The offset is used to the second: a zone's historic local mean time (before 1970 many zones
     * have an offset such as +3:25:44) is not cut to whole minutes.
     */
    private function localStamp(int $timestamp): int
    {
        $offset = $this->timezone->getOffset(new DateTimeImmutable('@'.$timestamp));

        return (int) (floor(($timestamp + $offset) / 60 + 0.5) * 60);
    }
}
