<?php

declare(strict_types=1);

namespace RtlyKit\Exceptions;

use Throwable;

/**
 * Marker interface implemented by every exception the library throws on purpose.
 *
 * `catch (RtlyKitThrowable $e)` handles all of them with a single clause.
 */
interface RtlyKitThrowable extends Throwable
{
    /**
     * Stable machine-readable failure code (unlike the message, never reworded).
     */
    public function getErrorCode(): ErrorCode;

    /**
     * Structured data describing the failure (offending values, limits, ...).
     * Never contains secrets; may be empty.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array;
}
