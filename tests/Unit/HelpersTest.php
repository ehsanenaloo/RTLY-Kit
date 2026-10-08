<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RtlyKit\Calendar\Hebrew;
use RtlyKit\Calendar\Hijri;
use RtlyKit\Calendar\Jalali;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\InvalidNumberException;
use RtlyKit\Exceptions\InvalidPrayerConfigException;
use RtlyKit\Exceptions\UnsupportedLocaleException;
use RtlyKit\Validation\Result;

use function RtlyKit\contains_rtl;
use function RtlyKit\format_number;
use function RtlyKit\hdate;
use function RtlyKit\hebrew_date;
use function RtlyKit\is_bank_card;
use function RtlyKit\is_iran_holiday;
use function RtlyKit\is_mobile;
use function RtlyKit\is_national_code;
use function RtlyKit\is_postal_code;
use function RtlyKit\is_sheba;
use function RtlyKit\is_vehicle_plate;
use function RtlyKit\jdate;
use function RtlyKit\normalize_text;
use function RtlyKit\number_to_words;
use function RtlyKit\ordinal;
use function RtlyKit\prayer_times;
use function RtlyKit\text_direction;
use function RtlyKit\to_english;
use function RtlyKit\to_english_digits;
use function RtlyKit\to_persian;
use function RtlyKit\to_persian_digits;
use function RtlyKit\validate_bank_card;
use function RtlyKit\validate_mobile;
use function RtlyKit\validate_national_code;
use function RtlyKit\validate_postal_code;
use function RtlyKit\validate_sheba;
use function RtlyKit\validate_vehicle_plate;

/**
 * One behavioural test per namespaced helper in src/helpers.php (known answers only).
 */
final class HelpersTest extends TestCase
{
    public function test_jdate_helper_basic(): void
    {
        $this->assertInstanceOf(Jalali::class, jdate());
    }

    public function test_jdate_converts_gregorian_string_to_nowruz_1403(): void
    {
        $j = jdate('2024-03-20 10:00:00', $this->utc());

        $this->assertInstanceOf(Jalali::class, $j);
        $this->assertSame('1403/01/01', $j->toDateString());
        $this->assertSame('1403/01/01 10:00:00', $j->toDateTimeString());
    }

    public function test_jdate_accepts_datetime_and_timestamp_and_jalali_string(): void
    {
        $this->assertSame('1403/01/01', jdate(new DateTimeImmutable('2024-03-20 12:00', $this->utc()))->toDateString());
        $this->assertSame('1403/01/01', jdate(1710925200, $this->utc())->toDateString()); // 2024-03-20 09:00 UTC
        $this->assertSame('1403/05/09', jdate('1403/05/09', $this->utc())->toDateString());
        $this->assertInstanceOf(Jalali::class, jdate());
    }

    public function test_jdate_rejects_garbage_with_library_exception(): void
    {
        $this->expectException(InvalidDateException::class);
        jdate('not a date');
    }

    public function test_hdate_converts_to_ummalqura_ramadan_1445(): void
    {
        $h = hdate('2024-03-20 10:00:00', $this->utc());

        $this->assertInstanceOf(Hijri::class, $h);
        $this->assertSame('1445/09/10', $h->toDateString());
        $this->assertInstanceOf(Hijri::class, hdate());
    }

    public function test_hebrew_date_converts_to_10_adar_ii_5784(): void
    {
        $h = hebrew_date('2024-03-20 10:00:00', $this->utc());

        $this->assertInstanceOf(Hebrew::class, $h);
        $this->assertSame(5784, $h->getYear());
        $this->assertSame(10, $h->getDay());
        $this->assertSame('Adar II', $h->format('F'));
        $this->assertInstanceOf(Hebrew::class, hebrew_date());
    }

    public function test_to_persian_digits_and_alias(): void
    {
        $this->assertSame('۱۴۰۳/۰۱/۰۱', to_persian_digits('1403/01/01'));
        $this->assertSame('۱۲۳', to_persian_digits(123));
        $this->assertSame(to_persian_digits('0123456789'), to_persian('0123456789'));
        $this->assertSame('۰۱۲۳۴۵۶۷۸۹', to_persian('0123456789'));
        $this->assertSame('abc', to_persian('abc'));
    }

    public function test_to_english_digits_and_alias(): void
    {
        $this->assertSame('0123456789', to_english_digits('۰۱۲۳۴۵۶۷۸۹'));
        $this->assertSame('0123456789', to_english_digits('٠١٢٣٤٥٦٧٨٩'));
        $this->assertSame('1403/01/01', to_english('۱۴۰۳/۰۱/۰۱'));
        $this->assertSame('a1b2', to_english('a۱b٢'));
    }

    public function test_is_national_code(): void
    {
        $this->assertTrue(is_national_code('0499370899'));
        $this->assertTrue(is_national_code('۰۴۹۹۳۷۰۸۹۹'));
        $this->assertFalse(is_national_code('0499370898')); // bad checksum
        $this->assertFalse(is_national_code('1111111111')); // repeated digits
        $this->assertFalse(is_national_code('123'));
    }

    public function test_is_sheba(): void
    {
        $this->assertTrue(is_sheba('IR062960000000100324200001'));
        $this->assertFalse(is_sheba('IR062960000000100324200002'));
        $this->assertFalse(is_sheba(''));
    }

    public function test_is_bank_card(): void
    {
        $this->assertTrue(is_bank_card('6037991899071116'));
        $this->assertFalse(is_bank_card('6037991899071111'));
        $this->assertFalse(is_bank_card('6037'));
    }

    public function test_is_mobile(): void
    {
        $this->assertTrue(is_mobile('09123456789'));
        $this->assertTrue(is_mobile('+989123456789'));
        $this->assertFalse(is_mobile('0912345678'));
        $this->assertFalse(is_mobile('hello'));
    }

    public function test_is_postal_code(): void
    {
        $this->assertTrue(is_postal_code('1593715416'));
        $this->assertFalse(is_postal_code('0593715416')); // never starts with 0
        $this->assertFalse(is_postal_code('12345'));
    }

    public function test_is_vehicle_plate(): void
    {
        $this->assertTrue(is_vehicle_plate('12ب345-67'));
        $this->assertFalse(is_vehicle_plate('12-345-67'));
        $this->assertFalse(is_vehicle_plate('ab'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function validatorProvider(): array
    {
        return [
            'national_code' => ['RtlyKit\validate_national_code', '0499370899', '0499370898', 'invalid_checksum'],
            'sheba' => ['RtlyKit\validate_sheba', 'IR062960000000100324200001', 'IR062960000000100324200002', 'invalid_checksum'],
            'bank_card' => ['RtlyKit\validate_bank_card', '6037991899071116', '6037991899071111', 'invalid_checksum'],
            'mobile' => ['RtlyKit\validate_mobile', '09123456789', '0912345678', 'invalid_format'],
            'vehicle_plate' => ['RtlyKit\validate_vehicle_plate', '12ب345-67', '12-345-67', 'invalid_format'],
        ];
    }

    #[DataProvider('validatorProvider')]
    public function test_validate_helpers_return_structured_result(string $fn, string $good, string $bad, string $code): void
    {
        /** @var Result $ok */
        $ok = $fn($good);
        $this->assertInstanceOf(Result::class, $ok);
        $this->assertTrue($ok->isValid());
        $this->assertSame([], $ok->errors());

        /** @var Result $no */
        $no = $fn($bad);
        $this->assertFalse($no->isValid());
        $this->assertContains($code, $no->errors());
    }

    public function test_validate_helpers_expose_details(): void
    {
        $this->assertSame('تهران', validate_national_code('0499370899')->details()['location']['province']);
        $this->assertSame('296', validate_sheba('IR062960000000100324200001')->details()['bank_code']);
        $this->assertSame('بانک ملی ایران', validate_bank_card('6037991899071116')->details()['bank_name']);
        $this->assertSame('09123456789', validate_mobile('+989123456789')->details()['normalized']);
        $this->assertSame('ب', validate_vehicle_plate('12ب345-67')->details()['letter']);
    }

    public function test_validate_postal_code(): void
    {
        $ok = validate_postal_code('1593715416');
        $this->assertTrue($ok->isValid());
        $this->assertSame('1593715416', $ok->details()['normalized']);

        $bad = validate_postal_code('0593715416');
        $this->assertFalse($bad->isValid());
        $this->assertNotSame([], $bad->errors());
    }

    public function test_number_to_words_persian_default(): void
    {
        $this->assertSame('صفر', number_to_words(0));
        $this->assertSame('بیست و سه', number_to_words(23));
        $this->assertSame('منفی پنج', number_to_words(-5));
        $this->assertSame('یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت', number_to_words(1234567));
        $this->assertSame('بیست و سه', number_to_words('۲۳'));
    }

    public function test_number_to_words_arabic_and_unsupported_locale(): void
    {
        $this->assertSame('ثلاثة وعشرون', number_to_words(23, 'ar'));
        $this->assertSame('سالب خمسة', number_to_words(-5, 'ar'));

        $this->expectException(UnsupportedLocaleException::class);
        number_to_words(5, 'xx');
    }

    public function test_number_to_words_arabic_rejects_values_beyond_billion(): void
    {
        $this->expectException(InvalidNumberException::class);
        number_to_words('1000000000', 'ar');
    }

    public function test_normalize_text_unifies_arabic_letters_and_digits(): void
    {
        $this->assertSame('کیف ۱۲۳', normalize_text('كيف ١٢٣'));
        $this->assertSame('کیف 123', normalize_text('كيف 123')); // ASCII digits are left alone
        $this->assertSame('', normalize_text(''));
    }

    public function test_contains_rtl(): void
    {
        $this->assertTrue(contains_rtl('hello سلام'));
        $this->assertTrue(contains_rtl('שלום'));
        $this->assertFalse(contains_rtl('hello 123'));
        $this->assertFalse(contains_rtl(''));
    }

    public function test_text_direction(): void
    {
        $this->assertSame('rtl', text_direction('سلام دنیا'));
        $this->assertSame('ltr', text_direction('hello world'));
        $this->assertSame('ltr', text_direction(''));
    }

    public function test_is_iran_holiday_with_ints_and_jalali(): void
    {
        $this->assertTrue(is_iran_holiday(1403, 1, 1));   // Nowruz
        $this->assertTrue(is_iran_holiday(1403, 11, 22)); // 22 Bahman
        $this->assertFalse(is_iran_holiday(1403, 1, 5));
        $this->assertTrue(is_iran_holiday(jdate('2024-03-20 10:00:00', $this->utc())));
        $this->assertFalse(is_iran_holiday(Jalali::create(1403, 1, 5)));
    }

    public function test_prayer_times_default_city_has_ordered_valid_times(): void
    {
        $t = prayer_times();

        $this->assertSame(['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'], array_keys($t));
        foreach ($t as $name => $value) {
            $this->assertMatchesRegularExpression('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value, $name);
        }
        $this->assertTrue(
            $t['fajr'] < $t['sunrise'] && $t['sunrise'] < $t['dhuhr'] && $t['dhuhr'] < $t['asr']
            && $t['asr'] < $t['maghrib'] && $t['maghrib'] < $t['isha'],
            'prayer times must be chronologically ordered',
        );
    }

    public function test_prayer_times_method_changes_result_and_unknown_city_throws(): void
    {
        $this->assertNotSame(prayer_times('mecca', 'MWL')['fajr'], prayer_times('mecca', 'Egypt')['fajr']);

        $this->expectException(InvalidPrayerConfigException::class);
        prayer_times('atlantis');
    }

    public function test_format_number(): void
    {
        $this->assertSame('۱٬۲۳۴٬۵۶۷', format_number(1234567));
        $this->assertSame('۱٬۲۳۴٬۵۶۷٫۵', format_number(1234567.5));
        $this->assertSame('۱٬۰۰۰', format_number('1000'));
        $this->assertSame('۰', format_number(0));
    }

    public function test_ordinal(): void
    {
        $this->assertSame('اول', ordinal(1));
        $this->assertSame('دوم', ordinal(2));
        $this->assertSame('سوم', ordinal(3));
        $this->assertSame('بیست و سوم', ordinal(23));
        $this->assertSame("سی\u{200C}ام", ordinal(30));
        $this->assertSame('سوم', ordinal(3.0));
    }

    public function test_ordinal_rejects_negative_and_fractional(): void
    {
        try {
            ordinal(-1);
            $this->fail('negative ordinal must throw');
        } catch (InvalidNumberException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidNumberException::class);
        ordinal(2.5);
    }

    public function test_helper(): void
    {
        $this->assertTrue(is_national_code('0013542419'));
        $this->assertFalse(is_national_code('0000000000'));
    }

    public function test_text_and_number_helpers_known_answers(): void
    {
        $this->assertSame('۱۲۳', to_persian_digits(123));
        $this->assertSame('123', to_english_digits('۱۲۳'));
        $this->assertNotEmpty(number_to_words(42));
        $this->assertSame('علی', normalize_text('علي'));
        $this->assertTrue(contains_rtl('سلام'));
        $this->assertSame('rtl', text_direction('سلام'));
    }

    public function test_validate_helpers_return_valid_results(): void
    {
        $this->assertTrue(validate_national_code('0013542419')->isValid());
        $this->assertTrue(validate_sheba('IR270170000000100324200001')->isValid());
        $this->assertTrue(validate_bank_card('6037991234567893')->isValid());
        $this->assertTrue(validate_mobile('+989121234567')->isValid());
    }

    private function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
