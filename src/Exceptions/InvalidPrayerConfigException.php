<?php

declare(strict_types=1);

namespace RtlyKit\Exceptions;

/**
 * Thrown for an unknown prayer-time city, calculation method or Asr factor.
 */
class InvalidPrayerConfigException extends RtlyKitException
{
    protected static function defaultErrorCode(): ErrorCode
    {
        return ErrorCode::InvalidPrayerConfig;
    }
}
