<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Text\Slugify;

final class SlugifyUtf8Test extends TestCase
{
    public function test_lower_cases_non_ascii_letters(): void
    {
        self::assertSame('école-привет', Slugify::make('ÉCOLE Привет'));
    }

    public function test_dots_are_dropped(): void
    {
        self::assertSame('v12', Slugify::make('v1.2'));
        self::assertSame('a-b', Slugify::make('a. b'));
    }

    public function test_invalid_utf8_text_is_rejected(): void
    {
        try {
            Slugify::make("ab\xFFcd");
            self::fail('expected exception');
        } catch (RtlyKitException $e) {
            self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
            self::assertSame(['argument' => 'text'], $e->getContext());
        }
    }

    public function test_invalid_utf8_separator_is_rejected(): void
    {
        try {
            Slugify::make('a b', "\xFF");
            self::fail('expected exception');
        } catch (RtlyKitException $e) {
            self::assertSame(ErrorCode::InvalidArgument, $e->getErrorCode());
            self::assertSame(['argument' => 'separator'], $e->getContext());
        }
    }
}
