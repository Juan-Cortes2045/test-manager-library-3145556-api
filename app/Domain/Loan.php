<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * Loan aggregate (architecture.md §3): one member, 1 to 3 items, no repeated book, due at loaned_at + 14 days,
 * immutable except for returning an item. Withdrawing the copy and creating the item are one operation.
 */
final class Loan
{
    public const MAX_ITEMS = 3;
    public const LOAN_DAYS = 14;

    /** @param list<LoanItem> $items */
    private function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
        private array $items,
    ) {
    }

    /**
     * Registers a loan (CA-06.1, 06.2, 06.3, 06.6). Each line withdraws one copy from its Book.
     *
     * @param  list<array{itemId: string, book: Book, categoryName: string}>  $lines
     */
    public static function lend(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt, array $lines): self
    {
        if (count($lines) < 1 || count($lines) > self::MAX_ITEMS) {
            throw new DomainRuleViolated('Un préstamo debe tener entre 1 y 3 libros.');
        }
        $bookIds = array_map(fn (array $line) => $line['book']->id, $lines);
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new DuplicateBookInLoan('No se puede repetir el mismo libro en un préstamo.');
        }

        $loan = new self($id, $memberId, $loanedByUserId, $loanedAt, []);
        foreach ($lines as $line) {
            $loan->addItem($line['itemId'], $line['book'], $line['categoryName']);
        }

        return $loan;
    }

    /** @param list<LoanItem> $items */
    public static function restore(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt, array $items): self
    {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, $items);
    }

    private function addItem(string $itemId, Book $book, string $categoryName): void
    {
        $book->withdraw();
        $this->items[] = new LoanItem(
            $itemId,
            $book->id,
            $book->title(),
            $book->isbn()->value,
            $categoryName,
            $this->loanedAt->modify('+'.self::LOAN_DAYS.' days'),
        );
    }

    /** Returns one item (CA-07.1, 07.2): sets returned_at and restocks the book exactly once. */
    public function returnItem(string $itemId, Book $book, DateTimeImmutable $at): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                if ($item->bookId !== $book->id) {
                    throw new DomainRuleViolated('El libro no corresponde a la línea del préstamo.');
                }
                $item->markReturned($at);
                $book->restock();

                return $item;
            }
        }
        throw new DomainRuleViolated('La línea no pertenece a este préstamo.');
    }

    /** Computed status (CA-08.1, CA-07.4, api-contract.md §3.6). */
    public function status(DateTimeImmutable $now): LoanStatus
    {
        $pending = array_filter($this->items, fn (LoanItem $item) => ! $item->isReturned());
        if ($pending === []) {
            return LoanStatus::Closed;
        }
        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
        }

        return LoanStatus::Open;
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }
}
