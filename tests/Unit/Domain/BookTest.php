<?php

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\BookNotAvailable;
use App\Domain\DomainRuleViolated;
use App\Domain\InvalidAvailableCopies;
use App\Domain\Isbn;
use App\Domain\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookTest extends TestCase
{
    private function book(int $total = 2): Book
    {
        return Book::create('a0000000-0000-4000-8000-000000000001', '  Cien años de soledad ', 'Gabriel García Márquez',
            new Isbn('9780307474728'), '11111111-1111-4111-8111-111111111111', new Quantity($total));
    }

    public function test_new_book_starts_with_available_equal_to_total_and_trimmed_title(): void
    {
        $book = $this->book(3);
        $this->assertSame(3, $book->availableCopies());
        $this->assertSame(3, $book->totalCopies());
        $this->assertSame('Cien años de soledad', $book->title());
        $this->assertNull($book->coverKey());
        $this->assertTrue($book->isActive());
    }

    public function test_withdraw_never_goes_below_zero(): void
    {
        $book = $this->book(1);
        $book->withdraw();
        $this->assertSame(0, $book->availableCopies());

        $this->expectException(BookNotAvailable::class);
        $book->withdraw();
    }

    public function test_restock_never_goes_above_total(): void
    {
        $book = $this->book(2);
        $book->withdraw();
        $book->restock();
        $this->assertSame(2, $book->availableCopies());

        $this->expectException(InvalidAvailableCopies::class);
        $book->restock();
    }

    public function test_soft_delete_keeps_the_first_instant(): void
    {
        $book = $this->book();
        $first = new DateTimeImmutable('2026-10-13T16:00:00Z');
        $book->softDelete($first);
        $book->softDelete(new DateTimeImmutable('2026-10-14T00:00:00Z'));
        $this->assertFalse($book->isActive());
        $this->assertEquals($first, $book->deletedAt());
    }

    public function test_empty_title_is_rejected(): void
    {
        $this->expectException(DomainRuleViolated::class);
        Book::create('id', '   ', 'Autor', new Isbn('9780307474728'), 'cat', new Quantity(1));
    }

    public function test_restore_rejects_available_out_of_range(): void
    {
        $this->expectException(InvalidAvailableCopies::class);
        Book::restore('id', 'T', 'A', new Isbn('9780307474728'), 'cat', new Quantity(2), 3, null, null);
    }

    public function test_restore_rejects_negative_available(): void
    {
        $this->expectException(InvalidAvailableCopies::class);
        Book::restore('id', 'T', 'A', new Isbn('9780307474728'), 'cat', new Quantity(2), -1, null, null);
    }

    public function test_restore_rejects_empty_cover_key(): void
    {
        $this->expectException(DomainRuleViolated::class);
        Book::restore('id', 'T', 'A', new Isbn('9780307474728'), 'cat', new Quantity(1), 1, '', null);
    }
}
