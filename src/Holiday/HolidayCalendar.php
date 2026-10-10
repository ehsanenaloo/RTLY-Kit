<?php

declare(strict_types=1);

namespace RtlyKit\Holiday;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RtlyKit\Calendar\CalendarLimits;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Number\Digits;

/**
 * Iranian public holidays with a configurable source of truth.
 *
 * Iran fixes its religious holidays by moon sighting, so computing them from the Umm al-Qura table can be
 * 0-2 days off. For every day the calendar answers from, in order of precedence:
 *
 * 1. your own overrides: {@see self::withHoliday()}, {@see self::withoutHoliday()} and
 *    {@see self::withHijriMonthStart()};
 * 2. the published official table (Jalali years it covers, see {@see self::sourceOf()}); switch off with
 *    {@see self::withOfficialData()};
 * 3. an estimate from the Umm al-Qura table, shifted by {@see self::withIslamicOffset()}.
 *
 * Fixed Jalali holidays (Nowruz, 22 Bahman, ...) are exact and never moved by an offset or a month start.
 *
 * Instances are immutable: every `with*` method returns a new calendar and nothing is shared or global.
 * Estimated Islamic holidays are reported only inside the embedded Umm al-Qura range (AH 1300-1500).
 */
final class HolidayCalendar
{
    /** Largest accepted Islamic offset, in days, in either direction. */
    public const MAX_OFFSET = 3;

    /** Largest accepted difference, in days, between a given Hijri month start and the computed one. */
    public const MAX_MONTH_START_DEVIATION = 3;

    /** Longest accepted holiday title, in characters. */
    public const MAX_TITLE_LENGTH = 200;

    private const MAX_INPUT_LENGTH = 64;

    /** Jalali::make() reads a text year at or above this as Gregorian; a holiday date string rejects it. */
    private const GREGORIAN_YEAR_FROM = 1700;

    private const CONFIG_KEYS = ['islamic_offset', 'hijri_month_starts', 'extra', 'removed', 'use_official_data'];

    /**
     * @param  array<int, int>  $monthStarts  Hijri (year * 100 + month) => day number of the first day
     * @param  array<string, list<string>>  $extra  Jalali "YYYY/MM/DD" => added titles
     * @param  array<string, true|list<string>>  $removed  Jalali "YYYY/MM/DD" => true (whole day) or removed titles
     */
    private function __construct(
        private readonly int $offset,
        private readonly array $monthStarts,
        private readonly array $extra,
        private readonly array $removed,
        private readonly bool $useOfficial,
    ) {}

    /**
     * The default calendar: official data where it exists, Umm al-Qura estimates elsewhere, no overrides.
     */
    public static function default(): self
    {
        return new self(0, [], [], [], true);
    }

    /**
     * Build a calendar from the shape of the `holidays` entry of the Laravel config file
     * (`resources/config/rtly-kit.php`). Missing keys take their defaults; unknown keys are rejected.
     *
     * - `islamic_offset` (int, -3..3; an int-like string such as '1' or '-2', e.g. from env(), is cast to int)
     * - `hijri_month_starts` (map "1447-10" => "2026-03-20", the Gregorian first day of that month)
     * - `extra` (map "1405/02/03" => title or list of titles)
     * - `removed` (list of dates removing the whole day, or map "date" => title or list of titles)
     * - `use_official_data` (bool)
     *
     * @param  array<array-key, mixed>  $config
     *
     * @throws InvalidDateException when a value is malformed
     */
    public static function fromArray(array $config): self
    {
        foreach (array_keys($config) as $key) {
            if (! in_array($key, self::CONFIG_KEYS, true)) {
                throw InvalidDateException::because(
                    ErrorCode::InvalidArgument,
                    sprintf('Unknown holiday option "%s". Allowed: %s.', (string) $key, implode(', ', self::CONFIG_KEYS)),
                    ['option' => $key],
                );
            }
        }

        $offset = $config['islamic_offset'] ?? 0;
        // An int-like string ('1', '-2', '+3') is accepted because env() returns strings; floats and other strings are not.
        if (is_string($offset) && preg_match('~^[+-]?[0-9]{1,18}$~', $offset) === 1) {
            $offset = (int) $offset;
        }
        if (! is_int($offset)) {
            throw self::invalidOption('islamic_offset', 'an integer');
        }

        $use = $config['use_official_data'] ?? true;
        if (! is_bool($use)) {
            throw self::invalidOption('use_official_data', 'a boolean');
        }

        $calendar = self::default()->withIslamicOffset($offset)->withOfficialData($use);

        foreach (self::arrayOption($config, 'hijri_month_starts') as $key => $value) {
            if (preg_match('~^([0-9]{1,4})[-/]([0-9]{1,2})$~D', Digits::toEnglish((string) $key), $m) !== 1
                || ! (is_string($value) || $value instanceof DateTimeInterface)) {
                throw self::invalidOption('hijri_month_starts', 'a map of "year-month" => Gregorian date');
            }
            $calendar = $calendar->withHijriMonthStart((int) $m[1], (int) $m[2], $value);
        }

        foreach (self::arrayOption($config, 'extra') as $date => $titles) {
            foreach (is_array($titles) ? $titles : [$titles] as $title) {
                if (! is_string($date) || ! is_string($title)) {
                    throw self::invalidOption('extra', 'a map of date => title or list of titles');
                }
                $calendar = $calendar->withHoliday($date, $title);
            }
        }

        foreach (self::arrayOption($config, 'removed') as $date => $titles) {
            if (is_int($date)) {
                if (! is_string($titles)) {
                    throw self::invalidOption('removed', 'a list of dates or a map of date => title(s)');
                }
                $calendar = $calendar->withoutHoliday($titles);

                continue;
            }
            if ($titles === null || $titles === true) {
                $calendar = $calendar->withoutHoliday($date);

                continue;
            }
            foreach (is_array($titles) ? $titles : [$titles] as $title) {
                if (! is_string($title)) {
                    throw self::invalidOption('removed', 'a list of dates or a map of date => title(s)');
                }
                $calendar = $calendar->withoutHoliday($date, $title);
            }
        }

        return $calendar;
    }

    /* -----------------------------------------------------------------
     |  Configuration (each returns a new calendar)
     | -----------------------------------------------------------------
     */

    /**
     * Shift every Islamic holiday that is estimated (years without official data) by a number of days.
     * Use +1 when Iran's announced dates keep coming a day after the table's. Official years are unaffected.
     *
     * @throws InvalidDateException when the offset is outside -3..3
     */
    public function withIslamicOffset(int $days): self
    {
        if ($days < -self::MAX_OFFSET || $days > self::MAX_OFFSET) {
            throw InvalidDateException::because(
                ErrorCode::InvalidArgument,
                sprintf('Islamic offset %d is outside the supported range -%d..%d.', $days, self::MAX_OFFSET, self::MAX_OFFSET),
                ['offset' => $days, 'max' => self::MAX_OFFSET],
            );
        }

        return new self($days, $this->monthStarts, $this->extra, $this->removed, $this->useOfficial);
    }

    /**
     * Tell the calendar the real (moon-sighting) first day of a Hijri month. Every Islamic holiday of that month
     * is derived from it, in every year, official ones included. The Imam Reza holiday (last day of Safar) follows
     * the start of Rabi I when that is given, otherwise the table's length of Safar.
     *
     * @param  DateTimeInterface|string  $firstDayGregorian  a DateTimeInterface or a "Y-m-d" string (digits may be Persian or Arabic)
     *
     * @throws InvalidDateException when the year/month/date is invalid, the date is more than
     *                              {@see self::MAX_MONTH_START_DEVIATION} days from the computed start, or it
     *                              is not 29 or 30 days from the start of a neighbouring month already given
     */
    public function withHijriMonthStart(int $hijriYear, int $hijriMonth, DateTimeInterface|string $firstDayGregorian): self
    {
        if ($hijriYear < Hijri::MIN_YEAR || $hijriYear > Hijri::MAX_YEAR) {
            throw InvalidDateException::because(
                ErrorCode::DateOutOfRange,
                sprintf('Hijri year %d is outside the supported range %d..%d.', $hijriYear, Hijri::MIN_YEAR, Hijri::MAX_YEAR),
                ['year' => $hijriYear, 'min' => Hijri::MIN_YEAR, 'max' => Hijri::MAX_YEAR],
            );
        }
        if ($hijriMonth < 1 || $hijriMonth > 12) {
            throw InvalidDateException::because(
                ErrorCode::InvalidDate,
                sprintf('Hijri month %d is not between 1 and 12.', $hijriMonth),
                ['month' => $hijriMonth],
            );
        }

        $start = self::gregorianDayNumber($firstDayGregorian);
        [$gy, $gm, $gd] = Hijri::hijriToGregorian($hijriYear, $hijriMonth, 1);
        $computed = self::dayNumber($gy, $gm, $gd);

        if (abs($start - $computed) > self::MAX_MONTH_START_DEVIATION) {
            throw InvalidDateException::because(
                ErrorCode::InvalidDate,
                sprintf(
                    'Hijri month %d/%d cannot start on that date: it is %d days from the computed start %04d-%02d-%02d (at most %d allowed).',
                    $hijriYear,
                    $hijriMonth,
                    abs($start - $computed),
                    $gy,
                    $gm,
                    $gd,
                    self::MAX_MONTH_START_DEVIATION,
                ),
                ['hijriYear' => $hijriYear, 'hijriMonth' => $hijriMonth, 'computed' => sprintf('%04d-%02d-%02d', $gy, $gm, $gd)],
            );
        }

        // A lunar month is 29 or 30 days long: neighbouring starts that are already known must agree.
        $previous = $hijriMonth === 1 ? ($hijriYear - 1) * 100 + 12 : $hijriYear * 100 + $hijriMonth - 1;
        $next = $hijriMonth === 12 ? ($hijriYear + 1) * 100 + 1 : $hijriYear * 100 + $hijriMonth + 1;
        $gaps = [];
        if (isset($this->monthStarts[$previous])) {
            $gaps[] = $start - $this->monthStarts[$previous];
        }
        if (isset($this->monthStarts[$next])) {
            $gaps[] = $this->monthStarts[$next] - $start;
        }
        foreach ($gaps as $gap) {
            if ($gap < 29 || $gap > 30) {
                throw InvalidDateException::because(
                    ErrorCode::InvalidDate,
                    sprintf('Hijri month %d/%d: the given start is %d days from a neighbouring month start; a month has 29 or 30 days.', $hijriYear, $hijriMonth, $gap),
                    ['hijriYear' => $hijriYear, 'hijriMonth' => $hijriMonth, 'gap' => $gap],
                );
            }
        }

        $starts = $this->monthStarts;
        $starts[$hijriYear * 100 + $hijriMonth] = $start;

        return new self($this->offset, $starts, $this->extra, $this->removed, $this->useOfficial);
    }

    /**
     * Add a holiday on one Jalali day (in addition to whatever else falls there).
     *
     * @param  Jalali|string  $date  a Jalali, or a Jalali date string "YYYY/MM/DD" or "YYYY-MM-DD" (digits may be
     *                               Persian or Arabic). A text year of 1700 or later is not a Jalali year and is
     *                               rejected (elsewhere such a string is read as Gregorian); a time or free text is rejected too
     *
     * @throws InvalidDateException for an invalid date or an empty, control-character or over-long title
     */
    public function withHoliday(Jalali|string $date, string $title): self
    {
        $key = self::dayKey(self::toJalali($date));
        $title = self::cleanTitle($title);

        $extra = $this->extra;
        if (! in_array($title, $extra[$key] ?? [], true)) {
            $extra[$key][] = $title;
        }

        return new self($this->offset, $this->monthStarts, $extra, $this->removed, $this->useOfficial);
    }

    /**
     * Remove one title (or, without a title, every holiday) from one Jalali day, fixed ones included.
     * Holidays added with {@see self::withHoliday()} on that day are removed as well. The date is read as in
     * {@see self::withHoliday()}: a Jalali or a Jalali date string, never a Gregorian one.
     *
     * @throws InvalidDateException for an invalid date or title
     */
    public function withoutHoliday(Jalali|string $date, ?string $title = null): self
    {
        $key = self::dayKey(self::toJalali($date));
        $extra = $this->extra;
        $removed = $this->removed;

        if ($title === null) {
            $removed[$key] = true;
            unset($extra[$key]);
        } else {
            $title = self::cleanTitle($title);
            $current = $removed[$key] ?? [];
            if ($current !== true) {
                $list = $current;
                if (! in_array($title, $list, true)) {
                    $list[] = $title;
                }
                $removed[$key] = $list;
            }
            if (isset($extra[$key])) {
                $left = array_values(array_filter($extra[$key], static fn (string $t): bool => $t !== $title));
                if ($left === []) {
                    unset($extra[$key]);
                } else {
                    $extra[$key] = $left;
                }
            }
        }

        return new self($this->offset, $this->monthStarts, $extra, $removed, $this->useOfficial);
    }

    /**
     * Use (true, the default) or ignore (false) the published official table. Ignoring it makes every year
     * an estimate, which is what releases before official data did.
     */
    public function withOfficialData(bool $use): self
    {
        return new self($this->offset, $this->monthStarts, $this->extra, $this->removed, $use);
    }

    /* -----------------------------------------------------------------
     |  Reading
     | -----------------------------------------------------------------
     */

    /**
     * How the Islamic holiday dates of a Jalali year are known by this calendar (before your own overrides).
     * For {@see HolidaySource::Reported} years (1380-1393 and 1395) the dates come from one published list and
     * the set of holidays may be incomplete (titles such as Imam Reza or Imam Hasan Askari can be
     * missing); use {@see self::withHoliday()} to add one you know.
     *
     * @throws InvalidDateException when the year is outside Jalali::MIN_YEAR..MAX_YEAR
     */
    public function sourceOf(int $jalaliYear): HolidaySource
    {
        self::assertYear($jalaliYear);

        if (! $this->useOfficial) {
            return HolidaySource::Estimated;
        }

        return HolidayData::year($jalaliYear)['source'] ?? HolidaySource::Estimated;
    }

    public function isHoliday(Jalali|int $year, ?int $month = null, ?int $day = null): bool
    {
        return $this->entries(self::resolveDate($year, $month, $day)) !== [];
    }

    public function getTitle(Jalali|int $year, ?int $month = null, ?int $day = null): ?string
    {
        return $this->getTitles($year, $month, $day)[0] ?? null;
    }

    /**
     * All holiday titles of a day: fixed Jalali ones first, then Islamic ones, then your additions.
     *
     * @return list<string>
     */
    public function getTitles(Jalali|int $year, ?int $month = null, ?int $day = null): array
    {
        return array_map(
            static fn (HolidayEntry $e): string => $e->title,
            $this->entries(self::resolveDate($year, $month, $day)),
        );
    }

    /**
     * Every holiday of a day with the origin of its date: official data, a report, an estimate, a fixed
     * holiday or one of yours. A day in a reported year (see {@see self::sourceOf()}) may lack a holiday that
     * exists, so an empty answer there is not proof of a working day.
     *
     * @return list<HolidayEntry>
     */
    public function statusOf(Jalali|int $year, ?int $month = null, ?int $day = null): array
    {
        return $this->entries(self::resolveDate($year, $month, $day));
    }

    /**
     * The statutory fixed Jalali holidays of a year, keyed by "YYYY/MM/DD". Your own edits do not change this list.
     *
     * @return array<string, string>
     */
    public function allFixed(int $year): array
    {
        self::assertYear($year);

        $result = [];
        foreach (HolidayTitles::FIXED as $m => $days) {
            foreach ($days as $d => $title) {
                $result[sprintf('%04d/%02d/%02d', $year, $m, $d)] = $title;
            }
        }

        return $result;
    }

    /**
     * All holidays of a Jalali year; titles on the same day are joined with " / ". See allTitles().
     *
     * @return array<string, string>
     */
    public function all(int $year): array
    {
        return array_map(static fn (array $t): string => implode(' / ', $t), $this->allTitles($year));
    }

    /**
     * All holidays of a Jalali year keyed by Y/m/d, each a list of titles.
     *
     * @return array<string, list<string>>
     */
    public function allTitles(int $year): array
    {
        self::assertYear($year);

        $result = [];

        // Walk month by month so the last supported day never needs addDays(1) past MAX_YEAR.
        for ($month = 1; $month <= 12; $month++) {
            $last = Jalali::daysInMonth($year, $month);
            for ($day = 1; $day <= $last; $day++) {
                $current = Jalali::create($year, $month, $day);
                $titles = array_map(static fn (HolidayEntry $e): string => $e->title, $this->entries($current));
                if ($titles !== []) {
                    $result[$current->toDateString()] = $titles;
                }
            }
        }

        return $result;
    }

    /** Friday. */
    public function isWeekend(Jalali $date): bool
    {
        return (int) $date->toGregorian()->format('w') === 5;
    }

    public function isBusinessDay(Jalali $date): bool
    {
        return ! $this->isWeekend($date) && ! $this->isHoliday($date);
    }

    /**
     * The first business day (not Friday, not a holiday) after $date.
     *
     * @throws InvalidDateException when the search passes the last supported Jalali date (9377/12/30)
     */
    public function nextBusinessDay(Jalali $date): Jalali
    {
        $next = $date->addDays(1);
        while (! $this->isBusinessDay($next)) {
            $next = $next->addDays(1);
        }

        return $next;
    }

    /* -----------------------------------------------------------------
     |  Resolution
     | -----------------------------------------------------------------
     */

    /**
     * @return list<HolidayEntry>
     */
    private function entries(Jalali $date): array
    {
        $year = $date->getYear();
        $month = $date->getMonth();
        $day = $date->getDay();

        /** @var list<HolidayEntry> $entries */
        $entries = [];

        $fixed = HolidayTitles::FIXED[$month][$day] ?? null;
        if ($fixed !== null) {
            $entries[] = new HolidayEntry($fixed, HolidayOrigin::Fixed);
        }

        $gregorian = $date->toGregorian();

        $official = $this->useOfficial ? HolidayData::year($year) : null;
        if ($official !== null) {
            $origin = $official['source'] === HolidaySource::Official ? HolidayOrigin::Official : HolidayOrigin::Reported;
            $titles = $official['holidays'][$month.'/'.$day] ?? [];
            $hijri = $titles !== [] && $this->monthStarts !== [] ? self::hijriOrNull($gregorian) : null;
            foreach ($titles as $title) {
                if ($hijri === null || ! $this->isOverridden($title, $hijri)) {
                    $entries[] = new HolidayEntry($title, $origin);
                }
            }
        } else {
            $shifted = $this->offset === 0 ? $gregorian : $gregorian->modify(sprintf('%d days', -$this->offset));
            $hijri = self::hijriOrNull($shifted);
            if ($hijri !== null && Hijri::hasUmmAlQuraData($hijri->getYear())) {
                foreach (self::islamicTitles($hijri) as $title) {
                    if (! $this->isOverridden($title, $hijri)) {
                        $entries[] = new HolidayEntry($title, HolidayOrigin::Estimated);
                    }
                }
            }
        }

        if ($this->monthStarts !== []) {
            $dayNo = self::dayNumber((int) $gregorian->format('Y'), (int) $gregorian->format('n'), (int) $gregorian->format('j'));
            foreach ($this->monthStartTitles($dayNo) as $title) {
                $entries[] = new HolidayEntry($title, HolidayOrigin::User);
            }
        }

        $key = self::dayKey($date);

        $removed = $this->removed[$key] ?? null;
        if ($removed === true) {
            $entries = [];
        } elseif ($removed !== null) {
            $entries = array_values(array_filter($entries, static fn (HolidayEntry $e): bool => ! in_array($e->title, $removed, true)));
        }

        foreach ($this->extra[$key] ?? [] as $title) {
            $entries[] = new HolidayEntry($title, HolidayOrigin::User);
        }

        return self::unique($entries);
    }

    /**
     * @param  list<HolidayEntry>  $entries
     * @return list<HolidayEntry>
     */
    private static function unique(array $entries): array
    {
        $seen = [];
        $result = [];
        foreach ($entries as $entry) {
            if (! isset($seen[$entry->title])) {
                $seen[$entry->title] = true;
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Islamic titles for a Hijri day.
     *
     * @return list<string>
     */
    private static function islamicTitles(Hijri $hijri): array
    {
        $month = $hijri->getMonth();
        $day = $hijri->getDay();

        $titles = [];
        if (isset(HolidayTitles::ISLAMIC[$month][$day])) {
            $titles[] = HolidayTitles::ISLAMIC[$month][$day];
        }

        // Imam Reza martyrdom: last day of Safar (29 or 30 days).
        if ($month === 2 && $day === Hijri::daysInMonth($hijri->getYear(), 2)) {
            $titles[] = HolidayTitles::IMAM_REZA;
        }

        return $titles;
    }

    /**
     * Titles that fall on a day given the Hijri month starts supplied by the caller.
     *
     * @return list<string>
     */
    private function monthStartTitles(int $dayNo): array
    {
        $titles = [];

        foreach ($this->monthStarts as $key => $start) {
            $hijriYear = intdiv($key, 100);
            $hijriMonth = $key % 100;
            $dayOfMonth = $dayNo - $start + 1;

            if ($dayOfMonth >= 1 && $dayOfMonth <= 30 && isset(HolidayTitles::ISLAMIC[$hijriMonth][$dayOfMonth])) {
                $titles[] = HolidayTitles::ISLAMIC[$hijriMonth][$dayOfMonth];
            }

            // Last day of Safar: the day before Rabi I, or the table's length of Safar.
            if ($hijriMonth === 3 && $dayNo === $start - 1) {
                $titles[] = HolidayTitles::IMAM_REZA;
            } elseif ($hijriMonth === 2 && ! isset($this->monthStarts[$hijriYear * 100 + 3])
                && $dayNo === $start + Hijri::daysInMonth($hijriYear, 2) - 1) {
                $titles[] = HolidayTitles::IMAM_REZA;
            }
        }

        return $titles;
    }

    /**
     * Whether the caller gave the month start that decides this title's date, in which case the underlying
     * (official or estimated) date of the title is replaced.
     *
     * @param  Hijri  $near  the table-based Hijri date of the day being resolved
     */
    private function isOverridden(string $title, Hijri $near): bool
    {
        if ($this->monthStarts === []) {
            return false;
        }

        foreach (HolidayTitles::islamicMonths()[$title] ?? [] as $month) {
            // The title's month is the one nearest to the day: the same month or a neighbouring one.
            $step = (($month - $near->getMonth() + 18) % 12) - 6;
            $target = $near->getMonth() + $step;
            $year = $near->getYear() + ($target > 12 ? 1 : ($target < 1 ? -1 : 0));

            if (isset($this->monthStarts[$year * 100 + $month])) {
                return true;
            }
        }

        return false;
    }

    private static function hijriOrNull(DateTimeImmutable $gregorian): ?Hijri
    {
        try {
            return Hijri::make($gregorian);
        } catch (InvalidDateException) {
            return null; // before the Hijri epoch or past its last year
        }
    }

    /* -----------------------------------------------------------------
     |  Input handling
     | -----------------------------------------------------------------
     */

    private static function resolveDate(Jalali|int $year, ?int $month, ?int $day): Jalali
    {
        if ($year instanceof Jalali) {
            return $year;
        }
        if ($month === null || $day === null) {
            throw new InvalidDateException('Month and day are required when the year is given as int.');
        }

        return Jalali::create($year, $month, $day);
    }

    private static function toJalali(Jalali|string $date): Jalali
    {
        if ($date instanceof Jalali) {
            return $date;
        }

        self::assertLength($date);
        $normalised = CalendarLimits::normalize($date);

        // A holiday is a whole Jalali day: only Y/m/d or Y-m-d is read. Jalali::make() would take a year of
        // 1700 or later as Gregorian (and free text such as 'tomorrow'), which would silently move the holiday.
        if (preg_match('~^([0-9]{3,4})[/-]([0-9]{1,2})[/-]([0-9]{1,2})$~D', $normalised, $m) !== 1) {
            throw InvalidDateException::because(
                ErrorCode::InvalidDate,
                'Expected a Jalali date as YYYY/MM/DD (or YYYY-MM-DD) or a Jalali object.',
                ['value' => $date],
            );
        }
        if ((int) $m[1] >= self::GREGORIAN_YEAR_FROM) {
            throw InvalidDateException::because(
                ErrorCode::InvalidDate,
                sprintf('The year %d is not a Jalali year (a year of %d or later is read as Gregorian elsewhere); give a Jalali date such as 1405/02/03.', (int) $m[1], self::GREGORIAN_YEAR_FROM),
                ['value' => $date],
            );
        }

        return Jalali::create((int) $m[1], (int) $m[2], (int) $m[3], timezone: new DateTimeZone('UTC'));
    }

    private static function dayKey(Jalali $date): string
    {
        return sprintf('%04d/%02d/%02d', $date->getYear(), $date->getMonth(), $date->getDay());
    }

    private static function cleanTitle(string $title): string
    {
        $title = trim($title);
        $valid = preg_match('/^[^\p{Cc}\x{2028}\x{2029}]+$/uD', $title) === 1;

        if (! $valid || preg_match_all('/./us', $title) > self::MAX_TITLE_LENGTH) {
            throw InvalidDateException::because(
                ErrorCode::InvalidArgument,
                sprintf('A holiday title must be 1-%d characters of valid UTF-8 without control characters.', self::MAX_TITLE_LENGTH),
                ['max' => self::MAX_TITLE_LENGTH],
            );
        }

        return $title;
    }

    private static function assertLength(string $value): void
    {
        if (strlen($value) > self::MAX_INPUT_LENGTH) {
            throw InvalidDateException::because(
                ErrorCode::InputTooLong,
                sprintf('A date string may have at most %d bytes.', self::MAX_INPUT_LENGTH),
                ['limit' => self::MAX_INPUT_LENGTH],
            );
        }
    }

    /**
     * @throws InvalidDateException
     */
    private static function gregorianDayNumber(DateTimeInterface|string $date): int
    {
        if ($date instanceof DateTimeInterface) {
            $year = (int) $date->format('Y');
            if ($year < 1 || $year > 9999) {
                throw InvalidDateException::because(ErrorCode::DateOutOfRange, 'The date is outside the supported range.', ['year' => $year]);
            }

            return self::dayNumber($year, (int) $date->format('n'), (int) $date->format('j'));
        }

        self::assertLength($date);
        $normalised = CalendarLimits::normalize($date); // Persian/Arabic digits, outer spaces; a NUL byte or empty text throws

        if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $normalised, $m) !== 1
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw InvalidDateException::because(
                ErrorCode::InvalidDate,
                'Expected a Gregorian date as YYYY-MM-DD.',
                ['value' => $date],
            );
        }

        return self::dayNumber((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * Julian day number of a proleptic Gregorian date (year >= 1).
     */
    private static function dayNumber(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }

    /**
     * @throws InvalidDateException with ErrorCode::DateOutOfRange
     */
    private static function assertYear(int $year): void
    {
        if ($year < Jalali::MIN_YEAR || $year > Jalali::MAX_YEAR) {
            throw InvalidDateException::because(
                ErrorCode::DateOutOfRange,
                sprintf('Jalali year %d is outside the supported range %d..%d.', $year, Jalali::MIN_YEAR, Jalali::MAX_YEAR),
                ['year' => $year, 'min' => Jalali::MIN_YEAR, 'max' => Jalali::MAX_YEAR],
            );
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<array-key, mixed>
     */
    private static function arrayOption(array $config, string $name): array
    {
        $value = $config[$name] ?? [];
        if (! is_array($value)) {
            throw self::invalidOption($name, 'an array');
        }

        return $value;
    }

    private static function invalidOption(string $name, string $expected): InvalidDateException
    {
        return InvalidDateException::because(
            ErrorCode::InvalidArgument,
            sprintf('Holiday option "%s" must be %s.', $name, $expected),
            ['option' => $name],
        );
    }
}
