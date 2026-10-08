<?php

declare(strict_types=1);

namespace RtlyKit\Exceptions;

/**
 * Stable, machine-readable identifier of every failure the library raises on purpose.
 *
 * Messages may be reworded between releases; these values are part of the public
 * API and do not change. Read one with {@see RtlyKitThrowable::getErrorCode()}.
 */
enum ErrorCode: string
{
    /** Generic fallback for an invalid argument that has no more specific code. */
    case InvalidArgument = 'invalid_argument';

    /** A date or time value is malformed, impossible or unparsable. */
    case InvalidDate = 'invalid_date';

    /** A date, year or timestamp lies outside the supported range. */
    case DateOutOfRange = 'date_out_of_range';

    /** A value cannot be interpreted as a number. */
    case InvalidNumber = 'invalid_number';

    /** A number is larger than the supported magnitude. */
    case NumberTooLarge = 'number_too_large';

    /** NaN or infinity was supplied where a finite number is required. */
    case NonFiniteNumber = 'non_finite_number';

    /** Number words are unknown or ill-formed. */
    case InvalidNumberWords = 'invalid_number_words';

    /** A string input exceeds a documented size cap. */
    case InputTooLong = 'input_too_long';

    /** An unknown prayer-time city, calculation method or Asr factor. */
    case InvalidPrayerConfig = 'invalid_prayer_config';

    /** A locale other than a supported one was requested. */
    case UnsupportedLocale = 'unsupported_locale';

    /** A bundled data table is missing or corrupt (a packaging problem). */
    case DataUnavailable = 'data_unavailable';
}
