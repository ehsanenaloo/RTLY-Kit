<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use RtlyKit\Globals;

/**
 * Every test that registers globals runs in its own process: defined functions
 * can never be removed, so they must not leak into the rest of the suite.
 */
final class GlobalsTest extends TestCase
{
    public function test_autoload_defines_no_global_helper(): void
    {
        foreach (Globals::NAMES as $name) {
            self::assertFalse(function_exists($name), "global {$name}() must not exist before Globals::register()");
            self::assertTrue(function_exists('RtlyKit\\'.$name), "RtlyKit\\{$name}() must be autoloaded");
        }
    }

    public function test_names_match_the_namespaced_helpers_exactly(): void
    {
        $namespaced = [];
        foreach (get_defined_functions()['user'] as $fn) {
            if (str_starts_with($fn, 'rtlykit\\') && (new ReflectionFunction($fn))->getFileName() === realpath(__DIR__.'/../../src/helpers.php')) {
                $namespaced[] = substr($fn, strlen('rtlykit\\'));
            }
        }

        $names = Globals::NAMES;
        sort($namespaced);
        sort($names);

        self::assertSame($names, $namespaced);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_register_defines_every_free_name_and_reports_nothing_skipped(): void
    {
        self::assertSame([], Globals::register());

        foreach (Globals::NAMES as $name) {
            self::assertTrue(function_exists($name), $name);
        }

        self::assertSame('1403/01/01', \jdate('2024-03-20', new \DateTimeZone('UTC'))->toDateString());
        self::assertTrue(\is_national_code('0499370899'));
        self::assertSame('۱۲۳', \to_persian(123));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_global_signatures_match_the_namespaced_helpers(): void
    {
        Globals::register();

        foreach (Globals::NAMES as $name) {
            $global = new ReflectionFunction($name);
            $namespaced = new ReflectionFunction('RtlyKit\\'.$name);

            self::assertSame((string) $namespaced->getReturnType(), (string) $global->getReturnType(), $name);
            self::assertSame($namespaced->getNumberOfParameters(), $global->getNumberOfParameters(), $name);
            self::assertSame($namespaced->getNumberOfRequiredParameters(), $global->getNumberOfRequiredParameters(), $name);

            foreach ($namespaced->getParameters() as $i => $param) {
                $other = $global->getParameters()[$i];
                self::assertSame($param->getName(), $other->getName(), $name);
                self::assertSame((string) $param->getType(), (string) $other->getType(), $name);
                self::assertSame($param->isOptional(), $other->isOptional(), $name);
                self::assertSame(
                    $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                    $other->isDefaultValueAvailable() ? $other->getDefaultValue() : null,
                    $name,
                );
            }
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_colliding_user_function_is_skipped_reported_and_left_untouched(): void
    {
        eval('function is_mobile($v) { return "user-defined"; } function ordinal() { return "user-ordinal"; }');

        $skipped = Globals::register();

        self::assertSame(['is_mobile', 'ordinal'], $skipped);
        self::assertSame('user-defined', \is_mobile('09123456789'));
        self::assertSame('user-ordinal', \ordinal());

        // The namespaced versions are unaffected and every other name is defined.
        self::assertTrue(\RtlyKit\is_mobile('09123456789'));
        self::assertSame('اول', \RtlyKit\ordinal(1));
        self::assertTrue(function_exists('is_sheba'));
        self::assertTrue(function_exists('jdate'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_register_is_idempotent_and_keeps_reporting_user_collisions(): void
    {
        eval('function format_number() { return "mine"; }');

        $first = Globals::register();
        $definedAfterFirst = get_defined_functions()['user'];
        $second = Globals::register();
        $third = Globals::register();

        self::assertSame(['format_number'], $first);
        self::assertSame($first, $second);
        self::assertSame($first, $third);
        self::assertSame($definedAfterFirst, get_defined_functions()['user']);
        self::assertSame('mine', \format_number());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_repeated_registration_without_collisions_reports_nothing(): void
    {
        self::assertSame([], Globals::register());
        self::assertSame([], Globals::register());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_function_that_exists_in_another_case_counts_as_a_collision(): void
    {
        eval('function JDate() { return "case"; }');

        self::assertSame(['jdate'], Globals::register());
    }
}
