# Data sources

RTLY-Kit is zero-dependency, so its reference data is embedded in the source. No official machine-readable publication exists for most of it, so the tables were compiled from public third-party sources, listed here. The docblocks of the files named below are authoritative for what each table contains.

## Two-source rule

A BIN, Sheba bank code or national-code prefix is included only when at least two independent sources agree. Entries found in a single source, or where sources conflict, are omitted on purpose. A missing entry therefore means "unknown", not "invalid".

Caveats for all tables:

- Sources were checked on 2026-10-07. Banks merge, rename and reassign ranges, so tables drift.
- Some community datasets share ancestry, so agreement is strong but not fully independent.
- Only facts (numeric ranges and the names they map to) were taken. No source text or code was copied.
- The data is best-effort, not an official registry. Do not rely on it for legal or financial decisions.

## Bank card BINs (`src/Validation/BankCard.php`)

BIN (first 6 digits) to bank name, two agreeing sources among:

- github.com/masihgh/iranian-bank-list (banks.json)
- ekhtebar.ir, table of card prefixes
- bankavl.com, table for identifying a bank from a card prefix
- pypi.org/project/ircards and pub.dev/packages/iranian_banks (spot checks)

Card validity itself is the Luhn checksum, independent of this table.

## Sheba (IBAN) bank codes (`src/Validation/Sheba.php`)

The IBAN check is ISO 7064 mod-97-10. The 3-digit bank identifier is kept when two sources list it:

- persian-tools dataset as vendored by github.com/alihoushy/iranian-sheba (`resources/banks.php`)
- pishkhanak.com/tools/iran-banks-directory
- bankavl.com bank identifier tables
- nabzebourse.com, 20-bank table
- salambank.net, bank identification article

Codes 022, 052, 060/090, 061, 063-066, 069, 073, 075, 078, 079 and 095 are confirmed by the first two sources only, because the official Central Bank of Iran table was not reachable (see the docblock).

## National code place-of-issue prefixes (`src/Validation/NationalCode.php`)

The checksum is the public national-code algorithm. The 3-digit prefix lookup is a hint about the place of issuance, not of birth or residence. The civil registry publishes no machine-readable list, so a prefix is included only when three community datasets agree on province and city and a fourth does not contradict it:

1. persian-tools (npm `@persian-tools/persian-tools` 4.0.4, `getPlaceByIranNationalId`)
2. github.com/benyaminsalimi/Iranian-national-code-generator (`city_codes.json`)
3. rghorbani/node-iranian-ssn 1.0.2 (`lib/data/cities.json`)
4. iran-lib 1.0.22, used only to veto conflicting prefixes

The datasets share ancestry; ports of persian-tools were not counted as separate sources. 547 prefixes are included.

## Mobile operator prefixes (`src/Validation/Mobile.php`)

Only well-established prefix blocks are listed. The docblock cites no specific source for them, so treat the table as best-effort. Number portability means a prefix never guarantees the current operator. Uncertain blocks are omitted.

## Umm al-Qura table (`src/Calendar/Hijri.php`)

The month-length table for AH 1300-1500 was generated from the ICU/CLDR `islamic-umalqura` calendar data and spot-checked against known anchors (1 Ramadan 1446 = 2025-03-01, 1 Muharram 1447 = 2025-06-26, 1 Shawwal 1445 = 2024-04-10). It has not been verified month by month against the official Umm al-Qura publication. ICU and CLDR are distributed under the Unicode License (<https://www.unicode.org/license.txt>); only the resulting month lengths (facts) are embedded, no ICU code. Outside AH 1300-1500 the arithmetic tabular calendar is used.

## Prayer times (`src/Prayer/PrayerTimes.php`)

Calculated from the low-precision solar position equations of Jean Meeus, *Astronomical Algorithms* (the same series used by the NOAA Solar Calculator) and standard spherical-trigonometry hour-angle formulas; sunrise/sunset use the conventional -0.833 degree altitude. Method angles are published conventions, not code: Fajr/Isha MWL 18/17, ISNA 15/15, Egypt 19.5/17.5, Makkah 18.5 and Isha 90 min after Maghrib, Karachi 18/18, and the Tehran convention of Fajr 17.7, Isha 14, Maghrib 4.5. City coordinates are approximate public values. Sunrise/sunset were spot-checked against the NOAA Solar Calculator (12 city/date pairs, all within 1 minute). See `NOTICE`.
