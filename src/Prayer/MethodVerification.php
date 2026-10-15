<?php

declare(strict_types=1);

namespace RtlyKit\Prayer;

/**
 * How well the values of a {@see CalculationMethod} are backed. It describes the evidence, it does not
 * grade the method: the angles of a method are conventions that an authority chooses.
 */
enum MethodVerification: string
{
    /** Times computed with the method were compared with the timetable the defining authority publishes. */
    case PublishedTable = 'published_table';

    /** The values agree with several independent sources, but no timetable of the authority was found. */
    case Corroborated = 'corroborated';

    /** Defined by the caller. RTLY-Kit checks the ranges only; the caller answers for the values. */
    case Custom = 'custom';
}
