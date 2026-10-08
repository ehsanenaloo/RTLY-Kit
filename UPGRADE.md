# Upgrade guide

Before 1.0.0, a minor release may contain breaking changes. Each one is listed here with the steps to migrate. See [API stability](https://ehsanenaloo.github.io/RTLY-Kit/en/api-stability.html) for the policy.

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
