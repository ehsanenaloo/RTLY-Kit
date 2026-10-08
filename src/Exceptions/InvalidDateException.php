<?php

declare(strict_types=1);

namespace RtlyKit\Exceptions;

/**
 * Thrown for invalid or unparsable dates, times and timezones.
 */
class InvalidDateException extends RtlyKitException
{
    protected static function defaultErrorCode(): ErrorCode
    {
        return ErrorCode::InvalidDate;
    }
}
