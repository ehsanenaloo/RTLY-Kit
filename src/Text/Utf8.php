<?php

declare(strict_types=1);

namespace RtlyKit\Text;

/**
 * Minimal UTF-8 helpers built on PCRE (which is always compiled into PHP) so
 * the package needs no mbstring extension.
 *
 * Invalid UTF-8 contract: {@see isValid()} reports it, {@see scrub()} replaces it, {@see lower()} requires
 * valid input (callers validate first) and {@see truncate()} never fails: each
 * byte that is not part of a well-formed sequence is replaced by U+FFFD before
 * cutting, so the result is always valid UTF-8.
 *
 * Lower-casing uses the Unicode simple case mapping (one character in, one
 * character out; U+0130 maps to "i" + U+0307). It is context-free, so a
 * Greek capital sigma always becomes the medial sigma.
 *
 * @internal Not part of the public API.
 */
final class Utf8
{
    /**
     * Lower-case ranges: [first code point, last code point, step, delta].
     * Generated from the Unicode simple lower-case mapping (code points >= U+0080).
     *
     * @var list<array{int, int, int, int}>
     */
    private const LOWER = [
        [192,214,1,32],
        [216,222,1,32],
        [256,302,2,1],
        [306,310,2,1],
        [313,327,2,1],
        [330,374,2,1],
        [376,376,1,-121],
        [377,381,2,1],
        [385,385,1,210],
        [386,388,2,1],
        [390,390,1,206],
        [391,391,1,1],
        [393,394,1,205],
        [395,395,1,1],
        [398,398,1,79],
        [399,399,1,202],
        [400,400,1,203],
        [401,401,1,1],
        [403,403,1,205],
        [404,404,1,207],
        [406,406,1,211],
        [407,407,1,209],
        [408,408,1,1],
        [412,412,1,211],
        [413,413,1,213],
        [415,415,1,214],
        [416,420,2,1],
        [422,422,1,218],
        [423,423,1,1],
        [425,425,1,218],
        [428,428,1,1],
        [430,430,1,218],
        [431,431,1,1],
        [433,434,1,217],
        [435,437,2,1],
        [439,439,1,219],
        [440,444,4,1],
        [452,452,1,2],
        [453,453,1,1],
        [455,455,1,2],
        [456,456,1,1],
        [458,458,1,2],
        [459,475,2,1],
        [478,494,2,1],
        [497,497,1,2],
        [498,500,2,1],
        [502,502,1,-97],
        [503,503,1,-56],
        [504,542,2,1],
        [544,544,1,-130],
        [546,562,2,1],
        [570,570,1,10795],
        [571,571,1,1],
        [573,573,1,-163],
        [574,574,1,10792],
        [577,577,1,1],
        [579,579,1,-195],
        [580,580,1,69],
        [581,581,1,71],
        [582,590,2,1],
        [880,882,2,1],
        [886,886,1,1],
        [895,895,1,116],
        [902,902,1,38],
        [904,906,1,37],
        [908,908,1,64],
        [910,911,1,63],
        [913,929,1,32],
        [931,939,1,32],
        [975,975,1,8],
        [984,1006,2,1],
        [1012,1012,1,-60],
        [1015,1015,1,1],
        [1017,1017,1,-7],
        [1018,1018,1,1],
        [1021,1023,1,-130],
        [1024,1039,1,80],
        [1040,1071,1,32],
        [1120,1152,2,1],
        [1162,1214,2,1],
        [1216,1216,1,15],
        [1217,1229,2,1],
        [1232,1326,2,1],
        [1329,1366,1,48],
        [4256,4293,1,7264],
        [4295,4301,6,7264],
        [5024,5103,1,38864],
        [5104,5109,1,8],
        [7312,7354,1,-3008],
        [7357,7359,1,-3008],
        [7680,7828,2,1],
        [7838,7838,1,-7615],
        [7840,7934,2,1],
        [7944,7951,1,-8],
        [7960,7965,1,-8],
        [7976,7983,1,-8],
        [7992,7999,1,-8],
        [8008,8013,1,-8],
        [8025,8031,2,-8],
        [8040,8047,1,-8],
        [8072,8079,1,-8],
        [8088,8095,1,-8],
        [8104,8111,1,-8],
        [8120,8121,1,-8],
        [8122,8123,1,-74],
        [8124,8124,1,-9],
        [8136,8139,1,-86],
        [8140,8140,1,-9],
        [8152,8153,1,-8],
        [8154,8155,1,-100],
        [8168,8169,1,-8],
        [8170,8171,1,-112],
        [8172,8172,1,-7],
        [8184,8185,1,-128],
        [8186,8187,1,-126],
        [8188,8188,1,-9],
        [8486,8486,1,-7517],
        [8490,8490,1,-8383],
        [8491,8491,1,-8262],
        [8498,8498,1,28],
        [8544,8559,1,16],
        [8579,8579,1,1],
        [9398,9423,1,26],
        [11264,11311,1,48],
        [11360,11360,1,1],
        [11362,11362,1,-10743],
        [11363,11363,1,-3814],
        [11364,11364,1,-10727],
        [11367,11371,2,1],
        [11373,11373,1,-10780],
        [11374,11374,1,-10749],
        [11375,11375,1,-10783],
        [11376,11376,1,-10782],
        [11378,11381,3,1],
        [11390,11391,1,-10815],
        [11392,11490,2,1],
        [11499,11501,2,1],
        [11506,42560,31054,1],
        [42562,42604,2,1],
        [42624,42650,2,1],
        [42786,42798,2,1],
        [42802,42862,2,1],
        [42873,42875,2,1],
        [42877,42877,1,-35332],
        [42878,42886,2,1],
        [42891,42891,1,1],
        [42893,42893,1,-42280],
        [42896,42898,2,1],
        [42902,42920,2,1],
        [42922,42922,1,-42308],
        [42923,42923,1,-42319],
        [42924,42924,1,-42315],
        [42925,42925,1,-42305],
        [42926,42926,1,-42308],
        [42928,42928,1,-42258],
        [42929,42929,1,-42282],
        [42930,42930,1,-42261],
        [42931,42931,1,928],
        [42932,42946,2,1],
        [42948,42948,1,-48],
        [42949,42949,1,-42307],
        [42950,42950,1,-35384],
        [42951,42953,2,1],
        [42960,42966,6,1],
        [42968,42997,29,1],
        [65313,65338,1,32],
        [66560,66599,1,40],
        [66736,66771,1,40],
        [66928,66938,1,39],
        [66940,66954,1,39],
        [66956,66962,1,39],
        [66964,66965,1,39],
        [68736,68786,1,64],
        [71840,71871,1,32],
        [93760,93791,1,32],
        [125184,125217,1,34],
    ];

    /** One well-formed UTF-8 sequence (RFC 3629), or any single other byte. */
    private const SCRUB = '/(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})(*SKIP)(*FAIL)|[\x80-\xFF]/s';

    public static function isValid(string $text): bool
    {
        return preg_match('//u', $text) === 1;
    }

    /**
     * Lower-case a valid UTF-8 string (ASCII and the Unicode simple mapping).
     */
    public static function lower(string $text): string
    {
        $text = strtolower($text); // ASCII only since PHP 8.2, locale-independent

        if (preg_match('/[\x80-\xFF]/', $text) !== 1) {
            return $text;
        }

        return preg_replace_callback(
            '/[^\x00-\x7F]/u',
            static fn (array $m): string => self::lowerChar($m[0]),
            $text,
        ) ?? $text;
    }

    /**
     * The text with every byte that is not part of a well-formed sequence replaced by U+FFFD (one per
     * byte). Valid text is returned unchanged, so the result is always valid UTF-8.
     */
    public static function scrub(string $text): string
    {
        if (self::isValid($text)) {
            return $text;
        }

        return preg_replace(self::SCRUB, "\u{FFFD}", $text) ?? '';
    }

    /**
     * First $chars characters of the text; invalid bytes become U+FFFD first.
     */
    public static function truncate(string $text, int $chars): string
    {
        $text = self::scrub($text);

        return preg_match('/^.{0,'.max(0, $chars).'}/su', $text, $m) === 1 ? $m[0] : '';
    }

    private static function lowerChar(string $char): string
    {
        $cp = self::decode($char);

        if ($cp === 0x130) {
            return "i\u{0307}";
        }

        $lo = 0;
        $hi = count(self::LOWER) - 1;
        while ($lo <= $hi) {
            $mid = ($lo + $hi) >> 1;
            [$from, $to, $step, $delta] = self::LOWER[$mid];
            if ($cp < $from) {
                $hi = $mid - 1;
            } elseif ($cp > $to) {
                $lo = $mid + 1;
            } else {
                return ($cp - $from) % $step === 0 ? self::encode($cp + $delta) : $char;
            }
        }

        return $char;
    }

    private static function decode(string $char): int
    {
        $b = array_map('ord', str_split($char));

        return match (count($b)) {
            2 => (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F),
            3 => (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F),
            4 => (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F),
            default => $b[0],
        };
    }

    private static function encode(int $cp): string
    {
        return match (true) {
            $cp < 0x80 => chr($cp & 0x7F),
            $cp < 0x800 => chr(0xC0 | ($cp >> 6)).chr(0x80 | ($cp & 0x3F)),
            $cp < 0x10000 => chr(0xE0 | ($cp >> 12)).chr(0x80 | (($cp >> 6) & 0x3F)).chr(0x80 | ($cp & 0x3F)),
            default => chr(0xF0 | (($cp >> 18) & 0x07)).chr(0x80 | (($cp >> 12) & 0x3F)).chr(0x80 | (($cp >> 6) & 0x3F)).chr(0x80 | ($cp & 0x3F)),
        };
    }
}
