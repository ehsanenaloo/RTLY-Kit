<?php

declare(strict_types=1);

namespace RtlyKit\Exceptions;

/**
 * Thrown when a locale other than a supported one is requested.
 */
class UnsupportedLocaleException extends RtlyKitException
{
    protected static function defaultErrorCode(): ErrorCode
    {
        return ErrorCode::UnsupportedLocale;
    }
}
