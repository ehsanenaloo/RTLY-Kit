<?php

declare(strict_types=1);

namespace RtlyKit\Exceptions;

use InvalidArgumentException;
use Throwable;

/**
 * Base class of every exception thrown by RTLY-Kit.
 *
 * Every library exception is an invalid-argument condition, so this class
 * extends {@see InvalidArgumentException} (and therefore LogicException and
 * Exception): code that already catches \InvalidArgumentException, \LogicException
 * or \Exception keeps working. New code should catch this class or the
 * {@see RtlyKitThrowable} marker interface.
 *
 * Each exception carries a stable {@see ErrorCode} and an optional context
 * array. Subclasses supply a default code; pass `$errorCode` to be specific.
 *
 * @phpstan-consistent-constructor
 */
class RtlyKitException extends InvalidArgumentException implements RtlyKitThrowable
{
    private readonly ErrorCode $errorCode;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?ErrorCode $errorCode = null,
        private readonly array $context = [],
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorCode = $errorCode ?? static::defaultErrorCode();
    }

    /**
     * Named constructor: `InvalidDateException::because(ErrorCode::DateOutOfRange, 'msg', ['year' => 99999])`.
     *
     * @param  array<string, mixed>  $context
     */
    public static function because(ErrorCode $errorCode, string $message, array $context = [], ?Throwable $previous = null): static
    {
        return new static($message, 0, $previous, $errorCode, $context);
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * The code used when none is passed to the constructor.
     */
    protected static function defaultErrorCode(): ErrorCode
    {
        return ErrorCode::InvalidArgument;
    }
}
