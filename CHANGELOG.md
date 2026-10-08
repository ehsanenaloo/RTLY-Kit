# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-10-08

### Added
- `Jalali`, `Hijri` and `Hebrew` implement `JsonSerializable` (the full `Y/m/d H:i:s` text), so Eloquent `toJson()` with `JalaliCast` no longer produces `{}`.
- Range errors now carry `ErrorCode::DateOutOfRange` (year, timestamp and shift overflow); other invalid dates keep `ErrorCode::InvalidDate`.

- `RtlyKit\Globals::register(): list<string>`: opt-in short global names. Defines only names that are free, never overwrites, never throws, is idempotent and returns the names it skipped. `Globals::NAMES` lists them.
- `RtlyKit\Contracts\CalendarDate`, implemented by `Jalali`, `Hijri` and `Hebrew`. Comparisons and `diffIn*()` accept `CalendarDate|DateTimeInterface` (cross-calendar), `make()` accepts a `CalendarDate`, and `equals()`, `isBefore()`, `isAfter()` are new aliases.
- `RtlyKit\Contracts\Validator` (static `validate(mixed): Result`, `isValid(mixed): bool`), implemented by all six validators. Non-string input returns an invalid `Result` with `invalid_type`, input over 4096 bytes returns `input_too_long`; validators never throw.
- `RtlyKit\Exceptions\ErrorCode` backed string enum. Every library exception has `getErrorCode()` and `getContext()`, and `RtlyKitException::because()` builds one with a specific code.
- Documentation: [error handling](docs/en/error-handling.html), [API stability](docs/en/api-stability.html) and [UPGRADE.md](UPGRADE.md).

- Pure-PHP Hebrew calendar (`Hebrew`): leap years, ordinal months, `en` / `he` / `fa` month and weekday names, no `ext-calendar` needed.
- Hijri calendar rewritten around the Umm al-Qura month table for AH 1300-1500 (generated from ICU/CLDR data), with a `HijriVariant::Tabular` arithmetic fallback, month/year arithmetic and `ar` / `fa` / `en` formatting with selectable digits.
- Structured validation: `RtlyKit\Validation\Result` (`isValid()`, `errors()`, `details()`) and `validate()` on `NationalCode`, `Sheba`, `BankCard`, `Mobile`, `VehiclePlate` and `PostalCode`, with stable error codes (`PostalCode`: `invalid_length`, `invalid_format`). New helpers `validate_national_code`, `validate_sheba`, `validate_bank_card`, `validate_mobile`, `validate_postal_code`, `validate_vehicle_plate`.
- `NumberToWords::fromWords()` (words to number), numbers up to 21 digits, negative numbers.
- Arabic number words: `NumberToWords::convert($n, 'ar')` and `number_to_words($n, $locale = 'fa')`. Supports 0 to 999,999,999 and negatives (prefix `سالب`) in the masculine counting form only, without case endings or gender agreement; larger values throw `InvalidNumberException`, other locales throw `InvalidArgumentException`.
- `Hijri` and `Hebrew` now have `between()`, `isPast()`, `isFuture()` and `diffInDays()` / `diffInMonths()` / `diffInYears()` (`$other`, `$absolute = true`), matching `Jalali`; `Hebrew` also has `isToday()`.
- `Normalizer::fixHalfSpace()` and `Normalizer::clean()`; `Detector::isRtlLocale()`, `isHebrew()` and Unicode-based `direction()`.
- `IranHolidays::getTitles()`, `allTitles()`, `isWeekend()`, `isBusinessDay()`, `nextBusinessDay()`.
- `PrayerTimes::nextPrayer()` with rollover to tomorrow's fajr; unreachable high-latitude times return `null`.
- Laravel integration: container-resolved `JalaliFactory` behind the `Jalali` facade, composer `extra.laravel` auto-discovery, and the `national_code`, `sheba`, `bank_card`, `iran_mobile` (alias `mobile`), `postal_code` and `vehicle_plate` rules, covered by integration tests against real Illuminate components.
- Carbon macros `toJalali()`, `jformat()` and `createFromJalali()` for `Carbon` and `CarbonImmutable`, registered automatically; time zones accepted as object or string.
- Carbon macros `toHijri(?HijriVariant)`, `toHebrew()`, `createFromHijri()` and `createFromHebrew()`.
- Laravel validation messages in Persian, English and Arabic (`resources/lang`), loaded automatically under the `rtly-kit` namespace and chosen by `app()->getLocale()`. Messages in your own `lang/{locale}/validation.php`, inline custom messages and `lang/vendor/rtly-kit` overrides still win.
- `RtlyKit\Laravel\Casts\JalaliCast`: an Eloquent cast that stores a Gregorian datetime and exposes an immutable `Jalali`; accepts `Jalali`, `DateTimeInterface`, Gregorian strings, Unix timestamps and Jalali strings.
- Helpers `to_persian()` and `to_english()`.
- Documentation: new English README, Persian README, guides, `CONTRIBUTING.md`, `LICENSE` and this changelog.
- Docker development environment and a CI workflow (PHP 8.2, 8.3, 8.4: PHPStan and PHPUnit).
- `Jalali`: `getDayOfWeek()`, `monthName()`, `subHours()` / `subMinutes()` / `subSeconds()`, and the `format()` tokens `c`, `r`, `W` and `S`. `Jalali::daysInYear()` is documented.
- `Hijri` and `Hebrew`: `today()`, `addHours()` / `addMinutes()` / `addSeconds()` and their `sub*()` twins, and the extra `format()` tokens `M N z a A g h S W c r`. `Hijri::usesUmmAlQuraTable()` tells whether an instance is backed by the real table or by tabular extrapolation.
- Exception hierarchy: `RtlyKit\Exceptions\RtlyKitException` (extends `\InvalidArgumentException`) implements the new `RtlyKitThrowable` marker interface, and `InvalidDateException`, `InvalidNumberException`, `InvalidPrayerConfigException` and `UnsupportedLocaleException` all extend it, so one `catch` covers the library. New "Error handling" section in the getting-started guides.
- `SECURITY.md`, `DATA-SOURCES.md` and `NOTICE`, linked from the READMEs. Composer now suggests `illuminate/support` and `nesbot/carbon`.
- 527 PHPUnit tests (unit and Laravel/Eloquent integration), PHPStan at level max.

### Changed

- **BREAKING: global helper functions are no longer autoloaded.** All 27 helpers are now namespaced functions (`use function RtlyKit\jdate;`, or `\RtlyKit\jdate()`), and the package defines no global function by default. To get the short names back, call `RtlyKit\Globals::register()` once. The Laravel provider registers no globals either. See [UPGRADE.md](UPGRADE.md).
- Reference data tables moved to `resources/data/*.php` (internal; not public API).
- `Slugify::make()` throws `RtlyKitException` (`input_too_long` / `invalid_argument`) for an invalid UTF-8 separator or one over 64 bytes.
- `PrayerTimes` core rewritten clean-room from standard astronomical equations (Meeus/NOAA); each event uses the sun position at its own time, DST is applied per event, optional `$elevation` constructor argument, Asr is `null` during polar night. Differences from the previous implementation across 14 cities × 6 methods × 2 Asr factors × 365 days: at most 2 minutes (evening events), mean ≤ 0.5 min. Sunrise/sunset cross-checked against the NOAA Solar Calculator.
- `Jalali` conversion internals rewritten as an independent implementation of the arithmetic 33-year rule; verified identical to the previous implementation on every supported day.
- Benchmarks: see the [benchmarks guide](docs/en/benchmarks.html).

- `Jalali::format()` supports the full set of `date()` tokens plus backslash escaping; `createFromFormat()` and `create()` validate time fields.
- `Format::ordinal()` handles 30 (`سی‌ام`) and 23 (`بیست و سوم`) correctly; `Format::withSeparator()` accepts Persian/Arabic digits and separators without rounding.
- `Slugify` keeps hyphens and underscores and collapses repeats; `Detector::isPersian()` recognises the full set of Persian-specific letters.
- `Mobile` normalises `0098`, `98` and bare `9xxxxxxxxx` forms; `Sheba` accepts the bare 24-digit form.
- Islamic holidays in `IranHolidays` are derived from the Hijri calendar; the documentation states that they can differ from the official announcement by 1-2 days.

- Supported year ranges are now explicit constants: `Jalali::MIN_YEAR` / `MAX_YEAR` = -620 / 9377, `Hijri` = 1 / 9665, `Hebrew` = 3762 / 13759 (all inside Gregorian years 1-9999). Out-of-range or overflowing input (years, timestamps, huge `add*()` / `sub*()` deltas) throws `InvalidDateException` instead of a `TypeError` or a wrapped-around date. `Hebrew::addMonths()` and `addYears()` run in constant time.
- `make()` string semantics are specified: digits are normalised; `Y/m/d` or `Y-m-d` with a year below 1700 is read as Jalali / Hijri, a `Hebrew` year of 3000 or more as Hebrew, everything else as Gregorian; blank strings throw; `null` is now. A Gregorian ISO string with a year below 1700 is therefore read as Jalali or Hijri: pass a `DateTimeImmutable` for such dates.
- `IranHolidays` reports Islamic holidays only for AH 1300-1500 (the Umm al-Qura table); outside it only the fixed Jalali holidays are returned, and a year outside the Jalali range throws `InvalidDateException`.
- `NumberToWords::convert()` and `Format::ordinal()` accept integral floats (`3.0`) and throw `InvalidNumberException` for fractional floats, `NaN` and `INF`.
- Documentation: `RTLY-Kit` repository URLs and the CI badge point to <https://github.com/ehsanenaloo/RTLY-Kit>.

### Security

- Input caps against resource exhaustion: `Format::withSeparator()` rejects string input over 4096 bytes and numbers over 1000 characters, `NumberToWords::convert()` and `fromWords()` reject strings over 4096 bytes. All throw `InvalidNumberException`.
- Every calendar entry point now throws only `RtlyKitException` subclasses for bad input, so untrusted dates cannot surface as a `TypeError` or `ValueError`.

### Fixed
- `Jalali::make()`, `Hijri::make()`, `Hebrew::make()` and `createFromFormat()` now throw `InvalidDateException` for strings that contain only a time-zone token (for example `x`, `UTC`) instead of silently returning the current time.

- `PrayerTimes::METHOD_MAKKAH`: Isha is now Maghrib + 120 minutes during Ramadan (Umm al-Qura practice) and Maghrib + 90 minutes otherwise. It was always + 90, so Ramadan Isha was 30 minutes early. Verified against the Umm Al-Qura Calendar (see `docs/en/prayer-times.html`).
- `BankCard` rejects numbers made of one repeated digit.
- Duplicate `hdate()` helper declaration removed.
- `NationalCode` no longer ships an unverifiable city table: `getLocation()` now covers only the 547 prefixes that three community datasets agree on, and returns `null` for everything else.
- Bank card BIN (39), Sheba bank-code (38) and mobile operator tables are limited to entries confirmed by at least two sources.
- `PrayerTimes` timezone handling around DST and unknown method / Asr factor errors.

### Known limitations

- Prayer-time method angles for Karachi, MWL and ISNA were not verified against an official timetable; the Tehran method's Isha (14 degrees) and Asr are not covered by the reference tables used for verification.
- Umm al-Qura data has not been verified month by month against KACST.
- Iranian Islamic holidays may differ by 1-2 days from moon-sighting announcements.
- National code province/city lookup covers 547 prefixes confirmed by three community datasets (shared ancestry); others return `null`. The prefix is the place of issuance.
- Bank card BIN (39), Sheba bank-code (38) and mobile operator tables contain only entries confirmed by at least two sources and are incomplete.
- Arabic number words are limited to numbers below 10^9, use the masculine counting form only, and have no case endings, tanwin or gender agreement.
