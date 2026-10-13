<?php

namespace Tests\Unit\Domain;

use App\Application\Ports\Clock;
use Tests\Fakes\FixedClock;
use App\Domain\Book;
use App\Domain\BookNotAvailable;
use App\Domain\DomainRuleViolated;
use App\Domain\DuplicateBookInLoan;
use App\Domain\ItemAlreadyReturned;
use App\Domain\Isbn;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use App\Domain\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    private function book(string $id, int $copies = 1, string $title = 'Cien años de soledad'): Book
    {
        return Book::create($id, $title, 'Autor', new Isbn('9780307474728'), 'cat', new Quantity($copies));
    }

    private function lend(Clock $clock, Book ...$books): Loan
    {
        $lines = [];
        foreach ($books as $i => $book) {
            $lines[] = ['itemId' => "item-$i", 'book' => $book, 'categoryName' => 'Novela'];
        }

        return Loan::lend('loan-1', 'member-1', 'user-1', $clock->now(), $lines);
    }

    public function test_due_date_is_fourteen_days_and_values_are_frozen(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-10-14T14:15:00Z'));
        $book = $this->book('b1', 2);
        $loan = $this->lend($clock, $book);
        $item = $loan->items()[0];

        $this->assertEquals(new DateTimeImmutable('2026-10-28T14:15:00Z'), $item->dueAt);
        $this->assertSame('Cien años de soledad', $item->bookTitle);
        $this->assertSame('9780307474728', $item->isbn);
        $this->assertSame('Novela', $item->categoryName);
        $this->assertSame(1, $book->availableCopies(), 'lending withdraws one copy in the same operation');
    }

    public function test_status_is_computed_open_overdue_closed(): void
    {
        $start = new DateTimeImmutable('2026-10-01T10:00:00Z');
        $book = $this->book('b1');
        $loan = $this->lend(new FixedClock($start), $book);

        $this->assertSame(LoanStatus::Open, $loan->status(new DateTimeImmutable('2026-10-15T10:00:00Z')));
        $late = new DateTimeImmutable('2026-10-17T10:00:00Z');
        $this->assertSame(LoanStatus::Overdue, $loan->status($late));
        $this->assertSame(2, $loan->items()[0]->daysLate($late));

        $loan->returnItem('item-0', $book, $late);
        $this->assertSame(LoanStatus::Closed, $loan->status($late));
        $this->assertSame(1, $book->availableCopies());
    }

    public function test_one_to_three_items(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-10-13T00:00:00Z'));
        $this->expectException(DomainRuleViolated::class);
        $this->lend($clock, $this->book('b1'), $this->book('b2'), $this->book('b3'), $this->book('b4'));
    }

    public function test_zero_items_is_rejected(): void
    {
        $this->expectException(DomainRuleViolated::class);
        Loan::lend('loan', 'm', 'u', new DateTimeImmutable('2026-10-13T00:00:00Z'), []);
    }

    public function test_same_book_twice_is_rejected(): void
    {
        $book = $this->book('b1', 3);
        $this->expectException(DuplicateBookInLoan::class);
        try {
            $this->lend(new FixedClock(new DateTimeImmutable('2026-10-13T00:00:00Z')), $book, $book);
        } finally {
            $this->assertSame(3, $book->availableCopies(), 'nothing is withdrawn when the loan is rejected');
        }
    }

    public function test_no_copies_left_is_rejected(): void
    {
        $book = $this->book('b1', 1);
        $book->withdraw();
        $this->expectException(BookNotAvailable::class);
        $this->lend(new FixedClock(new DateTimeImmutable('2026-10-13T00:00:00Z')), $book);
    }

    public function test_returning_twice_never_adds_twice(): void
    {
        $book = $this->book('b1', 1);
        $loan = $this->lend(new FixedClock(new DateTimeImmutable('2026-10-13T00:00:00Z')), $book);
        $loan->returnItem('item-0', $book, new DateTimeImmutable('2026-10-14T00:00:00Z'));
        try {
            $loan->returnItem('item-0', $book, new DateTimeImmutable('2026-10-15T00:00:00Z'));
            $this->fail('second return must fail');
        } catch (ItemAlreadyReturned) {
            $this->assertSame(1, $book->availableCopies());
        }
    }
}
