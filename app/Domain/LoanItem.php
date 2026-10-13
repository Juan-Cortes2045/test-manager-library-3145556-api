<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * One book inside a loan. Title, ISBN and category name are frozen at lending time (CA-06.6, Article VIII).
 */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }

    /** Started 24-hour periods since due_at (interpretation I-06); 0 when not overdue. */
    public function daysLate(DateTimeImmutable $now): int
    {
        if (! $this->isOverdue($now)) {
            return 0;
        }

        return (int) ceil(($now->getTimestamp() - $this->dueAt->getTimestamp()) / 86400);
    }

    public function markReturned(DateTimeImmutable $at): void
    {
        if ($this->isReturned()) {
            throw new ItemAlreadyReturned('Esta línea del préstamo ya fue devuelta.');
        }
        $this->returnedAt = $at;
    }
}
