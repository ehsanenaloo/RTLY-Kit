<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Text\Normalizer;

final class NormalizerTest extends TestCase
{
    public function test_normalizer(): void
    {
        $this->assertSame('علی', Normalizer::normalize('علي'));
        $this->assertSame('کتاب', Normalizer::normalize('كتاب'));
        $this->assertSame('سلام دنیا', Normalizer::clean('  سلام   دنیا  '));
    }

    public function test_normalize_tatweel_diacritics_hamza_and_digits(): void
    {
        $this->assertSame('کتاب', Normalizer::normalize("\u{0643}\u{0640}\u{0640}\u{062A}\u{0627}\u{0628}")); // tatweel
        $this->assertSame('محمد', Normalizer::normalize("\u{0645}\u{064F}\u{062D}\u{064E}\u{0645}\u{0651}\u{064E}\u{062F}"));
        $this->assertSame("\u{0645}\u{064F}\u{062D}", Normalizer::normalize("\u{0645}\u{064F}\u{062D}", false));
        $this->assertSame('۱۲۳ 456', Normalizer::normalize('١٢٣ 456'));
        $this->assertSame('مسئله', Normalizer::normalize('مسئله'));  // hamza-on-yeh kept
        $this->assertSame('جزء', Normalizer::normalize('جزء'));      // standalone hamza kept
        $this->assertSame('مدرسه', Normalizer::normalize('مدرسة'));
        $this->assertSame('احمد', Normalizer::normalize('أحمد'));
    }

    public function test_fix_half_space(): void
    {
        $zwnj = "\u{200C}";
        $this->assertSame("می{$zwnj}روم", Normalizer::fixHalfSpace("می{$zwnj}{$zwnj}روم"));
        $this->assertSame('می روم', Normalizer::fixHalfSpace("می{$zwnj} روم"));
        $this->assertSame('ab', Normalizer::fixHalfSpace("a\u{00AD}b"));
        $this->assertSame("می{$zwnj}روم", Normalizer::clean("  می{$zwnj}روم\u{200B}  "));
    }

    public function test_fix_half_space_keeps_the_last_letter_of_the_text(): void
    {
        $zwnj = "\u{200C}";
        // ی (DB 8C) and the Arabic comma (D8 8C) end in the byte 8C that trim() would cut.
        foreach (['سلامی', 'علی', 'ب،'] as $text) {
            $this->assertSame($text, Normalizer::fixHalfSpace($text));
            $this->assertSame($text, Normalizer::clean($text));
        }
        $this->assertSame('می', Normalizer::fixHalfSpace("{$zwnj}می{$zwnj}"));
        $this->assertSame('علی', Normalizer::clean("  {$zwnj}علی{$zwnj}  "));
    }
}
