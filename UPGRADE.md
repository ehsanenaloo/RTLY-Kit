# Upgrade guide

Before 1.0.0, a minor release may contain breaking changes. Each one is listed here with the steps to migrate. See [API stability](docs/en/api-stability.html) for the policy.

## From the pre-release helpers to the namespaced helpers (Unreleased)

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
