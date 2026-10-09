# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-10-09

### Changed

- Breaking for installs: the Composer package name is now `enaxon/rtly-kit`. Run `composer require enaxon/rtly-kit`. If you used an earlier version under another package name, require this one instead. The namespace is still `RtlyKit\`, so your PHP code does not change.

## [0.2.0] - 2026-10-08

### Added

- `HolidayCalendar` with official Iranian holiday dates for the Jalali years 1394 and 1396 to 1405, and published dates for 1380 to 1393 and 1395. Other years are estimated from the Umm al-Qura table. You can adjust a calendar with `withIslamicOffset()` (-3 to +3 days), `withHijriMonthStart()`, `withHoliday()`, `withoutHoliday()` and `withOfficialData()`. Your changes win over official data, and official data wins over the estimate. `sourceOf()` and `statusOf()` show where a date comes from. `IranHolidays::calendar()` returns the default calendar.
- Laravel config file `rtly-kit.php` (publish tag `rtly-kit-config`) with a `holidays` block. The service provider binds a `HolidayCalendar` built from it.
- Arabic number words options: `ArabicOptions` sets gender, grammatical case, counted-noun mode, vowel marks, the spelling of hundreds, the negative word and the name of 10^9. `convert($n, 'ar')` now handles integers below 10^27, and `NumberToWords::ordinal()` writes Arabic ordinals from 1 to 99. Without options, the output is unchanged for numbers below 10^9.
- Prayer times: `HighLatitudeRule` (`None`, `NightMiddle`, `OneSeventh`, `AngleBased`) with `PrayerTimes::withHighLatitudeRule()`, and `PrayerTimes::withTune()` for manual changes of -30 to +30 minutes.
- `Mobile::isAllocated()` and an `allocated` detail in `Mobile::validate()`, based on the national numbering plan filed with the ITU on 2026-08-24.
- `Hijri::ummAlQuraVerifiedRange()` and `Hijri::isUmmAlQuraVerified()` for the checked range, AH 1318 to 1500.
- An Arabic version of the guide, with new pages on the holiday calendar, Arabic number words and verifying releases.
- Releases include `SHA256SUMS` and a build provenance attestation that you can check with `gh attestation verify`.

### Changed

Some changes below can break existing code. For upgrade steps, see [UPGRADE.md](UPGRADE.md).

- `ext-mbstring` is no longer required. The only runtime requirement is `php: ^8.2`.
- Tested on PHP 8.2 to 8.5, Laravel 11, 12 and 13 (Laravel 13 needs PHP 8.3 or newer) and Carbon 3.
- In the Jalali years 1380 to 1405, `IranHolidays` and `is_iran_holiday()` use the official or reported dates instead of the Umm al-Qura estimate. For example, Eid al-Fitr 1447 AH is now on 1405/01/01, not 1404/12/29. Other years are unchanged. `HolidayCalendar::default()->withOfficialData(false)` gives the old result.
- `PrayerTimes` uses `HighLatitudeRule::AngleBased` by default. It acts only when a Fajr or Isha time is missing or too far from sunrise or sunset. Below about 44 degrees north nothing changes. Farther north, around the June solstice, some times differ from 0.1.x, and a time that was `null` can now have a value. `HighLatitudeRule::None` gives the old result. `nextPrayer()` now finds a prayer after midnight.
- `Mobile` operator prefixes are updated: Shatel Mobile is narrowed to 09981 and 09982, Aptel is widened to 9991, and 0923, 0931, 0932 and 0934 are added. Validity is unchanged.
- `Format::withSeparator()` and `format_number()` write a float as its shortest exact decimal, cut to 15 significant digits, with no exponent. For example, `-1234567.891` gives `-۱٬۲۳۴٬۵۶۷٫۸۹۱` and negative zero gives `۰`. Pass a string if you need more digits.
- Breaking: `NumberToWords::fromWords()` is strict. It rejects a missing or trailing `و` and parts in the wrong order, with error code `invalid_number_words`. Every text that `convert()` writes still parses.
- Breaking: `Slugify::make()` throws `RtlyKitException` when the text is not valid UTF-8.
- Breaking: `Hijri::format()` and `Hebrew::format()` reject patterns over 256 bytes with `InvalidDateException` (`input_too_long`), as `Jalali::format()` already did. A trailing backslash in a pattern is dropped in all three calendars.
- Breaking: a date outside the supported range now reports `date_out_of_range` in all three calendars. Some `Jalali` code paths used to report `invalid_date`. The exception type is the same.
- `IranHolidays::all()`, `allTitles()` and `allFixed()` throw `InvalidDateException` for a Jalali year outside -620 to 9377.
- Error messages that repeat your input cut it to 40 characters and stay valid UTF-8.

### Fixed

- `IranHolidays::all()` and `allTitles()` work for every supported Jalali year, -620 to 9377. Before, years before the Hijri epoch and the last day of 9377 threw `invalid_date`. Where Islamic holidays cannot be derived, only the fixed holidays are returned.
- On PHP 8.5, `Digits` no longer raises a warning when it converts `NAN` or `INF`.

## [0.1.1] - 2026-10-08

### Changed

- Documentation only, no code changes. Both READMEs are rewritten in plain language, and the guide is published at <https://ehsanenaloo.github.io/RTLY-Kit/>.

## [0.1.0] - 2026-10-08

First release.

### Added

- Calendars: `Jalali`, `Hijri` and `Hebrew`. They are immutable, written in plain PHP (no `ext-calendar`) and implement `RtlyKit\Contracts\CalendarDate`, so you can compare dates and take differences across calendars. They also implement `JsonSerializable`, so Eloquent `toJson()` with `JalaliCast` works.
- `Hijri` uses the Umm al-Qura month table for AH 1300 to 1500, with a tabular fallback (`HijriVariant::Tabular`). `Hebrew` supports leap years, ordinal months and `en`, `he` and `fa` names.
- Date arithmetic, `between()`, `diffIn*()`, `isPast()` and `isFuture()`, and `format()` with `date()` tokens and backslash escaping.
- Supported years: Jalali -620 to 9377, Hijri 1 to 9665, Hebrew 3762 to 13759. Input outside the range throws `InvalidDateException`.
- `make()` reads strings as follows: digits are normalised, a `Y/m/d` or `Y-m-d` year below 1700 is read as Jalali or Hijri, a Hebrew year of 3000 or more as Hebrew, and everything else as Gregorian. Blank strings throw, and `null` means now.
- Error handling: every exception extends `RtlyKitException` and implements `RtlyKitThrowable`, so one `catch` covers the library. `getErrorCode()` returns an `ErrorCode` case and `getContext()` returns details. Bad input does not surface as a raw `TypeError` or `ValueError`. See [error handling](https://ehsanenaloo.github.io/RTLY-Kit/en/error-handling.html) and [API stability](https://ehsanenaloo.github.io/RTLY-Kit/en/api-stability.html).
- Validators `NationalCode`, `Sheba`, `BankCard`, `Mobile`, `PostalCode` and `VehiclePlate` implement `RtlyKit\Contracts\Validator` and return a `Result` with `isValid()`, `errors()` and `details()`. Error codes are stable. Input that is not a string gives `invalid_type`, input over 4096 bytes gives `input_too_long`, and validators never throw. Helpers: `validate_national_code()`, `validate_sheba()`, `validate_bank_card()`, `validate_mobile()`, `validate_postal_code()` and `validate_vehicle_plate()`.
- `NationalCode::getLocation()` covers the 547 prefixes that three datasets agree on and returns `null` for other prefixes. `Mobile` accepts `0098`, `98` and bare `9xxxxxxxxx` forms, `Sheba` accepts the bare 24-digit form, and `BankCard` rejects a number made of one repeated digit.
- Numbers and text: `NumberToWords::convert()` and `fromWords()` for Persian (up to 21 digits, negative numbers) and Arabic (0 to 999,999,999, masculine counting form), `Format::ordinal()`, `Format::withSeparator()`, `to_persian()` and `to_english()`, `Normalizer` (including `fixHalfSpace()` and `clean()`), `Detector` and `Slugify`.
- `IranHolidays` with titles, weekend and business-day helpers. Islamic holidays come from the Hijri calendar for AH 1300 to 1500 and can differ from the official announcement by 1 to 2 days.
- `PrayerTimes` with the Tehran, MWL, ISNA, Egypt, Makkah and Karachi methods, an optional elevation, and `nextPrayer()`. The Makkah method sets Isha to Maghrib + 120 minutes in Ramadan and + 90 minutes otherwise.
- 27 helper functions in the `RtlyKit` namespace, for example `use function RtlyKit\jdate;`. `RtlyKit\Globals::register()` turns on short global names. It defines only names that are free, never overwrites a function, never throws and returns the names it skipped.
- Carbon macros `toJalali()`, `jformat()`, `createFromJalali()`, `toHijri()`, `toHebrew()`, `createFromHijri()` and `createFromHebrew()`, registered automatically for `Carbon` and `CarbonImmutable`.
- Laravel: package auto-discovery, the `Jalali` facade, the `JalaliCast` Eloquent cast, and the validation rules `national_code`, `sheba`, `bank_card`, `iran_mobile` (alias `mobile`), `postal_code` and `vehicle_plate`. Messages are in Persian, English and Arabic, and your own messages take priority.

### Changed

- Breaking for early builds: global helper functions are no longer loaded automatically, and the Laravel provider registers none. Use `use function RtlyKit\jdate;`, or call `RtlyKit\Globals::register()` once to get the short names back. See [UPGRADE.md](UPGRADE.md).

### Security

- Input size limits: `Format::withSeparator()` rejects strings over 4096 bytes and numbers over 1000 characters, and `NumberToWords::convert()` and `fromWords()` reject strings over 4096 bytes. They throw `InvalidNumberException`.

[Unreleased]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/ehsanenaloo/RTLY-Kit/releases/tag/v0.1.0
