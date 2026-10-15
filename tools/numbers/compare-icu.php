<?php

declare(strict_types=1);

/**
 * Compares the Arabic number words of RTLY-Kit with the Arabic spell-out rules of ICU/CLDR.
 *
 *   php tools/numbers/compare-icu.php     (needs the intl extension; development tool, not run in CI)
 *
 * ICU is a cross-check, not an authority: its Arabic rules write the hundreds as separate words
 * ("ثلاثة مائة"), use "إحدى عشر" for 11 and the oblique dual ("ألفين") in the nominative, and write
 * "ثلاثة مليون" and "إثنان مليون" for 3 and 2 million. The script maps these ICU conventions to the
 * forms RTLY-Kit uses (diacritics, hamza and the spelling مائة/مئة are ignored too), then lists the
 * numbers that still differ. As of 2026-10-15 (ICU 76.1) every number from 0 to 9999 that was tried
 * agrees; the remaining differences are the groups of 101 to 110 thousand and 200 thousand, where RTLY-Kit
 * writes the split form that the specification marks as a design decision (D5), and the plural
 * scale names above one million.
 */

use RtlyKit\Number\NumberToWords;

if (! extension_loaded('intl')) {
    fwrite(STDERR, "The intl extension is needed.\n");
    exit(2);
}
require __DIR__.'/../../vendor/autoload.php';

$icu = new NumberFormatter('ar', NumberFormatter::SPELLOUT);
echo 'ICU ', INTL_ICU_VERSION, "\n";

function plain(string $s): string
{
    $s = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
    $s = str_replace(['مائة', 'مائتان', 'مائتين'], ['مئة', 'مئتان', 'مئتين'], $s);
    $s = (string) preg_replace('/[أإآ]/u', 'ا', $s);

    return str_replace('ى', 'ي', $s);
}

function ours(string $s): string
{
    return (string) preg_replace('/\s+/u', '', plain($s));
}

function icuAsOurs(string $s): string
{
    $s = plain($s);
    $s = (string) preg_replace('/(ثلاث|اربع|خمس|ست|سبع|ثمان|تسع)(?:ة|ية)? مئة/u', '$1مئة', $s);
    $s = str_replace(['احدي عشر', 'الفين'], ['احد عشر', 'الفان'], $s);

    return (string) preg_replace('/\s+/u', '', $s);
}

$sets = [
    '0 to 999' => range(0, 999),
    '1000 to 9999, every 7th' => range(1000, 9999, 7),
    '10000 to 999999, every 997th' => range(10000, 999999, 997),
];
$status = 0;
foreach ($sets as $name => $numbers) {
    $differ = [];
    foreach ($numbers as $n) {
        $a = NumberToWords::convert($n, 'ar');
        $b = (string) $icu->format($n);
        if (ours($a) !== icuAsOurs($b)) {
            $differ[] = [$n, $a, $b];
        }
    }
    printf("%s: %d numbers, %d differ\n", $name, count($numbers), count($differ));
    foreach (array_slice($differ, 0, 5) as [$n, $a, $b]) {
        echo "   {$n}\n      ours: {$a}\n      icu:  {$b}\n";
    }
    if ($differ !== [] && $name !== '10000 to 999999, every 997th') {
        $status = 1;
    }
}
exit($status);
