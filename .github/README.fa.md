<div align="center" dir="rtl">

[English](../README.md) | **فارسی**

<a href="../docs/index.html"><img src="../docs/assets/logo.svg" width="112" height="112" alt="لوگوی RTLY-Kit"></a>

# RTLY-Kit

**تاریخ، عدد و اعتبارسنجی برای برنامه‌های PHP فارسی و عربی.**

تقویم جلالی، هجری و عبری. اعتبارسنج‌های ایرانی. اعداد به حروف، تعطیلات و اوقات شرعی.
یک بستهٔ کوچک. بدون وابستگی اجباری.

[![CI](https://github.com/ehsanenaloo/RTLY-Kit/actions/workflows/ci.yml/badge.svg)](https://github.com/ehsanenaloo/RTLY-Kit/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012-ff2d20.svg)](../docs/fa/laravel-setup.html)
[![Dependencies](https://img.shields.io/badge/dependencies-none-2ea44f.svg)](#نیازمندی‌ها)
[![License: MIT](https://img.shields.io/badge/license-MIT-yellow.svg)](../LICENSE)

<img src="../docs/assets/social-preview.svg" width="720" alt="RTLY-Kit: جعبه‌ابزار RTL فارسی و عربی برای PHP. composer require enaxon/rtly-kit">

</div>

<div dir="rtl">

## چرا این بسته ساخته شد

ابزارهای فارسی و عربی PHP بین چند بسته پخش شده‌اند. یکی تاریخ جلالی را حل می‌کند. دیگری کد ملی را چک می‌کند. سومی عدد را به حروف می‌نویسد. بیشترشان Carbon یا یک فریم‌ورک کامل می‌خواهند.

RTLY-Kit کارهای رایج را یک‌جا جمع کرده است. کد آن PHP ساده است. نصبش می‌کنید، یک تابع را import می‌کنید و استفاده می‌کنید. فایل تنظیمات و مرحلهٔ راه‌اندازی ندارد.

## شروع سریع

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

توابع کمکی در یک namespace هستند و با کد شما تداخل پیدا نمی‌کنند. اگر نام‌های کوتاه سراسری را می‌خواهید، می‌توانید [آن‌ها را روشن کنید](#توابع-کمکی-بدون-تداخل).

## چه کارهایی می‌شود کرد

### کار با سه تقویم

`Jalali`، `Hijri` و `Hebrew` شیء‌های تغییرناپذیر (immutable) هستند. هر سه یک قرارداد مشترک دارند، پس می‌توانید تاریخ‌های تقویم‌های مختلف را با هم مقایسه کنید.

```php
use RtlyKit\Calendar\Jalali;
use function RtlyKit\{jdate, hdate, hebrew_date};

$date = jdate('2026-03-21');
echo $date->addMonths(1)->format('Y/m/d');                       // 1405/02/01
echo Jalali::create(1405, 1, 1)->toGregorian()->format('Y-m-d'); // 2026-03-21

echo hdate('2026-03-21')->format('j F Y', 'en');            // 2 Shawwal 1447
echo hebrew_date('2026-03-21')->format('j F Y', 'en');      // 3 Nisan 5786
```

تقویم هجری به‌طور پیش‌فرض از جدول ام‌القری استفاده می‌کند و حالت حسابی هم دارد. هر سه تقویم PHP خالص‌اند و به `ext-calendar` نیازی ندارند. بیشتر در [راهنمای جلالی](../docs/fa/jalali.html) و [راهنمای تبدیل و مقایسه](../docs/fa/convert-and-compare.html).

### اعتبارسنجی داده‌های ایرانی

کد ملی، شبا، کارت بانکی، موبایل، کد پستی و پلاک خودرو. هر کدام یک نتیجهٔ روشن می‌دهد، نه فقط true یا false.

```php
use function RtlyKit\{validate_sheba, validate_national_code};

$result = validate_sheba('IR27 0170 0000 0010 0324 2000 01');
$result->isValid();   // true
$result->details();   // ['normalized' => 'IR270170000000100324200001', 'bank_code' => '017', 'bank_name' => 'بانک ملی ایران']

$bad = validate_national_code('0499370898');
$bad->errors();       // ['invalid_checksum']
```

کدهای خطا رشته‌های پایدار هستند، مثل `invalid_format` و `invalid_checksum`. پیام نمایشی را خودتان از روی آن‌ها می‌سازید. اعتبارسنج‌ها هرگز استثنا پرتاب نمی‌کنند. ورودی عجیب، مثل `null` یا آرایه، نتیجهٔ `invalid_type` می‌دهد.

### نوشتن عدد و پاک‌سازی متن

```php
use function RtlyKit\{format_number, ordinal, normalize_text, text_direction, number_to_words};

echo format_number(1234567.5);          // ۱٬۲۳۴٬۵۶۷٫۵
echo ordinal(30);                       // سی‌ام
echo normalize_text('كتاب ٣ يك');        // کتاب ۳ یک
echo text_direction('سلام');            // rtl
echo number_to_words(1234, 'ar');       // ألف ومئتان وأربعة وثلاثون
```

### تعطیلات و اوقات شرعی

```php
use RtlyKit\Prayer\PrayerTimes;
use function RtlyKit\is_iran_holiday;

var_dump(is_iran_holiday(1405, 1, 1));   // true

$times = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)
    ->getTimes(new DateTimeImmutable('2026-06-01'));
echo $times['fajr'];                     // 04:11
```

شش روش داخلی دارد: تهران، MWL، ISNA، مصر، مکه و کراچی. ببینید [هر روش تا چه اندازه راستی‌آزمایی شده](#دقت-چقدر-است).

### کار با Carbon و Laravel

اگر Carbon نصب باشد، ماکروهای آن خودبه‌خود فعال می‌شوند.

```php
use Carbon\Carbon;

echo Carbon::parse('2026-03-21')->toJalali()->format('Y/m/d');   // 1405/01/01
echo Carbon::createFromJalali(1405, 1, 1)->toDateString();       // 2026-03-21
echo Carbon::parse('2026-03-21')->toHijri()->format('Y/m/d');    // 1447/10/02
```

Laravel بسته را خودش پیدا می‌کند. شش قانون اعتبارسنجی با پیام فارسی، انگلیسی و عربی، یک facade به نام `Jalali` و یک cast برای Eloquent می‌گیرید.

```php
$request->validate([
    'national_code' => ['required', 'national_code'],
    'sheba'         => ['required', 'sheba'],
    'phone'         => ['required', 'iran_mobile'],
]);

protected $casts = ['published_at' => \RtlyKit\Laravel\Casts\JalaliCast::class];
```

جزئیات در [راهنمای Laravel](../docs/fa/laravel-setup.html).

## توابع کمکی بدون تداخل

۲۷ تابع کمکی در namespace با نام `RtlyKit` هستند. هر کدام را لازم دارید با `use function` وارد کنید. چیزی به فضای سراسری اضافه نمی‌شود.

نام‌های کوتاه مثل `jdate()` را می‌خواهید؟ یک بار روشنشان کنید، مثلاً در فایل bootstrap:

```php
$skipped = \RtlyKit\Globals::register();   // نام‌هایی که از قبل گرفته شده بودند
```

فقط نام‌های آزاد را تعریف می‌کند، هیچ تابع شما را جایگزین نمی‌کند و استثنا پرتاب نمی‌کند. دو بار صدا زدنش مشکلی ندارد. مقدار برگشتی فهرست نام‌های ردشده است.

## وقتی چیزی خراب می‌شود

هر خطایی که کتابخانه پرتاب می‌کند از `RtlyKit\Exceptions\RtlyKitException` ارث می‌برد. همین یک کلاس را بگیرید تا همه را گرفته باشید. هر خطا یک کد پایدار و کمی اطلاعات جانبی دارد:

```php
use RtlyKit\Exceptions\RtlyKitException;
use function RtlyKit\jdate;

try {
    jdate('not a date');
} catch (RtlyKitException $e) {
    $e->getErrorCode();   // یک case از ErrorCode، اینجا invalid_date
    $e->getContext();     // جزئیات بیشتر، به‌صورت آرایه
}
```

ورودی نامعتبر هرگز `TypeError` یا `ValueError` خام بیرون نمی‌دهد. سال بیرون از بازهٔ پشتیبانی‌شده `InvalidDateException` پرتاب می‌کند. فهرست کامل در [راهنمای خطاها](../docs/fa/error-handling.html).

## دقت چقدر است

بهتر است محدودیت‌ها را از ما بشنوید تا در محیط واقعی کشفشان کنید.

- **هجری.** جدول ام‌القری سال‌های ۱۳۰۰ تا ۱۵۰۰ هجری را پوشش می‌دهد. آن را از داده‌های ICU/CLDR ساخته‌ایم. ماه‌به‌ماه با فهرست رسمی KACST مقایسه‌اش نکرده‌ایم.
- **تعطیلات مذهبی ایران.** از تقویم هجری محاسبه می‌شوند، ولی ایران آن‌ها را با رؤیت هلال تعیین می‌کند. ممکن است یک یا دو روز اختلاف داشته باشند. تعطیلات ثابت جلالی، مثل نوروز، دقیق‌اند.
- **اوقات شرعی.** طلوع و غروب با NOAA و timeanddate در حد یک دقیقه می‌خواند. تهران و مکه را با جدول‌های رسمی سنجیده‌ایم، از جمله عشا در رمضان. مصر فقط با جدول دارالإفتاء هم‌خوان است. کراچی، MWL و ISNA را با هیچ جدول رسمی **نسنجیده‌ایم**. عشای تهران (۱۴ درجه) و عصر هم در جدول‌های در دسترس نبود.
- **جدول‌های داده.** BIN بانک‌ها (۳۹)، کد بانک شبا (۳۸) و دفاتر کد ملی (۵۴۷) از فهرست‌های اجتماعی با ریشهٔ مشترک آمده‌اند. هر ردیف دست‌کم دو منبع دارد. ممکن است کارت معتبر در نام بانک `null` بدهد. این به معنای اشتباه بودن کارت نیست.
- **اعداد عربی به حروف.** فقط حالت مذکر و تا ۹۹۹٬۹۹۹٬۹۹۹. برای متن رسمی یا حقوقی از یک عرب‌زبان بخواهید مرور کند.

اشتباهی دیدید؟ همراه با منبع [یک issue باز کنید](https://github.com/ehsanenaloo/RTLY-Kit/issues/new/choose). فهرست کامل در [راهنمای دقت و داده](../docs/fa/accuracy-and-data.html) است.

## نیازمندی‌ها

- PHP نسخهٔ **۸٫۲** یا بالاتر، با `ext-mbstring` (به‌طور پیش‌فرض روشن است).
- هیچ بستهٔ Composer اجباری ندارد. `nesbot/carbon` و `illuminate/support` اختیاری‌اند.
- در CI روی PHP نسخه‌های ۸٫۲، ۸٫۳ و ۸٫۴ آزموده می‌شود. با Laravel 11 و 12 و Carbon 3 کار می‌کند.

## راهنماها

راهنمای کامل فارسی و انگلیسی است و جست‌وجو دارد. [`docs/index.html`](../docs/index.html) را باز کنید یا از اینجا شروع کنید:

| موضوع | بخوانید |
|---|---|
| شروع | [شروع سریع](../docs/fa/quick-start.html) |
| تقویم‌ها | [جلالی](../docs/fa/jalali.html) · [هجری](../docs/fa/hijri.html) · [عبری](../docs/fa/hebrew.html) · [تبدیل و مقایسه](../docs/fa/convert-and-compare.html) |
| اعتبارسنجی | [مرور اعتبارسنج‌ها](../docs/fa/validators-overview.html) |
| عدد و متن | [ارقام و قالب](../docs/fa/digits-and-format.html) · [عدد به حروف](../docs/fa/number-words.html) · [ابزار متن](../docs/fa/text-tools.html) |
| تعطیلات و اوقات شرعی | [تعطیلات](../docs/fa/holidays.html) · [اوقات شرعی](../docs/fa/prayer-times.html) |
| Laravel | [نصب](../docs/fa/laravel-setup.html) · [اعتبارسنجی و cast](../docs/fa/laravel-validation-and-cast.html) |
| مرجع | [توابع کمکی](../docs/fa/helpers-and-globals.html) · [خطاها](../docs/fa/error-handling.html) · [پایداری API](../docs/fa/api-stability.html) · [پرسش‌های متداول](../docs/fa/troubleshooting-faq.html) |

از نسخهٔ اولیه می‌آیید؟ [UPGRADE.md](../UPGRADE.md) را ببینید. تغییرات اخیر در [CHANGELOG.md](../CHANGELOG.md) است.

## مشارکت

گزارش باگ، اصلاح داده و pull request خوش‌آمد است. اول [CONTRIBUTING.md](CONTRIBUTING.md) را بخوانید. روش کار با Docker، سبک کد و شیوهٔ برخورد با منبع داده‌ها در آن آمده است. مشکل امنیتی را از مسیر [SECURITY.md](SECURITY.md) گزارش کنید، نه issue عمومی.

## مجوز

[MIT](../LICENSE). منبع داده‌ها و یادداشت‌هایشان در [SOURCES.md](../resources/data/SOURCES.md) و [NOTICE](../NOTICE) آمده است.

## دربارهٔ پروژه

ساختهٔ [احسان عنالو](https://github.com/ehsanenaloo). برچسب‌ها: `jalali` `hijri` `hebrew` `persian` `arabic` `rtl` `php` `laravel` `carbon` `iran` `prayer-times`.

</div>
