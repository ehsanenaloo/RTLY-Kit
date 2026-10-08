<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Validation\DataTables;

/**
 * Lazy, cached, integrity-checked loader of `resources/data/iran-official-holidays.php`.
 *
 * The table is validated once per process the first time a year is asked for; a damaged
 * file raises {@see RtlyKitException} with {@see ErrorCode::DataUnavailable}.
 *
 * @internal
 *
 * @phpstan-type YearData array{source: HolidaySource, holidays: array<string, list<string>>}
 */
final class HolidayData
{
    private const DATA_NAME = 'iran-official-holidays';

    /** @var array<int, YearData>|null */
    private static ?array $cache = null;

    /**
     * The recorded Islamic holidays of a Jalali year, keyed by "month/day", or null when the year is not in the table.
     *
     * @return YearData|null
     */
    public static function year(int $jalaliYear): ?array
    {
        return self::all()[$jalaliYear] ?? null;
    }

    /**
     * @return list<int> the covered Jalali years, ascending
     */
    public static function years(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<int, YearData>
     */
    private static function all(): array
    {
        return self::$cache ??= self::validate(DataTables::load(self::DATA_NAME));
    }

    /**
     * Check a raw table and convert it to its normalised form.
     *
     * @param  array<array-key, mixed>  $raw
     * @return array<int, YearData>
     *
     * @throws RtlyKitException with ErrorCode::DataUnavailable when anything is wrong
     */
    public static function validate(array $raw): array
    {
        $known = array_flip(HolidayTitles::knownIslamic());
        $result = [];

        foreach ($raw as $year => $entry) {
            if (! is_int($year) || $year < 1 || $year > Jalali::MAX_YEAR) {
                throw self::corrupt('year key is not a Jalali year', ['year' => $year]);
            }
            if (! is_array($entry)) {
                throw self::corrupt('year entry is not an array', ['year' => $year]);
            }

            $source = match ($entry['status'] ?? null) {
                'official' => HolidaySource::Official,
                'single_source' => HolidaySource::Reported,
                default => throw self::corrupt('unknown status', ['year' => $year]),
            };

            $sources = $entry['sources'] ?? null;
            if (! is_array($sources) || $sources === []) {
                throw self::corrupt('no sources recorded', ['year' => $year]);
            }

            $holidays = $entry['holidays'] ?? null;
            if (! is_array($holidays) || $holidays === []) {
                throw self::corrupt('no holidays recorded', ['year' => $year]);
            }

            $normalised = [];
            foreach ($holidays as $monthDay => $titles) {
                if (! is_string($monthDay) || preg_match('~^([1-9]|1[0-2])/([1-9][0-9]?)$~', $monthDay, $m) !== 1) {
                    throw self::corrupt('malformed month/day key', ['year' => $year, 'key' => $monthDay]);
                }
                if ((int) $m[2] > Jalali::daysInMonth($year, (int) $m[1])) {
                    throw self::corrupt('day does not exist in that month', ['year' => $year, 'key' => $monthDay]);
                }
                if (! is_array($titles) || $titles === [] || ! array_is_list($titles)) {
                    throw self::corrupt('titles must be a non-empty list', ['year' => $year, 'key' => $monthDay]);
                }
                foreach ($titles as $title) {
                    if (! is_string($title) || ! isset($known[$title])) {
                        throw self::corrupt('unknown holiday title', ['year' => $year, 'key' => $monthDay]);
                    }
                }
                /** @var list<string> $titles */
                if (count(array_unique($titles)) !== count($titles)) {
                    throw self::corrupt('duplicate title on one day', ['year' => $year, 'key' => $monthDay]);
                }

                $normalised[$monthDay] = $titles;
            }

            $result[$year] = ['source' => $source, 'holidays' => $normalised];
        }

        ksort($result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function corrupt(string $reason, array $context): RtlyKitException
    {
        return RtlyKitException::because(
            ErrorCode::DataUnavailable,
            sprintf("Data table '%s' is corrupt: %s.", self::DATA_NAME, $reason),
            ['table' => self::DATA_NAME] + $context,
        );
    }
}
