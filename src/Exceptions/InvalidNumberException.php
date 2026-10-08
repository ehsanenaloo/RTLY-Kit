<?php

declare(strict_types=1);

namespace RtlyKit\Exceptions;

/**
 * Thrown when a value cannot be interpreted as a number (words, separators,
 * ordinals) or exceeds a documented size limit.
 */
class InvalidNumberException extends RtlyKitException
{
    protected static function defaultErrorCode(): ErrorCode
    {
        return ErrorCode::InvalidNumber;
    }
}
