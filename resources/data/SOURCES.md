# Data sources

RTLY-Kit has no required dependency, so its reference data is embedded in the source. Most of it has no official machine-readable publication, so the tables were built from public sources. This file lists them and says what was compared and when. The docblocks of the data files are the detailed record for each table.

## Two-source rule

A BIN, a Sheba bank code or a national-code prefix is included only when at least two independent sources agree. Entries found in one source, or where sources disagree, are left out. A missing entry therefore means "unknown" and not "invalid".

Notes for all tables:

- Sources were read on 2026-10-07 and 2026-10-08. Banks merge, rename and change ranges, so tables age.
- Some community datasets share a common origin. We treat their agreement as strong but not fully independent.
- Only facts (numeric ranges and the names they map to) were taken. No source text or code was copied.
- The data is best-effort and is not an official registry. For legal or financial decisions, use an official source as well.

## Bank card BINs (`resources/data/bank-bins.php`)

BIN (first 6 digits) to bank name, two agreeing sources among:

- github.com/masihgh/iranian-bank-list (banks.json)
- ekhtebar.ir, table of card prefixes
- bankavl.com, table for finding a bank from a card prefix
- pypi.org/project/ircards and pub.dev/packages/iranian_banks (spot checks)

Card validity is the Luhn checksum and does not use this table.

Check of 2026-10-08: all 39 BINs appear on at least one of three public pages (ekhtebar.ir, bankavl.com, pishkhanak.com) with the same bank, and no page names a different bank. BIN 585983 (Tejarat) is confirmed by the bank's own announcement of 3 Khordad 1395, as reported by way2pay.ir and asriran.com (the prefix changes from 627353 to 585983), and by pishkhanak.com and masihgh/iranian-bank-list. The older prefix 627353 stays in the table. Several other card-prefix pages still list only 627353, which fits an older publication date. The masihgh/iranian-bank-list file has some rows that disagree with every other source, so it is used only as supporting evidence. No Central Bank or Shaparak BIN list could be reached.

## Sheba (IBAN) bank codes (`resources/data/sheba-banks.php`)

The IBAN check is ISO 7064 mod-97-10. The 3-digit bank identifier is kept when two sources list it:

- persian-tools dataset, as vendored by github.com/alihoushy/iranian-sheba (`resources/banks.php`)
- pishkhanak.com/tools/iran-banks-directory
- bankavl.com bank identifier tables
- nabzebourse.com, 20-bank table
- salambank.net, bank identification article
- almico.ir, cardinfo.ir and khanesarmaye.com for single codes

Official source: the national IBAN specification of the Central Bank of Iran, as published by Bank Melli Iran (<https://bmi.ir/fa/pages/192/>, section 5-2-1, page dated 19 Tir 1396). The live page is blocked from outside Iran (HTTP 403), so it was read in the Internet Archive capture of 2021-05-18. It lists 19 identifiers: 010 to 021, 051, 053, 054, 055, 056, 057 and 058. All 19 match our table. The list settles code 051: it is the credit institution Tose'e, not Tose'e Ta'avon. The list is dated 2017, so codes assigned later are not in it.

The other codes are not in an official list that we could reach. Each is held by at least two public sources: 022, 052, 060, 063, 064, 065, 066, 069, 070, 075, 079 (pishkhanak.com, almico.ir, the alihoushy dataset), 073 and 095 (pishkhanak.com, cardinfo.ir, the alihoushy dataset), 078 (pishkhanak.com, khanesarmaye.com, the alihoushy dataset), 059, 061, 062 (several pages) and 080 (nabzebourse.com, pishkhanak.com). Code 090 (alternative code of Mehr Iran) has two sources: pishkhanak.com and the alihoushy dataset.

## National-code place-of-issue prefixes (`resources/data/national-code-locations.php`)

The checksum is the public national-code algorithm. The 3-digit prefix is a hint about the place of issue, not of birth or residence. The civil registry publishes no machine-readable list. A prefix is included only when three community datasets agree on province and city and a fourth does not contradict it:

1. persian-tools (npm `@persian-tools/persian-tools` 4.0.4, `getPlaceByIranNationalId`)
2. github.com/benyaminsalimi/Iranian-national-code-generator (`city_codes.json`)
3. rghorbani/node-iranian-ssn 1.0.2 (`lib/data/cities.json`)
4. iran-lib 1.0.22, used only to veto conflicting prefixes

Ports of persian-tools were not counted as separate sources. 547 prefixes are included.

## Mobile operator prefixes (`src/Validation/Mobile.php`)

Official source: the national numbering plan that the Communications Regulatory Authority (CRA) communicated to the ITU on 24.VIII.2026 (ITU page <https://www.itu.int/oth/T0202000066/en>, English Word file read directly). It lists these national destination codes as "Mobile services": 900-905, 91, 920-923, 93, 990-994, 99510, 99550, 996, 9981, 9982, 99830-99832, 99888, 99900-99903, 9991, 99921, 99930-99934, 9995, 99969, 99977, 9998 and 9999. These are the codes behind `Mobile::isAllocated()`. The plan lists the 94 block (9412, 94200, 94220, 94221, 94260, 942121, 94280x, 94290x, 9430130, 940000, 940009, 94440) as non-geographical fixed numbers. Every prefix in our operator table lies inside one of the mobile blocks.

The plan names no operators. Operator names rest on public tables that agree with each other: en.wikipedia.org (Telephone numbers in Iran), fa.wikipedia.org (list of mobile operators in Iran, which cites the ITU document and the operators' own pages), digiato.com (Hamrah-e Aval, Irancell, Rightel, Anarestan), rondbaz.com (2022), setare.com (2021), virgool.io, and the pages of Irancell, Aptel and Shatel Mobile. Number portability means a prefix never proves the current operator. Per entry:

- 0919: Hamrah-e Aval (credit SIM). Listed by en.wikipedia, fa.wikipedia, digiato, setare.com and rondbaz.com. Inside the CRA block 91.
- 0990 to 0994: Hamrah-e Aval (0994 is the Anarestan child SIM). Same sources. CRA 990-994. Note that 0991 is not 09991, which is a different allocation.
- 09991: Aptel. The CRA allocates the whole code 9991. Sources: fa.wikipedia, rondbaz.com and Aptel's published number 09991000000 (aptel.ir).
- 09981 and 09982: Shatel Mobile (en.wikipedia, fa.wikipedia, rondbaz.com, Shatel's support number 09981000000, CRA 9981 and 9982). The blocks 09983x and 09988x are separate allocations in the plan, and fa.wikipedia assigns 09988 to Smartcomm, so they return no operator.
- 0923: Rightel (en.wikipedia lists 0921 to 0923, fa.wikipedia, digiato; CRA 923). 0932: Taliya (en.wikipedia, fa.wikipedia citing the operator's archived price page, virgool.io). 0934: TeleKish (en.wikipedia, fa.wikipedia, rondbaz.com). 0931: Espadan, Isfahan province (en.wikipedia, fa.wikipedia, virgool.io). The three 093x operator names rest on three public pages each.
- 0941 and the rest of the 094 block are left out. Three public pages list 0941 as Irancell TD-LTE, but the plan puts the 94 block among fixed numbers.

The other blocks (0900 to 0905, 0910 to 0918, 0930, 0933, 0935 to 0939, 0920 to 0922) lie inside CRA mobile blocks and are named by at least two public pages each.

## Umm al-Qura table (`resources/data/umm-al-qura.php`)

The month-length table for AH 1300 to 1500 was generated from the ICU/CLDR `islamic-umalqura` calendar data and spot-checked against known anchors (1 Ramadan 1446 = 2025-03-01, 1 Muharram 1447 = 2025-06-26, 1 Shawwal 1445 = 2024-04-10).

Check of 2026-10-08 against the official Umm Al-Qura Calendar of KACST (<https://www.ummulqura.org.sa/en/calendar>, city Mecca): for each of the 183 selectable years AH 1318 to 1500, all 12 month starts were read from the site and compared with the table. That is 2196 month starts with no difference (1900-04-30 to 2077-11-16). The official site does not offer AH 1300 to 1317, so those years rest on the ICU/CLDR data. The last day of AH 1500 and cities other than Mecca were not compared. `Hijri::ummAlQuraVerifiedRange()` returns `[1318, 1500]`. ICU and CLDR are distributed under the Unicode License (<https://www.unicode.org/license.txt>). Only the resulting month lengths (facts) are embedded, no ICU code. Outside AH 1300 to 1500 the arithmetic tabular calendar is used.

## Jalali calendar (`src/Calendar/Jalali.php`)

The conversion uses the arithmetic 33-year rule (leap years at residues 1, 5, 9, 13, 17, 22, 26 and 30 modulo 33). It was compared with:

- The official table of the Calendar Center, Institute of Geophysics, University of Tehran, "Common and leap Solar Hijri years (1206 to 1498)", read from the Internet Archive copy captured 2023-07-12. It gives the Gregorian date of 1 Farvardin for 293 years. The rule gives the same Nowruz date and the same leap years for every year from 1206 to 1497. The table was also compared with the plain-text transcription kept by R. Pournader (CC0): 293 of 293 rows identical.
- An independent astronomical computation of the spring equinox (Meeus, *Astronomical Algorithms*, chapter 27, with the Delta T polynomials of Espenak and Meeus and local apparent noon at 52.5 degrees east). It reproduces all 293 official dates, and the rule agrees with it for every year from 1178 to 1502. The equinox routine itself was checked against the USNO seasons data for 1700 to 2100 (root mean square 33 seconds).

Outside 1178 to 1502 there is no official definition, so the library keeps the same fixed rule. The known-answer data is in `tests/Fixtures/jalali-official-nowruz.php`.

## Official holiday dates (`resources/data/iran-official-holidays.php`)

Iran fixes its Islamic holidays by moon sighting. The data file holds the published dates for the Jalali years 1380 to 1405:

1. The official yearly calendar of the Calendar Center, Institute of Geophysics, University of Tehran (calendar.ut.ac.ir, "Holiday-<year>.pdf", one table per year). Read for 1394 and 1396 to 1405.
2. time.ir per-day pages (`https://www.time.ir/event/<year>/<month>/<day>`), scanned for the years 1380 to 1406.
3. For 1395, a news list of the official holidays (fararu.com) next to the PDF digits.

A year is `official` when the University of Tehran table and one more source agree on every recorded date: 1394 and 1396 to 1405. A year is `reported` when the dates come from one published source: 1380 to 1393 and 1395. The list of holidays in reported years may be incomplete. For 1406 no data was published yet on 2026-10-08. The fixed Jalali holidays are public law and custom and are not part of this file. Notes per year are in the data file.

## Prayer times (`src/Prayer/PrayerTimes.php`)

The times are calculated from the low-precision solar position equations of Jean Meeus, *Astronomical Algorithms* (the same series as the NOAA Solar Calculator), and standard spherical-trigonometry hour-angle formulas. Sunrise and sunset use the usual altitude of -0.833 degrees. Method angles are published conventions: MWL 18 / 17, ISNA 15 / 15, Egypt 19.5 / 17.5, Makkah 18.5 with Isha 90 minutes after Maghrib (120 in Ramadan), Karachi 18 / 18, and the Tehran convention of Fajr 17.7, Isha 14 and Maghrib 4.5. The high-latitude rules follow the definitions of the PrayTimes "High Latitude Adjustments" page (<https://praytimes.org/calculation>, read 2026-10-08): middle of the night, one seventh of the night, and the angle divided by 60. City coordinates are approximate public values. Sunrise and sunset were compared with the NOAA Solar Calculator (12 city and date pairs, all within 1 minute). See `NOTICE`.

Comparison with published timetables (all on 2026-10-08; agreement within 1 to 2 minutes):

- Tehran: Institute of Geophysics, University of Tehran, tables for 1405 (Tehran, Mashhad, Isfahan, Shiraz; four dates). Fajr, sunrise, noon and Maghrib agree. The tables have no Isha or Asr column. A member of the Calendar Council of the Institute states Fajr 17.7 and Maghrib 4.5 degrees (Hawzah News interview, 1 Ordibehesht 1399).
- Makkah: Umm Al-Qura calendar, all six times, including Isha in Ramadan.
- Egypt: Dar al-Ifta, Cairo, six dates in October 2026, all six times. The page does not name the angle authority.
- Turkey, Diyanet: Istanbul, Ankara and Izmir, four dates between 2026-10-08 and 2026-11-07. The published imsak and yatsi columns agree with our MWL Fajr and Isha (18 / 17) within 1 minute. The other columns differ from plain astronomical times by steady offsets of several minutes that the pages do not explain, so they are not counted.
- Malaysia, JAKIM e-solat, zone SGR01 (Kuala Lumpur), October 2026: Fajr and Isha are 1 to 2 minutes later than ours, which fits 18 / 18. JAKIM states no method on the pages read.
- Karachi and Hanafi Asr: Jamia Uloom-e-Islamia Allama Banuri Town, Karachi (<https://www.banuri.edu.pk/namaz-times>, October 2026, "Banuri Town" method with Hanafi Asr). Our Karachi method (18 / 18) with Hanafi Asr agrees with its Fajr, sunrise, Asr, sunset and Isha within 1 minute on all 31 days, exactly on most. Its Shafi'i choice agrees with our standard Asr. The page states no angles.
- Hanafi Asr: the fatwa desk of Darul Uloom Deoband (<https://islamqa.org/hanafi/darulifta-deoband/24409/>) states that Asr begins when the shadow is twice the object plus the noon shadow, which is shadow factor 2.
- ISNA: the Fiqh Council of North America names 15 degrees for both Fajr and Isha for the setting that corresponds to ISNA in prayer apps (<https://fiqhcouncil.org/the-suggested-calculation-method-for-fajr-and-isha/>, published 2021-11-08). ISNA's own site and ISNA Canada's timetables could not be read, so no ISNA table was compared.
- Tehran Isha: the 14 degree figure is attributed to the Lavaa Institute, Qom, by two secondary pages (ijtihadnet.ir, intime.ir). The Institute of Geophysics publishes no Isha or Asr column, and no official Tehran table with Isha was found.

We found no timetable issued by the University of Islamic Sciences, Karachi, or by the Muslim World League.

## Check of 2026-10-08 in short

- Umm al-Qura: matches KACST for every month start of AH 1318 to 1500. AH 1300 to 1317 rest on ICU/CLDR data.
- Jalali: matches the official calendar for 1206 to 1497 and the astronomical definition for 1178 to 1502.
- Holidays: official dates for 1394 and 1396 to 1405, reported dates for 1380 to 1393 and 1395.
- Bank BINs, Sheba codes and mobile prefixes: consistent with several public pages. The 19 Sheba codes of the Central Bank specification and the mobile blocks of the numbering plan are matched against official documents. Operator names, other Sheba codes and BINs rest on public pages. The checks were made on 2026-10-08.
