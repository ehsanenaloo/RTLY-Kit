<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

/**
 * One holiday title on one day together with its {@see HolidayOrigin}.
 */
final class HolidayEntry
{
    public function __construct(
        public readonly string $title,
        public readonly HolidayOrigin $origin,
    ) {}
}
