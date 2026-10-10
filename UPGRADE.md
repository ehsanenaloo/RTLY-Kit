# Upgrade guide

Before 1.0.0, a minor release may contain breaking changes. Each one is listed here with the steps to migrate. See [API stability](https://ehsanenaloo.github.io/RTLY-Kit/en/api-stability.html) for the policy.

## From 0.2.1 to 0.3.0

The changes below can break code that worked before. Most of them turn a guessed or wrong result into an exception or an invalid `Result`. Each item gives the old behaviour, the new behaviour and what to do.

### Dates from text

- **Text that only looks like a date.** `make()` on `Jalali`, `Hijri` and `Hebrew` reads the exact shapes `Y/m/d` and `Y-m-d` (with an optional time and zone designator) as a date of the calendar. Before, text that started like such a date but had more or less was handed to PHP and read as a Gregorian date, which gave wrong results. Now it throws `InvalidDateException`. Examples are `'1403-01-01 10:00 UTC'`, a time with `PM`, one-digit minutes and `'1403-01'`. What to do: pass a `DateTimeImmutable` for such values, or cut the text to one of the accepted shapes.
- **Bare digits.** Before, a run of 3 to 8 digits such as `'1403'` or `'14030101'` was read by PHP as a clock time. Now it throws `InvalidDateException`. Eight digits that start with a year of another calendar, such as `'20240101'`, are still read as a Gregorian date. A 5-digit year in a Jalali or Hijri string throws with the code `date_out_of_range`. What to do: use `create()` or a `DateTimeImmutable`.
- **Impossible Gregorian days.** `make()` on the three calendar classes, and `JalaliCast`, throw `InvalidDateException` for a Gregorian string with a day that does not exist. Text with a negative year such as `'-0100/01/01'` throws a clear `InvalidDateException` too. For negative years, use `create()` or a timestamp.

| Call | Before | After |
| --- | --- | --- |
| `Jalali::make('2024-02-30')` | the date of 2024-03-01 | `InvalidDateException` |

- **`createFromFormat()` tokens.** `Jalali::createFromFormat()` rejects the tokens `z y F M D l S e T P O p u v` with `InvalidDateException`, because they have a Gregorian or time-zone meaning and gave wrong dates. The format `U` alone reads a Unix timestamp. What to do: use the tokens `d j m n Y H G i s g h A a`, and pass the time zone as the third argument.
- **End of a period.** `endOfDay()`, `endOfMonth()` and `endOfYear()` end at 23:59:59.999999 and not at 23:59:59.000000. A format with `H:i:s` prints the same text. A strict comparison with a value built at `23:59:59` can now give another result. What to do: compare against the new end value, or use `startOfDay()` of the next day.
- **Year tokens in `format()`.** The token `Y` writes at least 4 digits, with a minus sign for negative years. The token `y` uses floor modulo.

| Call | Before | After |
| --- | --- | --- |
| `Jalali::create(-5, 1, 1)->format('Y')` | `-005` | `-0005` |
| `Jalali::create(-620, 1, 1)->format('y')` | `-20` | `80` |

### Eloquent cast

- **Unreadable stored values.** When the model reads the column, `JalaliCast` throws `InvalidDateException` if the stored value is an array, a boolean, a float or an object. Before, it returned `null`. `null` and an empty string still give `null`. What to do: fix the column type, or catch the exception where you read the attribute.

### Validators

- **Allowed characters.** `Mobile`, `PostalCode` and `BankCard` accept only digits plus spaces, no-break spaces, half-spaces (ZWNJ), LRM and RLM marks, hyphens, parentheses and dots. `Mobile` also accepts one `+` at the start of a `+98` number. Everything else gives `invalid_format`, and `normalize()` returns an empty string. Before, these classes kept only the digits and dropped the rest. What to do: clean the input on your side if you want to allow more.

| Input | Before | After |
| --- | --- | --- |
| `Mobile::validate('call 09121234567 now')` | valid | `invalid_format` |
| `Mobile::validate('-9121234567')` | valid | `invalid_format` |

- **Tab, newline and NUL at the ends.** These characters are no longer trimmed. `NationalCode`, `Sheba`, `VehiclePlate`, `Format::withSeparator()` and `NumberToWords` reject them. What to do: trim the input yourself when you want that.
- **Sheba check digits.** `Sheba` gives `invalid_checksum` for the check digits 00, 01 and 99, even when the modulo comes out right. The standard produces only 02 to 98.
- **Postal codes.** `PostalCode` gives `invalid_format` for a code with the digit 0 or 2 in its first five digits. The rule comes from three public sources. Iran Post publishes no official rule set. What to do: if you must accept codes that this rule rejects, check the shape yourself.

| Call | Before | After |
| --- | --- | --- |
| `PostalCode::isValid('1234567890')` | `true` | `false` |
| `PostalCode::isValid('1593715416')` | `true` | `true` |

- **Sheba bank names.** The names for the codes 013 and 019 are now `بانک رفاه کارگران` and `بانک صادرات ایران`, the same as in the BIN table. If you compare the names as text, update the comparison.

### Numbers and text

- **Grouping marks.** `NumberToWords::convert()` and `Format::withSeparator()` accept thousands separators only in proper grouping: a first group of 1 to 3 digits, then groups of exactly 3. What to do: remove stray spaces and commas from the input.

| Input | Before | After |
| --- | --- | --- |
| `'1 234 567'` | 1234567 | 1234567 |
| `'1,234,567'` | 1234567 | 1234567 |
| `'12 34'` | 1234 | `InvalidNumberException` (`invalid_number`) |
| `'1 2'` | 12 | `InvalidNumberException` (`invalid_number`) |

- **Locale names.** `NumberToWords` accepts a locale only when its language part is exactly `fa` or `ar`. `arn` and `farsi` were accepted by prefix. Now they throw `UnsupportedLocaleException`.
- **Invalid UTF-8.** `Normalizer::normalize()`, `fixHalfSpace()` and `clean()` replace bytes that are not valid UTF-8 with U+FFFD and then apply every step. Before, they applied only the byte steps to such text. `Detector` ignores invalid bytes and no longer returns `false` or `ltr` because of them.
- **Locale direction.** `Detector::isRtlLocale()` follows an explicit script subtag, so `fa-Latn`, `ar-Latn` and `sd-Deva` give `false`. `Detector::direction()` uses the same right-to-left scripts as `containsRtl()`.

### Prayer times

- **Input ranges.** `new PrayerTimes()` throws `InvalidPrayerConfigException` for `NaN`, infinity, a latitude outside -90 to 90, a longitude outside -180 to 180 and an elevation beyond 20000 m in either direction. `getTimes()` and `nextPrayer()` throw `InvalidDateException` when the local year is outside 1 to 9999. Before, they returned wrong times.
- **Missing times.** A prayer is `null` when its local date would fall after 9999-12-31 or before 0001-01-01. `nextPrayer()` returns `null` when no prayer is left up to the end of 9999. Near the poles, a time that would break the order fajr, sunrise, dhuhr, asr, maghrib, isha is `null` too. Asr after Isha at 89.9 degrees is an example. What to do: handle `null` in your output, as you already do for polar days.
- **Rounding.** For time zones whose offset includes seconds (mostly dates before 1970), times are rounded to the nearest minute and not cut. A time can differ by one minute from before.

### Holidays

- **Date strings.** `HolidayCalendar::withHoliday()` and `withoutHoliday()` accept only a Jalali date string: `Y/m/d` or `Y-m-d`, with a 3 or 4 digit year. Persian and Arabic digits are allowed. A year of 1700 or later, a time part and free text throw `InvalidDateException`. Before, a Gregorian-looking string was read as another day. A Hijri month start that contains a NUL byte throws `InvalidDateException`.

| Call | Before | After |
| --- | --- | --- |
| `withHoliday('2027/05/05', 'x')` | another day than intended | `InvalidDateException` |
| `withHoliday('1406/02/03', 'x')` | works | works |

- **Laravel config.** A `rtly-kit.holidays` value that is not an array throws `InvalidDateException` when the holiday calendar is resolved. Before, it was ignored. What to do: set the entry to an array, or remove it.

### Also check

These changes are not breaking for most code, but they can show up in tests or stored values.

- `serialize()` of `Jalali`, `Hijri` and `Hebrew` stores the moment (and the Hijri variant) as a small versioned array. Values serialized by earlier versions still load. If you compare or store the raw serialized string, you will see a different value.
- `Hijri::make()` and `Hebrew::make()` with an instance of their own class now honour the time zone argument. Before, they ignored it. `Hijri::make()` takes `?HijriVariant $variant = null`. The value `null` keeps the variant of a Hijri instance, and a variant you pass always applies.
- Comparison methods and `between()` use the whole instant, microseconds included. `addMonths()` and `addYears()` keep the microseconds.
- For the Tehran method with the `OneSeventh` and `NightMiddle` rules at high latitudes, Maghrib and Isha are no longer identical, so those times can change slightly. For zones far from their longitude, such as `Pacific/Kiritimati`, prayer times now belong to the requested local date.

## From 0.1.x to 0.2.0

Most applications need no change. Check the items below.

1. **`ext-mbstring` is no longer required.** `composer.json` used to need the extension; now it needs only PHP. Nothing to do. If you added `ext-mbstring` to your own `composer.json` only for this package, you can remove it.
2. **Float formatting.** `Format::withSeparator()` and `format_number()` now print a float as its shortest decimal that parses back to the same float, limited to 15 significant digits, so binary noise is gone. Strings are unchanged and stay exact: pass a string when you need more digits.

   | Call | Before | After |
   |---|---|---|
   | `withSeparator(-1234567.891)` | `-۱٬۲۳۴٬۵۶۷٫۸۹۱۰۰۰۰۰۰۰۶۱۴۶۷` | `-۱٬۲۳۴٬۵۶۷٫۸۹۱` |
   | `withSeparator(0.1 + 0.2)` | `۰٫۳` | `۰٫۳` (unchanged) |
   | `withSeparator(-0.0)` | `۰` | `۰` |

   If you format the result of float arithmetic, round first (`round($x, 2)`) or pass a string.
3. **`NumberToWords::fromWords()` is strict.** It used to add the words up in any order.

   | Input | Before | After |
   |---|---|---|
   | `دو صد` | `102` | `InvalidNumberException` (`invalid_number_words`) |
   | `بیست یک` | `21` | `InvalidNumberException` |
   | `صد و بیست و` | `120` | `InvalidNumberException` |
   | `پنج و بیست` | `25` | `InvalidNumberException` |
   | `سی و پنج` | `35` | `35` |

   Write `و` between parts and keep the order hundreds, tens, units. Everything `convert()` returns still parses.
4. **`Slugify::make()`** throws `RtlyKitException` (`invalid_argument`, `argument` = `text`) for text that is not valid UTF-8. Before, only the separator was checked. Catch `RtlyKitThrowable` or clean the input first.
5. **`Hijri::format()` and `Hebrew::format()`** reject a pattern longer than 256 bytes with `InvalidDateException` (`input_too_long`, `argument` = `format`, `limit` = 256). `Jalali::format()` already did. The limit is in `Hijri::MAX_FORMAT_LENGTH` and `Hebrew::MAX_FORMAT_LENGTH`.
6. **Trailing backslash in a format pattern** is dropped in all three calendars: `format('Y\')` returns just the year.
7. **`IranHolidays::all()`, `allTitles()` and `allFixed()`** throw `InvalidDateException` (`date_out_of_range`, context `year`, `min`, `max`) for a Jalali year outside -620..9377.
8. **Error messages** that repeat your input cut it to 40 characters and stay valid UTF-8. If you match on message text, match on `getErrorCode()` instead.
9. **Official holiday dates for 1380 to 1405.** In these Jalali years `IranHolidays` and `is_iran_holiday()` return the published dates instead of the Umm al-Qura estimate. Other years are unchanged.

   | Day | Before (estimate) | After (official) |
   |---|---|---|
   | 1404/01/10 | عید فطر | no holiday |
   | 1404/01/11 | تعطیل عید فطر | عید فطر |
   | 1404/01/01 | جشن نوروز + شهادت امام علی | جشن نوروز |
   | 1404/12/29 | ملی شدن صنعت نفت + عید فطر | ملی شدن صنعت نفت |
   | 1405/01/01 | جشن نوروز + تعطیل عید فطر | جشن نوروز + عید فطر |

   In official years only the titles in the official table appear. Years 1380 to 1393 and 1395 are "reported": the dates come from one source, and the list of days may be incomplete. For the old behaviour use `HolidayCalendar::default()->withOfficialData(false)`. `IranHolidays::sourceOf($year)` shows where a year comes from. See the [holiday calendar guide](https://ehsanenaloo.github.io/RTLY-Kit/en/holiday-calendar.html).
10. **High-latitude prayer times.** `PrayerTimes` has a new `HighLatitudeRule`, and the default is `AngleBased`. It acts only where a Fajr or Isha time would be missing, or farther from sunrise or sunset than the rule allows. Below about 44 degrees north nothing changes. North of about 44 to 46 degrees, around the June solstice, some values differ, and a time that was `null` now has a value.

    | Place and day (MWL) | Before (same as `None`) | After (default) |
    |---|---|---|
    | Stockholm, 2026-06-21 | Fajr `null`, Isha `null` | Fajr `01:54`, Isha `23:40` |
    | Munich, 2026-06-21 | Fajr `01:50`, Isha `00:12` | Fajr `02:51`, Isha `23:32` |

    For the old output call `->withHighLatitudeRule(HighLatitudeRule::None)`. `nextPrayer()` now also finds a prayer that falls after midnight.
11. **Mobile.** Validity is unchanged. Every result has a new `allocated` detail, and `Mobile::isAllocated()` returns it. The operator table follows the numbering plan more closely: the Shatel Mobile key `998` is narrowed to `09981` and `09982`, the Aptel key `99910` is widened to `9991`, and `0923`, `0931`, `0932` and `0934` are added.

    | Number | Operator before | Operator after |
    |---|---|---|
    | `09983112345` | شاتل موبایل | `null` |
    | `09231234567` | `null` | رایتل |
    | `09321234567` | `null` | تالیا |

    If you compare the whole `details()` array, expect the extra key `allocated`.
12. **Arabic number words.** `convert($n, 'ar')` without options gives the same text as before for every number below 10^9. It now also accepts numbers up to 10^27. New options and the ordinals from 1 to 99 are described in the [Arabic number words guide](https://ehsanenaloo.github.io/RTLY-Kit/en/arabic-number-words.html).

## From the early pre-release helpers to the namespaced helpers

### 1. Global helper functions are no longer defined by default

Earlier pre-release builds defined `jdate()`, `to_persian()`, `is_national_code()` and the other helpers as global functions the moment Composer autoloaded the package. That could collide with your code or with other packages, so it is gone. The helpers now live in the `RtlyKit` namespace and nothing is defined globally.

The 27 helpers: `jdate`, `hdate`, `hebrew_date`, `to_persian_digits`, `to_english_digits`, `to_persian`, `to_english`, `is_national_code`, `is_sheba`, `is_bank_card`, `is_mobile`, `is_postal_code`, `is_vehicle_plate`, `validate_national_code`, `validate_sheba`, `validate_bank_card`, `validate_mobile`, `validate_postal_code`, `validate_vehicle_plate`, `number_to_words`, `normalize_text`, `contains_rtl`, `text_direction`, `is_iran_holiday`, `prayer_times`, `format_number`, `ordinal`.

**Option A: import what you use (recommended).**

```php
use function RtlyKit\jdate;
use function RtlyKit\is_national_code;

echo jdate('2026-03-21')->format('Y/m/d');
```

Or call the fully qualified name: `\RtlyKit\jdate('2026-03-21')`.

**Option B: restore the old short global names.** Call `Globals::register()` once, for example in your bootstrap file:

```php
\RtlyKit\Globals::register();

echo jdate('2026-03-21')->format('Y/m/d');   // works as before
```

`register()` defines only the names that are still free. It never overwrites an existing function and never throws. It returns the list of names it skipped because something else already uses them, and it is safe to call more than once:

```php
$skipped = \RtlyKit\Globals::register();
if ($skipped !== []) {
    error_log('RTLY-Kit globals skipped: '.implode(', ', $skipped));   // use \RtlyKit\name() for those
}
```

The Laravel service provider does not register globals either. In Blade, use `{{ \RtlyKit\jdate($post->created_at)->format('j F Y') }}`, or call `Globals::register()` from your `AppServiceProvider::register()`.

### 2. Other changes to be aware of

- **Validators take any value.** All six now implement `RtlyKit\Contracts\Validator` (`validate(mixed): Result`, `isValid(mixed): bool`). Non-string input (null, arrays, objects) returns an invalid `Result` with error `invalid_type`; input over 4096 bytes returns `input_too_long`. Validators never throw for bad input.
- **Exceptions carry codes.** `getErrorCode()` (an `ErrorCode` enum) and `getContext()` exist on every library exception. Nothing to change; prefer matching on the code over message text.
- **`Slugify::make()` throws** `RtlyKitException` for an invalid UTF-8 separator or one longer than 64 bytes.
- **Calendars share a contract.** `Jalali`, `Hijri` and `Hebrew` implement `RtlyKit\Contracts\CalendarDate`. Comparison and `diffIn*()` methods accept a `CalendarDate` or any `DateTimeInterface`, `make()` accepts a `CalendarDate`, and `equals()`, `isBefore()` and `isAfter()` are new aliases. Existing calls keep working.
- **Reference data moved** to `resources/data/*.php`. If you read the old table classes directly (they were never public API), switch to the public classes such as `Hijri`, `Sheba::getBankName()` or `NationalCode::getLocation()`.
- **Makkah Isha in Ramadan changed.** `PrayerTimes::METHOD_MAKKAH` now returns Isha as Maghrib + 120 minutes during Ramadan (Umm al-Qura practice) and + 90 minutes otherwise. The previous build always used 90, so Ramadan Isha moves 30 minutes later. This is a bug fix; update any stored expected values.
