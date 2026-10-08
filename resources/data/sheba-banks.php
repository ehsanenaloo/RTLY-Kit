<?php

declare(strict_types=1);

/**
 * Bank identifier -> bank name (Persian).
 *
 * The identifier is characters 5-7 of the full `IRkk...` number, i.e. the
 * 3 digits right after the 2-letter country code and 2 check digits (digits
 * 3-5 of the 24-digit form without `IR`). Keys are strings because of the
 * leading zeros. Names are those of the issuing bank at assignment time:
 * codes of merged institutions (e.g. 052, 063, 065, 073, 079 -> Sepah;
 * 080 -> Melli) remain valid on existing accounts.
 *
 * Sources (checked 2026-10-07; a code is kept only when at least two
 * independent sources list it):
 *  - persian-tools dataset as vendored by alihoushy/iranian-sheba
 *    (resources/banks.php), https://github.com/alihoushy/iranian-sheba
 *  - https://pishkhanak.com/tools/iran-banks-directory
 *  - https://bankavl.com/article/33762 and /article/8764 (bank identifier tables)
 *  - https://nabzebourse.com/fa/news/31451 (20-bank table)
 *  - https://salambank.net/blog/which-bank-is-this-number/
 * Codes 022, 052, 060/090, 061, 063-066, 069, 073, 075, 078, 079, 095 are
 * confirmed by the first two only (the official CBI table was not
 * reachable). 064/066/069/078 additionally appeared in secondary web
 * summaries. Unconfirmed codes are intentionally omitted.
 */

return [
    '010' => 'بانک مرکزی',
    '011' => 'بانک صنعت و معدن',
    '012' => 'بانک ملت',
    '013' => 'بانک رفاه',
    '014' => 'بانک مسکن',
    '015' => 'بانک سپه',
    '016' => 'بانک کشاورزی',
    '017' => 'بانک ملی ایران',
    '018' => 'بانک تجارت',
    '019' => 'بانک صادرات',
    '020' => 'بانک توسعه صادرات',
    '021' => 'پست بانک ایران',
    '022' => 'بانک توسعه تعاون',
    '051' => 'موسسه اعتباری توسعه',
    '052' => 'بانک قوامین',
    '053' => 'بانک کارآفرین',
    '054' => 'بانک پارسیان',
    '055' => 'بانک اقتصاد نوین',
    '056' => 'بانک سامان',
    '057' => 'بانک پاسارگاد',
    '058' => 'بانک سرمایه',
    '059' => 'بانک سینا',
    '060' => 'بانک قرض‌الحسنه مهر ایران',
    '061' => 'بانک شهر',
    '062' => 'بانک آینده',
    '063' => 'بانک انصار',
    '064' => 'بانک گردشگری',
    '065' => 'بانک حکمت ایرانیان',
    '066' => 'بانک دی',
    '069' => 'بانک ایران زمین',
    '070' => 'بانک قرض‌الحسنه رسالت',
    '073' => 'موسسه اعتباری کوثر',
    '075' => 'موسسه اعتباری ملل',
    '078' => 'بانک خاورمیانه',
    '079' => 'بانک مهر اقتصاد',
    '080' => 'موسسه اعتباری نور',
    '090' => 'بانک قرض‌الحسنه مهر ایران',
    '095' => 'بانک ایران ونزوئلا',
];
