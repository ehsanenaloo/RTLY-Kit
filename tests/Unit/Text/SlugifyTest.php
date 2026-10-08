<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use RtlyKit\Text\Slugify;

final class SlugifyTest extends TestCase
{
    public function test_slugify(): void
    {
        $slug = Slugify::make('سلام دنیا');
        $this->assertStringContainsString('سلام', $slug);
        $this->assertStringNotContainsString(' ', $slug);
    }

    public function test_slugify_separators_and_mixed_scripts(): void
    {
        $this->assertSame('سلام-دنیا', Slugify::make('سلام دنیا'));
        $this->assertSame('hello-world_foo-bar', Slugify::make('Hello  World_foo-bar!'));
        $this->assertSame('a-b', Slugify::make('a - b'));
        $this->assertSame('می-روم', Slugify::make("می\u{200C}روم"));
        $this->assertSame('a_b', Slugify::make('a b', '_'));
        $this->assertSame('کتاب-۱۲۳', Slugify::make('كتاب ١٢٣'));
        $this->assertSame('', Slugify::make('  !!!  '));
    }
}
