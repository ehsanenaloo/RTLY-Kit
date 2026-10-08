<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Number;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;

/**
 * Exception messages and contexts that echo user input stay valid UTF-8
 * (and short) even when the input is not.
 */
final class ErrorMessagesUtf8Test extends TestCase
{
    public function test_messages_echoing_invalid_input_are_valid_utf8_and_truncated(): void
    {
        $bad = str_repeat("\xFF", 200);
        $calls = [
            static fn () => NumberToWords::convert('1', $bad),
            static fn () => NumberToWords::convert($bad),
            static fn () => NumberToWords::fromWords($bad),
            static fn () => Format::withSeparator($bad),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                self::fail('expected exception');
            } catch (RtlyKitException $e) {
                self::assertSame(1, preg_match('//u', $e->getMessage()));
                self::assertSame(1, preg_match('//u', json_encode($e->getContext(), JSON_INVALID_UTF8_SUBSTITUTE) ?: ''));
                self::assertLessThan(200, strlen($e->getMessage()));
            }
        }
    }
}
