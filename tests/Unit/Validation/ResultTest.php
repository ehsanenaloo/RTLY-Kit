<?php

declare(strict_types=1);

namespace RtlyKit\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use RtlyKit\Validation\Result;

final class ResultTest extends TestCase
{
    public function test_result_value_object(): void
    {
        $r = Result::invalid(['a', 'b'], ['x' => 1]);
        $this->assertFalse($r->isValid());
        $this->assertSame(['a', 'b'], $r->errors());
        $this->assertSame(['x' => 1], $r->details());
        $this->assertTrue(Result::valid()->isValid());
    }
}
