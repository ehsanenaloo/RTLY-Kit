<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Integration\Laravel\Casts;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Laravel\Casts\JalaliCast;

/**
 * The cast reads its own Jalali string form (years 1200-1599, "/" or "-" chosen
 * separately for each separator, Persian digits, optional time) before it falls
 * back to the general parser. 1 Farvardin 1404 = 2025-03-21.
 */
final class JalaliCastStringFormsTest extends TestCase
{
    private function model(): Model
    {
        return new class () extends Model {};
    }

    /** @return array<string, array{string, string}> */
    public static function jalaliStrings(): array
    {
        return [
            'slashes' => ['1404/01/15', '2025-04-04 00:00:00'],
            'mixed separators, slash then dash' => ['1404/01-15', '2025-04-04 00:00:00'],
            'mixed separators, dash then slash' => ['1404-01/15', '2025-04-04 00:00:00'],
            'surrounding whitespace with mixed separators' => [" \t1404/01-15 \n", '2025-04-04 00:00:00'],
            'time with seconds' => ['1404/01/15 10:30:45', '2025-04-04 10:30:45'],
            'time with seconds, mixed separators' => ['1404-01/15 10:30:45', '2025-04-04 10:30:45'],
            'time without seconds' => ['1404/01/15 08:05', '2025-04-04 08:05:00'],
            'T separator' => ['1404/01/15T08:05', '2025-04-04 08:05:00'],
            'single digit parts' => ['1404-1-5 1:02', '2025-03-25 01:02:00'],
            'Persian digits' => ['۱۴۰۴/۰۱/۱۵ ۱۰:۳۰:۴۵', '2025-04-04 10:30:45'],
            'a year of the last century' => ['1300/01/01', '1921-03-21 00:00:00'],
            'trailing whitespace after the time' => ["1404/01/15 10:30:45 \n", '2025-04-04 10:30:45'],
        ];
    }

    #[DataProvider('jalaliStrings')]
    public function test_jalali_strings_are_stored_as_gregorian(string $input, string $expected): void
    {
        self::assertSame($expected, (new JalaliCast())->set($this->model(), 'at', $input, []));
    }

    public function test_an_impossible_jalali_date_is_rejected_not_reinterpreted(): void
    {
        foreach (['1404/13/01', '1404/12/30', '1404/01/32', '1404-00-10'] as $input) {
            try {
                (new JalaliCast())->set($this->model(), 'at', $input, []);
                self::fail("{$input} must be rejected");
            } catch (InvalidDateException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_unsupported_values_report_the_attribute_and_the_type(): void
    {
        $cast = new JalaliCast();

        foreach ([[1.5, 'float'], [['x'], 'array'], [true, 'bool'], [new \stdClass(), 'stdClass']] as [$value, $type]) {
            try {
                $cast->set($this->model(), 'published_at', $value, []);
                self::fail('unsupported value must be rejected');
            } catch (InvalidDateException $e) {
                self::assertSame("Cannot cast value for 'published_at' to a date.", $e->getMessage());
                self::assertSame(['attribute' => 'published_at', 'type' => $type], $e->getContext());
            }
        }
    }

    public function test_empty_string_and_null_are_stored_as_null(): void
    {
        $cast = new JalaliCast();

        self::assertNull($cast->set($this->model(), 'at', null, []));
        self::assertNull($cast->set($this->model(), 'at', '', []));
        self::assertNull($cast->get($this->model(), 'at', null, []));
        self::assertNull($cast->get($this->model(), 'at', '', []));
    }

    public function test_timestamps_are_stored_in_the_default_zone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            self::assertSame('1970-01-01 00:00:00', (new JalaliCast())->set($this->model(), 'at', 0, []));
            self::assertSame('2026-03-21 00:00:00', (new JalaliCast())->set($this->model(), 'at', 1774051200, []));
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
