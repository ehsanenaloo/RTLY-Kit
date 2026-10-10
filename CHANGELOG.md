# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-10-10

Some changes below can break existing code. They are marked `Breaking:`. For the steps to migrate, see [UPGRADE.md](UPGRADE.md).

### Added

- `make()` reads the common ISO-8601 forms as the calendar's own date when the year belongs to it: a space, `T` or `t` before the time, `H:i`, `H:i:s` or `H:i:s.u`, and an optional `Z`, `+03:30`, `+0330` or `+03` designator. A designator sets the time zone of the result, and a `$timezone` argument then converts to it. Offsets must lie within ±14:00.
- `make()` treats no-break space, ZWNJ, LRM and RLM as spaces. `Hebrew::make()` and `toDateString()` now round-trip the years 10000 to 13759.
- `islamic_offset` in the holiday config accepts an integer-like string such as `'1'`, as `env()` returns it.
- `VehiclePlate` accepts a bare `ا` (and `أ`, `إ`) as the letter and reports it as `الف`. `NumberToWords::ordinal()` accepts whole floats, and `convert($n, 'fa', [])` no longer throws. `Detector::containsRtl()` counts the explicit RLE, RLO and RLI controls.
- `Hijri::createFromFormat()` (with an optional variant) and `Hebrew::createFromFormat()`, with the same rules as `Jalali::createFromFormat()`.

### Changed

- Breaking: `make()` throws `InvalidDateException` for text that starts like a date of the calendar but is not one of the shapes above, for example a zone name after the time (`UTC`), `PM`, one-digit minutes or `1403-01`. It used to be read as a Gregorian date, which gave wrong results. Pass a `DateTimeImmutable` for such values.
- Breaking: a bare run of 3 to 8 digits (`1403`, `14030101`) throws instead of being read as a clock time. Eight digits that start with a year of another calendar (`20240101`) are still read as a Gregorian date. A 5-digit year in a Jalali or Hijri string throws `date_out_of_range`.
- Breaking: `endOfDay()`, `endOfMonth()` and `endOfYear()` end at 23:59:59.999999 instead of 23:59:59.000000. Formatted output with `H:i:s` is unchanged.
- Breaking: `Jalali::createFromFormat()` throws `InvalidDateException` for tokens with a Gregorian or time-zone meaning (`z y F M D l S e T P O p u v`). The format `U` alone reads a Unix timestamp.
- `Hijri::make()` takes `?HijriVariant $variant = null`. `null` keeps the variant of a Hijri instance and means Umm al-Qura for other input. A variant you pass, Umm al-Qura included, always applies. `Hijri::make()` and `Hebrew::make()` with an instance of their own class now honour the time zone. Before, they ignored it.
- The comparison methods and `between()` on `Jalali`, `Hijri` and `Hebrew` compare the whole instant, microseconds included. `diffInMonths()` and `diffInYears()` count microseconds too.
- `addMonths()` and `addYears()` keep the microseconds of the date, like `addDays()` and `addSeconds()` already did. `addMonths(0)` and `addYears(0)` return the same date, and month or year shifts keep the UTC offset during a repeated DST hour.
- Breaking: `make()` on `Jalali`, `Hijri` and `Hebrew`, and `JalaliCast`, throw `InvalidDateException` for a Gregorian string with an impossible day such as `2024-02-30`. Before, PHP rolled it over to the next month.
- Breaking: in `format()`, the token `Y` writes at least 4 digits and a leading minus for negative years (`-0005`; it used to print `-005`), and `y` uses the last two digits of the year with floor modulo (Jalali year -620 gives `80`; it used to give `-20`). A text date with a negative year now throws a clear `InvalidDateException` in `make()`.
- `serialize()` of `Jalali`, `Hijri` and `Hebrew` stores the moment (and the Hijri variant) as a small versioned array. `unserialize()` checks the range again and throws `InvalidDateException` for damaged data. Values serialized by earlier versions still load. Anyone who compares or stores the raw serialized string will see a different value.
- Breaking: `JalaliCast` throws `InvalidDateException` when the stored value is an array, a boolean, a float or an object. Before, it returned `null`. `null` and an empty string still give `null`.
- Breaking: `Mobile`, `PostalCode` and `BankCard` accept only digits plus spaces, no-break spaces, half-spaces, LRM/RLM marks, hyphens, parentheses and dots (and a leading `+98` for `Mobile`). Letters, other punctuation, negative numbers and control characters give `invalid_format`. Their `normalize()` returns an empty string for such input.
- Breaking: a tab, newline or NUL at either end of the input is no longer trimmed. `NationalCode`, `Sheba`, `VehiclePlate`, `Format::withSeparator()` and `NumberToWords` reject it.
- Breaking: `Sheba` rejects the check digits 00, 01 and 99 as `invalid_checksum`.
- Breaking: `PostalCode` rejects a code that has the digit 0 or 2 in its first five digits (for example `1234567890`) with `invalid_format`. The rule comes from three public sources; Iran Post publishes no official rule set.
- Breaking: number strings with improper grouping, such as `1 2`, `12 34` or `1,2`, throw `invalid_number` in `NumberToWords::convert()` and `Format::withSeparator()`. Before, they were read as 12 or 1234. Groups of exactly three digits after a first group of one to three digits (`1 234 567`, `1,234,567`) still work.
- Breaking: `Normalizer::normalize()`, `fixHalfSpace()` and `clean()` replace invalid UTF-8 bytes with U+FFFD and then apply every step. Before, they applied only the byte steps. `Detector` ignores invalid bytes instead of returning false or `ltr`.
- Breaking: `Detector::isRtlLocale()` follows an explicit script subtag (`fa-Latn`, `ar-Latn` and `sd-Deva` are false), and `Detector::direction()` treats all RTL scripts as `containsRtl()` does.
- Breaking: `NumberToWords` locales must name exactly `fa` or `ar`. `arn` and `farsi` used to be accepted by prefix and now throw `UnsupportedLocaleException`.
- Breaking: the Sheba bank names for codes 013 and 019 are `بانک رفاه کارگران` and `بانک صادرات ایران`, the same as in the BIN table.
- Breaking: `PrayerTimes` throws `InvalidPrayerConfigException` for NaN or infinite numbers, a latitude outside -90 to 90, a longitude outside -180 to 180 and an elevation beyond ±20000 m. `getTimes()` and `nextPrayer()` throw `InvalidDateException` when the local year is outside 1 to 9999. Before, they returned wrong times.
- Breaking: `PrayerTimes` reports a prayer as `null` when its local date would fall after 9999-12-31 or before 0001-01-01, and `nextPrayer()` returns `null` once no prayer is left up to the end of 9999. Near the poles, a time that would break the order Fajr, Sunrise, Dhuhr, Asr, Maghrib, Isha (for example Asr after Isha at 89.9 degrees) is `null` too.
- Breaking: for time zones whose offset includes seconds (mostly dates before 1970), prayer times are rounded to the nearest minute instead of cut, so a time can differ by one minute from before.
- Breaking: `HolidayCalendar::withHoliday()` and `withoutHoliday()` accept only a Jalali date string (`Y/m/d` or `Y-m-d`, a 3 or 4 digit year, Persian or Arabic digits allowed). A year of 1700 or later, a time part and free text throw `InvalidDateException`. Before, a Gregorian-looking string was read as another day. A Hijri month start that contains a NUL byte throws `InvalidDateException`.
- Breaking: a non-array `rtly-kit.holidays` Laravel config value throws `InvalidDateException` when the holiday calendar is resolved. Before, it was ignored.

### Fixed

- `JalaliCast` reads a stored Gregorian date with a year below 1700 as Gregorian. Before, such a value was read as a Jalali date.
- The Carbon macros no longer replace a macro that already has the same name. Only the missing ones are added.
- A NUL byte in a date string, a `createFromFormat()` argument or a time-zone name throws `InvalidDateException` instead of a `ValueError`. `diffInMonths()` and `diffInYears()` no longer throw for two valid dates near the year-range edge.
- A Laravel validation message that your application sets and that equals the English default is no longer replaced by the package translation, and float values reach the validation rules unchanged.
- `Normalizer::fixHalfSpace()` and `clean()` no longer damage text that ends in «ی» or «،», and `clean()` gives the same result when you run it again.
- `Detector::containsRtl()` and `isArabic()` no longer treat the byte order mark (U+FEFF) as a right-to-left character.
- `Slugify` no longer drops text that equals the separator or treats U+0001 as a separator. `NumberToWords::fromWords()` reports invalid UTF-8 clearly, and `Format::withSeparator('-0')` has no sign.
- `nextPrayer()` no longer skips a prayer for query times before 1970. Tehran-method Maghrib and Isha no longer come out identical at high latitudes with the `OneSeventh` and `NightMiddle` rules, so those times can change slightly. For time zones far from their longitude, such as Pacific/Kiritimati, prayer times now belong to the requested local date.

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

[Unreleased]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/ehsanenaloo/RTLY-Kit/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/ehsanenaloo/RTLY-Kit/releases/tag/v0.1.0
