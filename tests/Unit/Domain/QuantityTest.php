<?php

namespace Tests\Unit\Domain;

use App\Domain\InvalidQuantity;
use App\Domain\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_accepts_one_or_more(): void
    {
        $this->assertSame(1, (new Quantity(1))->value);
        $this->assertSame(4, (new Quantity(4))->value);
    }

    public function test_rejects_zero(): void
    {
        $this->expectException(InvalidQuantity::class);
        new Quantity(0);
    }

    public function test_rejects_negative(): void
    {
        $this->expectException(InvalidQuantity::class);
        new Quantity(-3);
    }
}
