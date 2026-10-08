<?php

declare(strict_types=1);

namespace RtlyKit\Prayer;

/**
 * How Fajr and Isha are derived when the twilight angle is never reached, or
 * is reached implausibly far from sunset/sunrise (high latitudes in summer).
 *
 * The night is the interval from sunset to the next sunrise. Fajr is never
 * earlier than (sunrise - portion x night) and Isha never later than
 * (sunset + portion x night); the portion depends on the rule. The rule is
 * applied only to a time that cannot be computed, or that falls outside that
 * bound; every other time keeps its exact astronomical value.
 *
 * Conventions: PrayTimes "High Latitude Adjustments"
 * (https://praytimes.org/calculation, fetched 2026-10-08): middle of the night
 * (night divided in two halves), one-seventh of the night, and angle-based
 * (the night divided into 60/angle parts, i.e. portion = angle / 60).
 */
enum HighLatitudeRule: string
{
    /** No adjustment: a time whose angle is not reached is null. */
    case None = 'none';

    /** Fajr and Isha are at most half the night from sunrise and sunset. */
    case NightMiddle = 'night_middle';

    /** Fajr and Isha are at most one seventh of the night from sunrise and sunset. */
    case OneSeventh = 'one_seventh';

    /** The portion of the night is (twilight angle) / 60. */
    case AngleBased = 'angle_based';

    /**
     * Portion of the night (0..1) allowed between the horizon event and a
     * time defined by the twilight $angle (degrees). None allows no limit.
     */
    public function nightFraction(float $angle): float
    {
        return match ($this) {
            self::None        => 1.0,
            self::NightMiddle => 1.0 / 2.0,
            self::OneSeventh  => 1.0 / 7.0,
            self::AngleBased  => $angle / 60.0,
        };
    }
}
