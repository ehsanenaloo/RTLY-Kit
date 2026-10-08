<?php

declare(strict_types=1);

/**
 * BIN (first 6 digits, as int) → bank name (Persian).
 *
 * Sources (verified 2026-10-07); an entry is included only when at least
 * two independent sources agree on the BIN→bank mapping:
 *  - github.com/masihgh/iranian-bank-list (banks.json)
 *  - ekhtebar.ir "پیش‌شماره کارت‌های عابر بانک" (table)
 *  - bankavl.com "تشخیص بانک از روی پیش شماره کارت بانکی" (table)
 *  - pypi.org/project/ircards, pub.dev/packages/iranian_banks (spot checks)
 * Single-source or conflicting BINs are deliberately omitted.
 *
 * 585983 (Tejarat) is Tejarat's newer prefix: the bank announced the change from
 * 627353 to 585983 on 3 Khordad 1395 (reported by way2pay.ir and asriran.com,
 * read 2026-10-08), and masihgh/iranian-bank-list and pishkhanak.com list it.
 * Both prefixes stay in the table. No official BIN registry was reachable.
 */

return [
    603799 => 'بانک ملی ایران',
    589210 => 'بانک سپه',
    627381 => 'بانک انصار',
    636949 => 'بانک حکمت ایرانیان',
    639370 => 'بانک مهر اقتصاد',
    627648 => 'بانک توسعه صادرات',
    207177 => 'بانک توسعه صادرات',
    627961 => 'بانک صنعت و معدن',
    603770 => 'بانک کشاورزی',
    639217 => 'بانک کشاورزی',
    628023 => 'بانک مسکن',
    627760 => 'پست بانک ایران',
    502908 => 'بانک توسعه تعاون',
    627412 => 'بانک اقتصاد نوین',
    622106 => 'بانک پارسیان',
    639194 => 'بانک پارسیان',
    502229 => 'بانک پاسارگاد',
    639347 => 'بانک پاسارگاد',
    627488 => 'بانک کارآفرین',
    502910 => 'بانک کارآفرین',
    621986 => 'بانک سامان',
    639346 => 'بانک سینا',
    639607 => 'بانک سرمایه',
    502806 => 'بانک شهر',
    502938 => 'بانک دی',
    603769 => 'بانک صادرات ایران',
    610433 => 'بانک ملت',
    991975 => 'بانک ملت',
    627353 => 'بانک تجارت',
    585983 => 'بانک تجارت',
    589463 => 'بانک رفاه کارگران',
    636214 => 'بانک آینده',
    504172 => 'بانک قرض‌الحسنه رسالت',
    505785 => 'بانک ایران زمین',
    639599 => 'بانک قوامین',
    505416 => 'بانک گردشگری',
    628157 => 'موسسه اعتباری توسعه',
    606373 => 'بانک قرض‌الحسنه مهر ایران',
    606256 => 'موسسه اعتباری ملل',
];
