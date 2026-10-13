<?php

namespace Tests\Unit\Domain;

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsbnTest extends TestCase
{
    public function test_accepts_thirteen_digits(): void
    {
        $this->assertSame('9780307474728', (new Isbn('9780307474728'))->value);
    }

    /** @return array<string, array{string}> */
    public static function invalidIsbns(): array
    {
        return [
            'twelve digits' => ['978030747472'],
            'fourteen digits' => ['97803074747281'],
            'letters' => ['978030747472X'],
            'hyphens' => ['978-0307474728'],
            'spaces' => [' 9780307474728'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidIsbns')]
    public function test_rejects_anything_but_thirteen_digits(string $value): void
    {
        $this->expectException(InvalidIsbn::class);
        new Isbn($value);
    }

    public function test_equality_by_value(): void
    {
        $this->assertTrue((new Isbn('9780307474728'))->equals(new Isbn('9780307474728')));
        $this->assertFalse((new Isbn('9780307474728'))->equals(new Isbn('9780000000009')));
    }
}
