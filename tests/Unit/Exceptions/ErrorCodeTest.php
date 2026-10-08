<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Exceptions;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Exceptions\RtlyKitThrowable;
use RtlyKit\Exceptions\UnsupportedLocaleException;
use RtlyKit\Number\Format;
use RtlyKit\Number\NumberToWords;
use RtlyKit\Text\Slugify;
use RuntimeException;

final class ErrorCodeTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<RtlyKitException>, 1: ErrorCode}>
     */
    public static function defaults(): array
    {
        return [
            'base' => [RtlyKitException::class, ErrorCode::InvalidArgument],
            'date' => [InvalidDateException::class, ErrorCode::InvalidDate],
            'number' => [InvalidNumberException::class, ErrorCode::InvalidNumber],
            'prayer' => [InvalidPrayerConfigException::class, ErrorCode::InvalidPrayerConfig],
            'locale' => [UnsupportedLocaleException::class, ErrorCode::UnsupportedLocale],
        ];
    }

    /**
     * @param class-string<RtlyKitException> $class
     */
    #[DataProvider('defaults')]
    public function test_every_exception_has_a_default_code_and_empty_context(string $class, ErrorCode $expected): void
    {
        $e = new $class('message');

        self::assertSame($expected, $e->getErrorCode());
        self::assertSame([], $e->getContext());
        self::assertSame('message', $e->getMessage());
        self::assertSame(0, $e->getCode());
        self::assertInstanceOf(RtlyKitThrowable::class, $e);
        self::assertInstanceOf(InvalidArgumentException::class, $e);
    }

    /**
     * @param class-string<RtlyKitException> $class
     */
    #[DataProvider('defaults')]
    public function test_existing_constructor_shapes_keep_working(string $class, ErrorCode $expected): void
    {
        $previous = new RuntimeException('inner');

        $positional = new $class('m', 7, $previous);
        self::assertSame(7, $positional->getCode());
        self::assertSame($previous, $positional->getPrevious());
        self::assertSame($expected, $positional->getErrorCode());

        $named = new $class('m', previous: $previous);
        self::assertSame($previous, $named->getPrevious());

        self::assertSame('', (new $class())->getMessage());
    }

    /**
     * @param class-string<RtlyKitException> $class
     */
    #[DataProvider('defaults')]
    public function test_code_and_context_can_be_overridden(string $class, ErrorCode $expected): void
    {
        $e = new $class('m', errorCode: ErrorCode::DateOutOfRange, context: ['year' => 99999]);

        self::assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
        self::assertSame(['year' => 99999], $e->getContext());

        $b = $class::because(ErrorCode::InputTooLong, 'too long', ['limit' => 4096], new RuntimeException('x'));
        self::assertInstanceOf($class, $b);
        self::assertSame(ErrorCode::InputTooLong, $b->getErrorCode());
        self::assertSame('too long', $b->getMessage());
        self::assertSame(['limit' => 4096], $b->getContext());
        self::assertSame('x', $b->getPrevious()?->getMessage());
    }

    public function test_enum_values_are_unique_snake_case_strings(): void
    {
        $values = array_map(static fn (ErrorCode $c): string => $c->value, ErrorCode::cases());

        self::assertSame(count($values), count(array_unique($values)));

        foreach ($values as $value) {
            self::assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)*$/', $value);
        }

        self::assertSame(ErrorCode::NumberTooLarge, ErrorCode::from('number_too_large'));
        self::assertNull(ErrorCode::tryFrom('nope'));
    }

    public function test_library_failures_carry_specific_codes_and_context(): void
    {
        $cases = [
            [fn () => NumberToWords::fromWords('foo'), ErrorCode::InvalidNumberWords, []],
            [fn () => NumberToWords::fromWords(''), ErrorCode::InvalidNumberWords, []],
            [fn () => NumberToWords::fromWords(str_repeat('a', 4097)), ErrorCode::InputTooLong, ['limit' => 4096]],
            [fn () => NumberToWords::convert(NAN), ErrorCode::NonFiniteNumber, []],
            [fn () => NumberToWords::convert(1.5), ErrorCode::InvalidNumber, []],
            [fn () => NumberToWords::convert('abc'), ErrorCode::InvalidNumber, []],
            [fn () => NumberToWords::convert(str_repeat('9', 30)), ErrorCode::NumberTooLarge, ['limit' => '10^21 - 1']],
            [fn () => NumberToWords::convert('1' . str_repeat('0', 27), 'ar'), ErrorCode::NumberTooLarge, ['limit' => '10^27 - 1']],
            [fn () => NumberToWords::convert(1, 'xx'), ErrorCode::UnsupportedLocale, ['locale' => 'xx']],
            [fn () => Format::withSeparator(INF), ErrorCode::NonFiniteNumber, []],
            [fn () => Format::withSeparator(str_repeat('9', 4097)), ErrorCode::InputTooLong, ['limit' => 4096]],
            [fn () => Format::withSeparator(str_repeat('9', 1001)), ErrorCode::InputTooLong, ['limit' => 1000]],
            [fn () => Format::withSeparator('abc'), ErrorCode::InvalidNumber, []],
            [fn () => Format::ordinal(NAN), ErrorCode::NonFiniteNumber, []],
            [fn () => Format::ordinal(-1), ErrorCode::InvalidNumber, []],
            [fn () => Slugify::make('a', str_repeat('-', 65)), ErrorCode::InputTooLong, ['argument' => 'separator', 'limit' => 64]],
            [fn () => Slugify::make('a', "\xff"), ErrorCode::InvalidArgument, ['argument' => 'separator']],
        ];

        foreach ($cases as $i => [$thrower, $code, $context]) {
            try {
                $thrower();
                self::fail("case #{$i} did not throw");
            } catch (RtlyKitThrowable $e) {
                self::assertSame($code, $e->getErrorCode(), "case #{$i}: " . $e->getMessage());
                self::assertSame($context, $e->getContext(), "case #{$i}");
            }
        }
    }

    public function test_messages_are_unchanged(): void
    {
        $this->expectException(InvalidNumberException::class);
        $this->expectExceptionMessage('Ordinals are defined for non-negative integers only.');
        Format::ordinal(-3);
    }
}
