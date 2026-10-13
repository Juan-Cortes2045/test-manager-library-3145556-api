<?php

namespace App\Application\Ports;

use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

interface LoanRepository
{
    /** Unreturned items of a member (Q7): the number checked against the limit of 3 (CA-06.4, I-07). */
    public function countUnreturnedItems(string $memberId): int;

    /** Q7: does the member have an unreturned item with due_at before $now (CA-06.5)? */
    public function memberHasOverdue(string $memberId, DateTimeImmutable $now): bool;

    /**
     * Persists a new loan and withdraws each copy in ONE transaction, with a conditional update per book
     * (ADR-002, I-01). Returns false if any copy was taken meanwhile; then nothing is persisted (CA-06.3).
     *
     * @param  list<Book>  $books
     */
    public function saveNew(Loan $loan, array $books): bool;

    public function find(string $id): ?Loan;

    /** Sets returned_at and restocks the book in one transaction (CA-07.1). */
    public function saveReturn(LoanItem $item, Book $book): void;

    /** @return list<OverdueLine> Q8: unreturned items with due_at before $now. */
    public function overdueLines(DateTimeImmutable $now): array;
}
