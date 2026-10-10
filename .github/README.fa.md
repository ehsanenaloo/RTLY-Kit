<div align="center" dir="rtl">

[English](../README.md) | **فارسی** | [العربية (راهنما)](https://ehsanenaloo.github.io/RTLY-Kit/ar/)

<a href="https://ehsanenaloo.github.io/RTLY-Kit/fa/"><img src="../docs/assets/logo.svg" width="112" height="112" alt="لوگوی RTLY-Kit"></a>

# RTLY-Kit

**تاریخ، عدد و اعتبارسنجی برای برنامه‌های PHP فارسی و عربی.**

تقویم جلالی، هجری و عبری. اعتبارسنج‌های ایرانی. عدد به حروف، تعطیلات و اوقات شرعی.
یک بستهٔ کوچک. بدون وابستگی اجباری.

[![Install](https://img.shields.io/badge/Packagist-Install-F28D1A?logo=packagist&logoColor=white&style=for-the-badge)](https://packagist.org/packages/enaxon/rtly-kit)
[![User guide](https://img.shields.io/badge/User%20guide-Read%20online-0f766e?logo=readthedocs&logoColor=white&style=for-the-badge)](https://ehsanenaloo.github.io/RTLY-Kit/fa/)
[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-FFDD00?style=for-the-badge&logo=buymeacoffee&logoColor=black)](https://buymeacoffee.com/enaloo)

[![CI](https://github.com/ehsanenaloo/RTLY-Kit/actions/workflows/ci.yml/badge.svg)](https://github.com/ehsanenaloo/RTLY-Kit/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/enaxon/rtly-kit.svg?label=packagist)](https://packagist.org/packages/enaxon/rtly-kit)
[![Downloads](https://img.shields.io/packagist/dt/enaxon/rtly-kit.svg)](https://packagist.org/packages/enaxon/rtly-kit)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](https://ehsanenaloo.github.io/RTLY-Kit/fa/laravel-setup.html)
[![Dependencies](https://img.shields.io/badge/dependencies-none-2ea44f.svg)](#نیازمندی‌ها)
[![Docs](https://img.shields.io/badge/docs-fa%20%7C%20en%20%7C%20ar-0f766e.svg)](https://ehsanenaloo.github.io/RTLY-Kit/fa/)
[![License: MIT](https://img.shields.io/badge/license-MIT-yellow.svg)](../LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/ehsanenaloo/RTLY-Kit?style=social)](https://github.com/ehsanenaloo/RTLY-Kit/stargazers)

<a href="https://ehsanenaloo.github.io/RTLY-Kit/fa/"><img src="../docs/assets/screenshots/guide-validators-fa-dark.jpg" width="860" alt="صفحهٔ مرور اعتبارسنج‌ها در راهنمای فارسی، با پوستهٔ تیره و چیدمان راست‌به‌چپ"></a>

</div>

<div dir="rtl">

## چرا این بسته ساخته شد

ابزارهای فارسی و عربی PHP بین چند بسته پخش شده‌اند. یکی تاریخ جلالی را حل می‌کند. یکی دیگر کد ملی را چک می‌کند. سومی عدد را به حروف می‌نویسد. بیشترشان Carbon یا یک فریم‌ورک کامل می‌خواهند.

RTLY-Kit کارهای رایج را یک‌جا جمع کرده است. کدش PHP ساده است. نصبش می‌کنید، یک تابع را import می‌کنید و استفاده می‌کنید. مرحلهٔ راه‌اندازی ندارد و فایل تنظیمات اختیاری است.

---

## نصب

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

همین است. توابع کمکی در یک فضای‌نام هستند و با کد شما تداخل ندارند. اگر نام‌های کوتاه سراسری را می‌خواهید، [روشنشان کنید](#توابع-کمکی-بدون-تداخل).

---

## چه چیزهایی دارد

| بخش | چه می‌گیرید |
|---|---|
| **تقویم‌ها** | جلالی، هجری (ام‌القری) و عبری. تغییرناپذیر، قابل مقایسه با هم، PHP ساده |
| **اعتبارسنج‌ها** | کد ملی، شبا، کارت بانکی، موبایل، کدپستی، پلاک خودرو. نتیجهٔ روشن با کد خطای پایدار |
| **عددها** | رقم فارسی، عربی و انگلیسی. جداکنندهٔ هزارگان و عدد ترتیبی. عدد به حروف فارسی و عربی، با جنس، حالت اعرابی و حرکت‌گذاری برای عربی |
| **متن** | تبدیل حرف عربی به فارسی، تمیز کردن نیم‌فاصله، تشخیص جهت و خط، اسلاگ فارسی |
| **تعطیلات** | تاریخ‌های رسمی تعطیلات ایران برای ۱۳۹۴ و ۱۳۹۶ تا ۱۴۰۵، تاریخ‌های گزارش‌شده برای ۱۳۸۰ تا ۱۳۹۳ و ۱۳۹۵، و تخمین برای بقیهٔ سال‌ها. جابه‌جایی و تغییر دلخواه، آخر هفته و روز کاری |
| **اوقات شرعی** | روش‌های تهران، MWL، ISNA، مصر، مکه و کراچی، قاعدهٔ عرض‌های بالا و تنظیم دستی دقیقه‌ها |
| **Carbon و Laravel** | ماکروهای Carbon، شش قانون اعتبارسنجی، facade به نام `Jalali`، cast برای Eloquent و فایل تنظیمات اختیاری |

---

## چه کارهایی می‌شود کرد

### کار با سه تقویم

`Jalali` و `Hijri` و `Hebrew` یک قرارداد مشترک دارند. پس می‌توانید تاریخ‌های تقویم‌های مختلف را با هم مقایسه کنید.

```php
use RtlyKit\Calendar\Jalali;
use function RtlyKit\{jdate, hdate, hebrew_date};

$date = jdate('2026-03-21');
echo $date->addMonths(1)->format('Y/m/d');                       // 1405/02/01
echo Jalali::create(1405, 1, 1)->toGregorian()->format('Y-m-d'); // 2026-03-21

echo hdate('2026-03-21')->format('j F Y', 'en');            // 2 Shawwal 1447
echo hebrew_date('2026-03-21')->format('j F Y', 'en');      // 3 Nisan 5786
```

هر سه تقویم PHP ساده‌اند و به `ext-calendar` نیاز ندارند. [راهنمای جلالی](https://ehsanenaloo.github.io/RTLY-Kit/fa/jalali.html) و [راهنمای تبدیل و مقایسه](https://ehsanenaloo.github.io/RTLY-Kit/fa/convert-and-compare.html) را ببینید.

### اعتبارسنجی داده‌های ایرانی

هر اعتبارسنج یک نتیجهٔ روشن می‌دهد، نه فقط true یا false.

```php
use function RtlyKit\{validate_sheba, validate_national_code};

$result = validate_sheba('IR27 0170 0000 0010 0324 2000 01');
$result->isValid();   // true
$result->details();   // ['normalized' => 'IR270170000000100324200001', 'bank_code' => '017', 'bank_name' => 'بانک ملی ایران']

$bad = validate_national_code('0499370898');
$bad->errors();       // ['invalid_checksum']
```

کدهای خطا رشته‌های پایدار هستند، مثل `invalid_format` و `invalid_checksum`. پیام نمایشی را خودتان از روی آن‌ها می‌سازید. اعتبارسنج‌ها هیچ‌وقت خطا پرتاب نمی‌کنند. ورودی عجیب، مثل `null` یا آرایه، نتیجهٔ `invalid_type` می‌دهد. نتیجهٔ موبایل می‌گوید پیش‌شماره در بلوک موبایلِ طرح شماره‌گذاری ملی هست یا نه (`allocated`).

### نوشتن عدد و تمیز کردن متن

```php
use RtlyKit\Number\NumberToWords;
use function RtlyKit\{format_number, ordinal, normalize_text, text_direction, number_to_words};

echo format_number(1234567.5);          // ۱٬۲۳۴٬۵۶۷٫۵
echo ordinal(30);                       // سی‌ام
echo normalize_text('كتاب ٣ يك');        // کتاب ۳ یک
echo text_direction('سلام');            // rtl
echo number_to_words(1234, 'ar');       // ألف ومئتان وأربعة وثلاثون

// عربی: معدود بعد از عدد می‌آید و مؤنث است
echo NumberToWords::convert(3, 'ar', ['mode' => 'noun', 'gender' => 'f']);   // ثلاث
echo NumberToWords::ordinal(21, 'ar', ['gender' => 'f']);                     // الحادية والعشرون
```

### تعطیلات و اوقات شرعی

```php
use RtlyKit\Holiday\HolidayCalendar;
use RtlyKit\Prayer\PrayerTimes;
use function RtlyKit\is_iran_holiday;

var_dump(is_iran_holiday(1405, 1, 1));   // true

// اگر هلال یک روز دیرتر دیده شد، سال تخمینی را اصلاح کنید
$calendar = HolidayCalendar::default()->withIslamicOffset(1);
$calendar->sourceOf(1406)->value;        // estimated

$times = PrayerTimes::forCity('mecca', PrayerTimes::METHOD_MAKKAH)
    ->getTimes(new DateTimeImmutable('2026-06-01'));
echo $times['fajr'];                     // 04:11
```

[تقویم تعطیلات](https://ehsanenaloo.github.io/RTLY-Kit/fa/holiday-calendar.html) و [اوقات شرعی](https://ehsanenaloo.github.io/RTLY-Kit/fa/prayer-times.html) را ببینید.

### کار با Carbon و Laravel

اگر Carbon نصب باشد، ماکروهایش خودکار روشن می‌شوند.

```php
use Carbon\Carbon;

echo Carbon::parse('2026-03-21')->toJalali()->format('Y/m/d');   // 1405/01/01
echo Carbon::createFromJalali(1405, 1, 1)->toDateString();       // 2026-03-21
echo Carbon::parse('2026-03-21')->toHijri()->format('Y/m/d');    // 1447/10/02
```

Laravel بسته را خودش پیدا می‌کند. شش قانون اعتبارسنجی (`mobile` نام کوتاه `iran_mobile` است) با پیام فارسی، انگلیسی و عربی، یک facade به نام `Jalali` و یک cast برای Eloquent می‌گیرید. فایل تنظیمات اختیاری را با `php artisan vendor:publish --tag=rtly-kit-config` منتشر کنید.

```php
$request->validate([
    'national_code' => ['required', 'national_code'],
    'sheba'         => ['required', 'sheba'],
    'phone'         => ['required', 'iran_mobile'],
]);

protected $casts = ['published_at' => \RtlyKit\Laravel\Casts\JalaliCast::class];
```

جزئیات در [راهنمای Laravel](https://ehsanenaloo.github.io/RTLY-Kit/fa/laravel-setup.html).

---

## راهنمای کامل، به سه زبان

راهنما ۲۹ صفحه به فارسی، انگلیسی و عربی دارد. جست‌وجو دارد، دو پوستهٔ روشن و تیره دارد و بدون JavaScript هم خوانده می‌شود. صفحه‌های فارسی و عربی راست‌به‌چپ نوشته شده‌اند.

<table>
  <tr>
    <td width="50%"><a href="https://ehsanenaloo.github.io/RTLY-Kit/fa/validators-overview.html"><img src="../docs/assets/screenshots/guide-validators-fa-dark.jpg" alt="صفحهٔ مرور اعتبارسنج‌ها در راهنمای فارسی، پوستهٔ تیره"></a></td>
    <td width="50%"><a href="https://ehsanenaloo.github.io/RTLY-Kit/en/jalali.html"><img src="../docs/assets/screenshots/guide-jalali-light.jpg" alt="صفحهٔ تقویم جلالی در راهنمای انگلیسی، پوستهٔ روشن"></a></td>
  </tr>
  <tr>
    <td align="center"><sub>فارسی، پوستهٔ تیره، راست‌به‌چپ</sub></td>
    <td align="center"><sub>انگلیسی، پوستهٔ روشن</sub></td>
  </tr>
</table>

| موضوع | بخوانید |
|---|---|
| شروع | [شروع سریع](https://ehsanenaloo.github.io/RTLY-Kit/fa/quick-start.html) · [نصب](https://ehsanenaloo.github.io/RTLY-Kit/fa/installation.html) |
| تقویم‌ها | [جلالی](https://ehsanenaloo.github.io/RTLY-Kit/fa/jalali.html) · [هجری](https://ehsanenaloo.github.io/RTLY-Kit/fa/hijri.html) · [عبری](https://ehsanenaloo.github.io/RTLY-Kit/fa/hebrew.html) · [تبدیل و مقایسه](https://ehsanenaloo.github.io/RTLY-Kit/fa/convert-and-compare.html) · [ماکروهای Carbon](https://ehsanenaloo.github.io/RTLY-Kit/fa/carbon-macros.html) |
| اعتبارسنجی | [مرور اعتبارسنج‌ها](https://ehsanenaloo.github.io/RTLY-Kit/fa/validators-overview.html) · [کد ملی](https://ehsanenaloo.github.io/RTLY-Kit/fa/national-code.html) · [شبا و کارت بانکی](https://ehsanenaloo.github.io/RTLY-Kit/fa/sheba-and-bank-card.html) · [موبایل، کدپستی، پلاک](https://ehsanenaloo.github.io/RTLY-Kit/fa/mobile-postal-plate.html) |
| عدد و متن | [رقم‌ها و قالب‌بندی عدد](https://ehsanenaloo.github.io/RTLY-Kit/fa/digits-and-format.html) · [عدد به حروف](https://ehsanenaloo.github.io/RTLY-Kit/fa/number-words.html) · [عدد به حروف عربی](https://ehsanenaloo.github.io/RTLY-Kit/fa/arabic-number-words.html) · [ابزارهای متن](https://ehsanenaloo.github.io/RTLY-Kit/fa/text-tools.html) |
| تاریخ و زمان | [تعطیلات](https://ehsanenaloo.github.io/RTLY-Kit/fa/holidays.html) · [تقویم تعطیلات](https://ehsanenaloo.github.io/RTLY-Kit/fa/holiday-calendar.html) · [اوقات شرعی](https://ehsanenaloo.github.io/RTLY-Kit/fa/prayer-times.html) |
| Laravel | [راه‌اندازی](https://ehsanenaloo.github.io/RTLY-Kit/fa/laravel-setup.html) · [اعتبارسنجی و cast](https://ehsanenaloo.github.io/RTLY-Kit/fa/laravel-validation-and-cast.html) |
| مرجع | [توابع کمکی](https://ehsanenaloo.github.io/RTLY-Kit/fa/helpers-and-globals.html) · [خطاها](https://ehsanenaloo.github.io/RTLY-Kit/fa/error-handling.html) · [پایداری API](https://ehsanenaloo.github.io/RTLY-Kit/fa/api-stability.html) · [دقت و داده](https://ehsanenaloo.github.io/RTLY-Kit/fa/accuracy-and-data.html) · [سقف‌ها](https://ehsanenaloo.github.io/RTLY-Kit/fa/limits.html) · [ارتقا](https://ehsanenaloo.github.io/RTLY-Kit/fa/upgrade.html) · [راستی‌آزمایی انتشارها](https://ehsanenaloo.github.io/RTLY-Kit/fa/verifying-releases.html) · [پرسش‌های متداول](https://ehsanenaloo.github.io/RTLY-Kit/fa/troubleshooting-faq.html) |
| زبان‌های دیگر | [English](https://ehsanenaloo.github.io/RTLY-Kit/en/) · [العربية](https://ehsanenaloo.github.io/RTLY-Kit/ar/) |

---

## توابع کمکی بدون تداخل

۲۷ تابع کمکی در فضای‌نام `RtlyKit` هستند. هر کدام را لازم دارید با `use function` وارد کنید. چیزی به فضای سراسری اضافه نمی‌شود.

نام‌های کوتاه مثل `jdate()` را می‌خواهید؟ یک بار روشنشان کنید، مثلاً در فایل راه‌اندازی:

```php
$skipped = \RtlyKit\Globals::register();   // نام‌هایی که از قبل گرفته شده بودند
```

فقط نام‌های آزاد را تعریف می‌کند، تابع‌های شما را عوض نمی‌کند و خطا پرتاب نمی‌کند. دو بار صدا زدنش مشکلی ندارد. خروجی، فهرست نام‌های ردشده است.

---

## وقتی چیزی خراب می‌شود

هر خطایی که کتابخانه پرتاب می‌کند از `RtlyKit\Exceptions\RtlyKitException` ارث می‌برد. همین یک کلاس را بگیرید تا همه را گرفته باشید. هر خطا یک کد پایدار و کمی اطلاعات جانبی دارد:

```php
use RtlyKit\Exceptions\RtlyKitException;
use function RtlyKit\jdate;

try {
    jdate('not a date');
} catch (RtlyKitException $e) {
    $e->getErrorCode();   // یک case از ErrorCode، اینجا invalid_date
    $e->getContext();     // اطلاعات بیشتر، به شکل آرایه
}
```

مقداری از نوع پذیرفته‌شده که کتابخانه نتواند به کار ببرد، مثل سال بیرون از بازهٔ پشتیبانی‌شده، یک استثنای کتابخانه می‌دهد (برای تاریخ `InvalidDateException`) و هیچ‌وقت `TypeError` یا `ValueError` خام بیرون نمی‌دهد. مقداری از نوعی که متد قبول نمی‌کند، مثل آرایه به‌جای تاریخ، را خود PHP با `TypeError` رد می‌کند. فهرست کامل در [راهنمای خطاها](https://ehsanenaloo.github.io/RTLY-Kit/fa/error-handling.html).

---

## چه چیزهایی را بررسی کرده‌ایم

در 2026-10-08 کتابخانه را، هر جا به منبع رسمی دسترسی داشتیم، با آن مقایسه کردیم.

- **جلالی.** تبدیل برای هر سال از ۱۲۰۶ تا ۱۴۹۷ همان نوروز و همان سال‌های کبیسهٔ جدول رسمی دانشگاه تهران را می‌دهد. برای ۱۱۷۸ تا ۱۵۰۲ با تعریف نجومی هم می‌خواند.
- **هجری.** آغاز هر ماه از ۱۳۱۸ تا ۱۵۰۰ هجری قمری (۲۱۹۶ ماه) با تقویم رسمی ام‌القری KACST برابر است. ۱۳۰۰ تا ۱۳۱۷ از دادهٔ ICU/CLDR می‌آید.
- **تعطیلات.** تاریخ‌های رسمی برای سال‌های جلالی ۱۳۹۴ و ۱۳۹۶ تا ۱۴۰۵ را با دو منبع سنجیده‌ایم، و برای ۱۳۸۰ تا ۱۳۹۳ و ۱۳۹۵ تاریخ‌های منتشرشده را داریم. بقیهٔ سال‌ها از تقویم هجری تخمین زده می‌شود. تعطیلات ثابت، مثل نوروز، دقیق‌اند.
- **اوقات شرعی.** طلوع و غروب در حد یک دقیقه با ماشین‌حساب خورشیدی NOAA می‌خواند. در مقایسه با جدول‌های منتشرشده، وقت‌ها در حد ۱ تا ۲ دقیقه برابرند: تهران (صبح، طلوع، ظهر، مغرب)، مکه (همهٔ وقت‌ها، از جمله عشای رمضان)، مصر (دارالافتاء) و کراچی با عصر حنفی (یک مؤسسهٔ کراچی، ۳۱ روز). صبح و عشای جدول دیانت ترکیه با زاویه‌های ۱۸ و ۱۷ درجه جور است. قاعدهٔ عصر حنفی و زاویه‌های ۱۵ درجهٔ ISNA با گفتهٔ دارالعلوم دیوبند و شورای فقهی آمریکای شمالی می‌خواند.
- **جدول‌های داده.** ۱۹ کد بانک شبا در مشخصات منتشرشدهٔ IBAN بانک مرکزی با جدول ما برابر است. هر پیش‌شمارهٔ موبایلی که آورده‌ایم داخل یک بلوک موبایل در طرح شماره‌گذاری ملی است. BIN بانک‌ها و کدهای دیگر با چند صفحهٔ عمومی می‌خوانند.

### خوب است بدانید

- ایران تاریخ مناسبت‌های مذهبی را با رؤیت هلال تعیین می‌کند. پس سال تخمینی ممکن است یکی دو روز فرق کند. با [HolidayCalendar](https://ehsanenaloo.github.io/RTLY-Kit/fa/holiday-calendar.html) می‌توانید اصلاحش کنید.
- جدولی از ISNA، رابطه جهان اسلام یا دانشگاه علوم اسلامی کراچی پیدا نکردیم، و جدول رسمی تهران با عشا و عصر هم نیست.
- نام بانک و اپراتور و محل از فهرست‌های عمومی می‌آید، نه از ثبت رسمی. کارت معتبر ممکن است برای نام بانک `null` بدهد.
- چند خروجی عدد به حروف عربی هنوز منتظر بازبینی یک عرب‌زبان است.

اشتباهی دیدید؟ همراه با منبع [یک issue باز کنید](https://github.com/ehsanenaloo/RTLY-Kit/issues/new/choose). فهرست کامل در [راهنمای دقت و داده](https://ehsanenaloo.github.io/RTLY-Kit/fa/accuracy-and-data.html) است.

---

## نیازمندی‌ها

- PHP نسخهٔ **۸٫۲** یا بالاتر. فقط همین لازم است. `mbstring` یا افزونهٔ دیگری لازم نیست.
- بستهٔ اجباری Composer ندارد. `nesbot/carbon` و `illuminate/support` اختیاری‌اند.
- در CI روی PHP نسخه‌های ۸٫۲، ۸٫۳، ۸٫۴ و ۸٫۵ آزموده می‌شود. با Laravel 11، 12 و 13 (Laravel 13 به PHP نسخهٔ ۸٫۳ یا بالاتر نیاز دارد) و Carbon 3 کار می‌کند.

---

## مشارکت

گزارش باگ، اصلاح داده، اصلاح ترجمه و pull requestهای کوچک خوش‌آمد است. اول [CONTRIBUTING.md](CONTRIBUTING.md) را بخوانید. روش کار با Docker، سبک کد و شیوهٔ برخورد با منبع داده‌ها در آن آمده است.

- **نتیجهٔ اشتباه را گزارش کنید.** از فرم «اصلاح داده» استفاده کنید و منبع بگذارید. هر ردیف جدول دست‌کم دو منبع لازم دارد.
- **متن‌ها را بهتر کنید.** اصلاح راهنمای فارسی و ترجمهٔ عربی بسیار خوش‌آمد است.
- **به مخزن ستاره بدهید** اگر وقتتان را گرفته است. به پیدا شدن پروژه کمک می‌کند.

اگر RTLY-Kit برایتان مفید بود، می‌توانید [برای من یک قهوه بخرید](https://buymeacoffee.com/enaloo). هیچ انتظاری نیست و قدردانی می‌کنم.

مشکل امنیتی را از مسیر [SECURITY.md](SECURITY.md) گزارش کنید، نه issue عمومی.

### مشارکت‌کنندگان

از همهٔ کسانی که کمک کرده‌اند ممنونیم. نام شما بعد از اولین مشارکتِ پذیرفته‌شده اینجا می‌آید.

<a href="https://github.com/ehsanenaloo/RTLY-Kit/graphs/contributors"><img src="https://contrib.rocks/image?repo=ehsanenaloo/RTLY-Kit" alt="مشارکت‌کنندگان"></a>

از نسخهٔ اولیه می‌آیید؟ [UPGRADE.md](../UPGRADE.md) را ببینید. تغییرات اخیر در [CHANGELOG.md](../CHANGELOG.md) است.

---

## مجوز

[MIT](../LICENSE). منبع داده‌ها و یادداشت‌هایشان در [SOURCES.md](../resources/data/SOURCES.md) و [NOTICE](../NOTICE) آمده است.

## دربارهٔ پروژه

ساختهٔ [احسان عنالو](https://github.com/ehsanenaloo). RTLY-Kit جعبه‌ابزار PHP برای تقویم جلالی، هجری و عبری، اعتبارسنج‌های ایرانی، عدد به حروف، تعطیلات و اوقات شرعی است.

برچسب‌ها: `jalali` `hijri` `hebrew` `persian` `arabic` `rtl` `php` `laravel` `carbon` `iran` `prayer-times`

</div>
