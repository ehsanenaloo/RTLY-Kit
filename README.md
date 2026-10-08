<div align="center">

**English** | [فارسی](.github/README.fa.md)

<a href="docs/index.html"><img src="docs/assets/logo.svg" width="112" height="112" alt="RTLY-Kit logo"></a>

# RTLY-Kit

**Dates, numbers and checks for Persian and Arabic PHP apps.**

Jalali, Hijri and Hebrew calendars. Iranian validators. Number words, holidays and prayer times.
One small package. No required dependencies.

[![CI](https://github.com/ehsanenaloo/RTLY-Kit/actions/workflows/ci.yml/badge.svg)](https://github.com/ehsanenaloo/RTLY-Kit/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012-ff2d20.svg)](docs/en/laravel-setup.html)
[![Dependencies](https://img.shields.io/badge/dependencies-none-2ea44f.svg)](#requirements)
[![License: MIT](https://img.shields.io/badge/license-MIT-yellow.svg)](LICENSE)

<img src="docs/assets/social-preview.svg" width="720" alt="RTLY-Kit: the Persian and Arabic RTL toolkit for PHP. composer require enaxon/rtly-kit">

</div>

## Why this exists

Persian and Arabic PHP tools are spread over many packages. One handles Jalali dates. Another checks national codes. A third turns numbers into words. Most of them need Carbon or a whole framework.

RTLY-Kit puts the common pieces in one place. It is plain PHP. You install it, import a function and use it. There is no config file and no setup step.

## Quick start

```bash
composer require enaxon/rtly-kit
```

```php
require 'vendor/autoload.php';

use function RtlyKit\{jdate, to_persian, number_to_words, is_national_code};

echo jdate('2026-03-21')->format('l j F Y');   // شنبه 1 فروردین 1405
echo to_persian(1405);                         // ۱۴۰۵
echo number_to_words(1234);                    // یک هزار و دویست و سی و چهار
var_dump(is_national_code('0499370899'));      // bool(true)
```

The helpers are namespaced functions. They cannot clash with your own code. If you like short global names, you can [turn them on](#helpers-without-clashes).

## What you can do

### Work with three calendars

`Jalali`, `Hijri` and `Hebrew` are immutable objects. They share one contract, so you can compare dates from different calendars.

```php
use RtlyKit\Calendar\Jalali;
use function RtlyKit\{jdate, hdate, hebrew_date};

$date = jdate('2026-03-21');
echo $date->addMonths(1)->format('Y/m/d');                       // 1405/02/01
echo Jalali::create(1405, 1, 1)->toGregorian()->format('Y-m-d'); // 2026-03-21

echo hdate('2026-03-21')->format('j F Y', 'en');            // 2 Shawwal 1447
echo hebrew_date('2026-03-21')->format('j F Y', 'en');      // 3 Nisan 5786
```

Hijri uses the Umm al-Qura table by default, with an arithmetic option. All three are pure PHP. You do not need `ext-calendar`. Read more in the [Jalali guide](docs/en/jalali.html) and the [compare guide](docs/en/convert-and-compare.html).

### Check Iranian data

National code, Sheba, bank card, mobile number, postal code and vehicle plate. Each one gives you a clear result, not just true or false.

```php
use function RtlyKit\{validate_sheba, validate_national_code};

$result = validate_sheba('IR27 0170 0000 0010 0324 2000 01');
$result->isValid();   // true
$result->details();   // ['normalized' => 'IR270170000000100324200001', 'bank_code' => '017', 'bank_name' => 'بانک ملی ایران']

$bad = validate_national_code('0499370898');
$bad->errors();       // ['invalid_checksum']
```

Error codes are stable strings, like `invalid_format` and `invalid_checksum`. You turn them into your own messages. Validators never throw. Odd input, such as `null` or an array, gives `invalid_type`.

### Write numbers and clean text

```php
use function RtlyKit\{format_number, ordinal, normalize_text, text_direction, number_to_words};

echo format_number(1234567.5);          // ۱٬۲۳۴٬۵۶۷٫۵
echo ordinal(30);                       // سی‌ام
echo normalize_text('كتاب ٣ يك');        // کتاب ۳ یک
echo text_direction('سلام');            // rtl
echo number_to_words(1234, 'ar');       // ألف ومئتان وأربعة وثلاثون
```

### Find holidays and prayer times

```php
use RtlyKit\Prayer\PrayerTimes;
use function RtlyKit\is_iran_holiday;

var_dump(is_iran_holiday(1405, 1, 1));   // true

$times = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)
    ->getTimes(new DateTimeImmutable('2026-06-01'));
echo $times['fajr'];                     // 04:11
```

Six methods are built in: Tehran, MWL, ISNA, Egypt, Makkah and Karachi. See [how well each one is checked](#how-accurate-is-it).

### Use it with Carbon and Laravel

Carbon macros turn on by themselves when Carbon is installed.

```php
use Carbon\Carbon;

echo Carbon::parse('2026-03-21')->toJalali()->format('Y/m/d');   // 1405/01/01
echo Carbon::createFromJalali(1405, 1, 1)->toDateString();       // 2026-03-21
echo Carbon::parse('2026-03-21')->toHijri()->format('Y/m/d');    // 1447/10/02
```

Laravel finds the package on its own. You get six validation rules with Persian, English and Arabic messages, a `Jalali` facade and an Eloquent cast.

```php
$request->validate([
    'national_code' => ['required', 'national_code'],
    'sheba'         => ['required', 'sheba'],
    'phone'         => ['required', 'iran_mobile'],
]);

protected $casts = ['published_at' => \RtlyKit\Laravel\Casts\JalaliCast::class];
```

More in the [Laravel guide](docs/en/laravel-setup.html).

## Helpers without clashes

The 27 helpers live in the `RtlyKit` namespace. Import what you need with `use function`. Nothing is added to the global scope.

Want short names like `jdate()`? Switch them on once, for example in your bootstrap file:

```php
$skipped = \RtlyKit\Globals::register();   // names that were already taken
```

It only adds names that are free. It never replaces your functions and never throws. You can call it twice. The return value lists any names it skipped.

## When something goes wrong

Every error the library throws extends `RtlyKit\Exceptions\RtlyKitException`. Catch that one class and you have them all. Each error has a stable code and some context:

```php
use RtlyKit\Exceptions\RtlyKitException;
use function RtlyKit\jdate;

try {
    jdate('not a date');
} catch (RtlyKitException $e) {
    $e->getErrorCode();   // an ErrorCode case, here invalid_date
    $e->getContext();     // extra details, as an array
}
```

Bad input never leaks a raw `TypeError` or `ValueError`. Years outside the supported range throw `InvalidDateException`. Read the [error guide](docs/en/error-handling.html) for the full list.

## How accurate is it?

We prefer to tell you the limits now than have you find them in production.

- **Hijri.** The Umm al-Qura table covers AH 1300 to 1500. We built it from ICU/CLDR data. We have not checked it month by month against the official KACST list.
- **Iranian Islamic holidays.** They come from the Hijri calendar. Iran sets them by moon sighting, so they can be one or two days off. Fixed Jalali holidays, like Nowruz, are exact.
- **Prayer times.** Sunrise and sunset match NOAA and timeanddate within a minute. We checked Tehran and Makkah against official tables, including Isha in Ramadan. Egypt only agrees with the Dar al-Ifta table. We have **not** checked Karachi, MWL or ISNA against an official timetable. Tehran Isha (14°) and Asr are not covered by the tables we had.
- **Data tables.** Bank BINs (39), Sheba bank codes (38) and national-code offices (547) come from community lists with shared roots. Each entry has at least two sources. A valid card can still return `null` for the bank name. That does not mean the card is wrong.
- **Arabic number words.** Masculine form only, up to 999,999,999. Ask a native speaker to review formal or legal text.

Found a mistake? Please [open an issue](https://github.com/ehsanenaloo/RTLY-Kit/issues/new/choose) with a source. The full list is in the [accuracy guide](docs/en/accuracy-and-data.html).

## Requirements

- PHP **8.2** or newer, with `ext-mbstring` (on by default).
- No required Composer packages. `nesbot/carbon` and `illuminate/support` are optional.
- Tested in CI on PHP 8.2, 8.3 and 8.4. Works with Laravel 11 and 12 and Carbon 3.

## Guides

The full guide is in English and Persian, with search. Open [`docs/index.html`](docs/index.html) or start here:

| Topic | Read |
|---|---|
| Start | [Quick start](docs/en/quick-start.html) |
| Calendars | [Jalali](docs/en/jalali.html) · [Hijri](docs/en/hijri.html) · [Hebrew](docs/en/hebrew.html) · [Convert and compare](docs/en/convert-and-compare.html) |
| Validation | [Validators overview](docs/en/validators-overview.html) |
| Numbers and text | [Digits and format](docs/en/digits-and-format.html) · [Number words](docs/en/number-words.html) · [Text tools](docs/en/text-tools.html) |
| Holidays and prayer | [Holidays](docs/en/holidays.html) · [Prayer times](docs/en/prayer-times.html) |
| Laravel | [Setup](docs/en/laravel-setup.html) · [Validation and cast](docs/en/laravel-validation-and-cast.html) |
| Reference | [Helpers](docs/en/helpers-and-globals.html) · [Errors](docs/en/error-handling.html) · [API stability](docs/en/api-stability.html) · [FAQ](docs/en/troubleshooting-faq.html) |

Moving from an early build? See [UPGRADE.md](UPGRADE.md). Recent changes are in [CHANGELOG.md](CHANGELOG.md).

## Contributing

Bug reports, data corrections and pull requests are welcome. Read [CONTRIBUTING.md](.github/CONTRIBUTING.md) first. It explains the Docker workflow, the code style and how we handle data sources. Security problems go through [SECURITY.md](.github/SECURITY.md), not public issues.

## License

[MIT](LICENSE). Data sources and their notes are listed in [SOURCES.md](resources/data/SOURCES.md) and [NOTICE](NOTICE).

## About

Made by [Ehsan Enaloo](https://github.com/ehsanenaloo). Topics: `jalali` `hijri` `hebrew` `persian` `arabic` `rtl` `php` `laravel` `carbon` `iran` `prayer-times`.
