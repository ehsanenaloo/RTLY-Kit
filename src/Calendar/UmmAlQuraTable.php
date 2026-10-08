<?php

declare(strict_types=1);

namespace RtlyKit\Calendar;

use RtlyKit\Exceptions\RtlyKitException;

/**
 * Lazy, cached loader for the Umm al-Qura month-length table kept in
 * `resources/data/umm-al-qura.php`, with integrity checks.
 *
 * @internal Not part of the public API; used by {@see Hijri}.
 */
final class UmmAlQuraTable
{
    private const DATA_FILE = __DIR__.'/../../resources/data/umm-al-qura.php';

    /** A year of twelve 29/30-day months has 348..360 days; Umm al-Qura years have 353..355. */
    private const MIN_YEAR_DAYS = 353;
    private const MAX_YEAR_DAYS = 355;

    private static ?self $instance = null;

    /**
     * @param list<int> $months bitmask per year (bit 0 = Muharram; set = 30 days)
     * @param list<int> $starts JDN of 1 Muharram per year, plus one sentinel entry
     */
    private function __construct(
        public readonly int $firstYear,
        public readonly int $lastYear,
        private readonly array $months,
        private readonly array $starts,
    ) {}

    /** The table shipped with the package (loaded once per process). */
    public static function default(): self
    {
        return self::$instance ??= self::fromFile(self::DATA_FILE);
    }

    /**
     * @throws RtlyKitException when the file is missing or fails the integrity checks
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RtlyKitException("Umm al-Qura data file not found or unreadable: {$path}");
        }

        /** @var mixed $data */
        $data = (static function (string $file): mixed {
            return require $file;
        })($path);

        return self::fromArray($data);
    }

    /**
     * Validates and builds a table. Checks: shape and integer types, the
     * year count equals last - first + 1, every bitmask fits 12 bits, every
     * year has 353..355 days, and the year-start JDNs increase strictly.
     *
     * @throws RtlyKitException
     */
    public static function fromArray(mixed $data): self
    {
        if (! is_array($data)
            || ! isset($data['first_year'], $data['last_year'], $data['start_jdn'], $data['months'])
            || ! is_int($data['first_year']) || ! is_int($data['last_year']) || ! is_int($data['start_jdn'])
            || ! is_array($data['months']) || ! array_is_list($data['months'])
        ) {
            throw new RtlyKitException('Umm al-Qura table: malformed structure.');
        }

        $first = $data['first_year'];
        $last = $data['last_year'];
        $months = $data['months'];

        if ($first < Hijri::MIN_YEAR || $last < $first || $last > Hijri::MAX_YEAR) {
            throw new RtlyKitException("Umm al-Qura table: invalid year range {$first}..{$last}.");
        }
        if (count($months) !== $last - $first + 1) {
            throw new RtlyKitException(sprintf(
                'Umm al-Qura table: expected %d year entries, found %d.',
                $last - $first + 1,
                count($months),
            ));
        }

        $jdn = $data['start_jdn'];
        $starts = [];
        $clean = [];
        foreach ($months as $i => $bits) {
            $year = $first + $i;
            if (! is_int($bits) || $bits < 0 || $bits > 0xFFF) {
                throw new RtlyKitException("Umm al-Qura table: invalid month bitmask for AH {$year}.");
            }
            $days = 348;
            for ($m = 0; $m < 12; $m++) {
                $days += ($bits >> $m) & 1;
            }
            if ($days < self::MIN_YEAR_DAYS || $days > self::MAX_YEAR_DAYS) {
                throw new RtlyKitException("Umm al-Qura table: AH {$year} has {$days} days.");
            }
            $starts[] = $jdn;
            $clean[] = $bits;
            $jdn += $days;
        }
        $starts[] = $jdn;

        $previous = null;
        foreach ($starts as $start) {
            if ($previous !== null && $start <= $previous) {
                throw new RtlyKitException('Umm al-Qura table: year starts are not strictly increasing.'); // @codeCoverageIgnore
            }
            $previous = $start;
        }

        return new self($first, $last, $clean, $starts);
    }

    public function covers(int $year): bool
    {
        return $year >= $this->firstYear && $year <= $this->lastYear;
    }

    /** Month-length bitmask of a covered year (bit 0 = Muharram; set bit = 30 days). */
    public function bits(int $year): int
    {
        return $this->months[$year - $this->firstYear];
    }

    public function monthLength(int $year, int $month): int
    {
        return (($this->bits($year) >> ($month - 1)) & 1) === 1 ? 30 : 29;
    }

    /** JDN of 1 Muharram of a covered year. */
    public function yearStart(int $year): int
    {
        return $this->starts[$year - $this->firstYear];
    }

    /** First JDN covered (1 Muharram of the first year). */
    public function firstJdn(): int
    {
        return $this->starts[0];
    }

    /** First JDN AFTER the table (1 Muharram of last year + 1). */
    public function endJdn(): int
    {
        return $this->starts[$this->lastYear - $this->firstYear + 1];
    }
}
