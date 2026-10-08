<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Exceptions\ErrorCode;
use RtlyKit\Exceptions\InvalidDateException;
use RtlyKit\Exceptions\RtlyKitException;
use RtlyKit\Text\Detector;
use RtlyKit\Text\Normalizer;
use RtlyKit\Text\Slugify;

/**
 * Known-answer boundary cases for Slugify, Detector, Normalizer and the exception base class.
 */
final class TextBoundaryTest extends TestCase
{
    public function test_slugify_separator_length_cap_is_64_bytes(): void
    {
        $this->assertSame('a'.str_repeat('x', 64).'b', Slugify::make('a b', str_repeat('x', 64)));

        try {
            Slugify::make('a b', str_repeat('x', 65));
            $this->fail('expected an exception');
        } catch (RtlyKitException $e) {
            $this->assertSame(ErrorCode::InputTooLong, $e->getErrorCode());
            $this->assertSame(['argument' => 'separator', 'limit' => 64], $e->getContext());
        }
    }

    public function test_slugify_collapses_hyphen_and_underscore_runs_for_other_separators(): void
    {
        $this->assertSame('a-b', Slugify::make('a--b', '_'));
        $this->assertSame('a_b', Slugify::make('a__b', '-'));
        $this->assertSame('a_b', Slugify::make('a____b', '.'));
        $this->assertSame('a-b', Slugify::make('a-----b', '.'));
        $this->assertSame('a.b', Slugify::make('a ! b', '.'));
    }

    public function test_slugify_trims_leading_and_trailing_separators(): void
    {
        $this->assertSame('a-b', Slugify::make('  a b  '));
        $this->assertSame('a', Slugify::make('-a-'));
        $this->assertSame('a', Slugify::make('  a  ', '--'));
        $this->assertSame('a--b', Slugify::make(' a b ', '--'));
        $this->assertSame('a.b', Slugify::make('...a b...', '.'));
    }

    public function test_slugify_known_answers(): void
    {
        $this->assertSame('hello-world', Slugify::make('Hello,   World!'));
        $this->assertSame('سلام-دنیا', Slugify::make('سلام   دنیا'));
        $this->assertSame('می-خواهم', Slugify::make("می\u{200C}خواهم"));
        $this->assertSame('a_b', Slugify::make('a b', '_'));
        $this->assertSame('ab', Slugify::make('a b', ''));
    }

    public function test_detector_persian_requires_arabic_script(): void
    {
        $this->assertFalse(Detector::isPersian('hello'));
        $this->assertFalse(Detector::isPersian(''));
        $this->assertFalse(Detector::isPersian('12345'));
        $this->assertTrue(Detector::isPersian('سلام'));
        $this->assertTrue(Detector::isPersian('چطوری'));
        $this->assertFalse(Detector::isArabic('hello'));
        $this->assertTrue(Detector::isArabic('كتاب'));
        $this->assertFalse(Detector::isArabic('گچپژ'));
    }

    public function test_detector_locale_only_checks_script_subtags_after_the_language(): void
    {
        $this->assertTrue(Detector::isRtlLocale(' fa '));
        $this->assertTrue(Detector::isRtlLocale("\tar_SA\n"));
        $this->assertTrue(Detector::isRtlLocale('ku-Arab'));
        $this->assertTrue(Detector::isRtlLocale('ckb-IQ'));
        $this->assertFalse(Detector::isRtlLocale('en'));
        $this->assertFalse(Detector::isRtlLocale('en-US'));
        $this->assertFalse(Detector::isRtlLocale('en-Latn'));
        // A script tag in the language position is not a language.
        $this->assertFalse(Detector::isRtlLocale('arab'));
        $this->assertFalse(Detector::isRtlLocale('hebr'));
        $this->assertFalse(Detector::isRtlLocale(''));
        $this->assertFalse(Detector::isRtlLocale('--'));
    }

    public function test_normalizer_trims(): void
    {
        $this->assertSame('x', Normalizer::normalize("  x \n"));
        $this->assertSame('x', Normalizer::normalize("\u{00A0}x\u{00A0}"));
        $this->assertSame('x', Normalizer::fixHalfSpace("\u{200C}x\u{200C}"));
        $this->assertSame('x', Normalizer::fixHalfSpace("\u{200C}\u{200C}x"));
        $this->assertSame('x', Normalizer::clean("x \u{200B}"));
        $this->assertSame('x', Normalizer::clean("\u{FEFF} x"));
    }

    public function test_exception_named_constructor_has_code_zero_and_default_error_codes(): void
    {
        $e = InvalidDateException::because(ErrorCode::DateOutOfRange, 'm', ['k' => 1]);
        $this->assertSame(0, $e->getCode());
        $this->assertSame(ErrorCode::DateOutOfRange, $e->getErrorCode());
        $this->assertSame(['k' => 1], $e->getContext());

        $this->assertSame(ErrorCode::InvalidArgument, (new RtlyKitException('x'))->getErrorCode());
        // Subclasses override the protected default; the base class must be able to call it.
        $this->assertNotSame(ErrorCode::InvalidArgument, (new InvalidDateException('x'))->getErrorCode());
        $this->assertSame(0, (new RtlyKitException('x'))->getCode());
    }
}
