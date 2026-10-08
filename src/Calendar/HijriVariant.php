<?php

declare(strict_types=1);

namespace RtlyKit\Calendar;

/**
 * Which Hijri month-length rule set a {@see Hijri} instance uses.
 */
enum HijriVariant: string
{
    /**
     * Saudi civil Umm al-Qura calendar, from an embedded month-length table
     * covering AH 1300-1500 (1882-11-12 .. 2077-11-16 CE). Outside that
     * range the tabular rules are used as a fallback.
     */
    case UmmAlQura = 'umm_al_qura';

    /**
     * Arithmetic civil ("Kuwaiti" / Type IIa) calendar: 30-year cycle with
     * 11 leap years. Deterministic and unbounded, but may differ from real
     * observation/Umm al-Qura by one or two days.
     */
    case Tabular = 'tabular';
}
