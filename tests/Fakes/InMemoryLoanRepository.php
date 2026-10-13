<?php

namespace Tests\Fakes;

use App\Application\Ports\LoanRepository;
use App\Application\Ports\OverdueLine;
use App\Application\Ports\ReportReader;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

final class InMemoryLoanRepository implements LoanRepository, ReportReader
{
    /** @var array<string, Loan> */
    public array $loans = [];

    /** Simulates another librarian taking the last copy between read and write (RNF-2). */
    public bool $simulateConcurrentWithdraw = false;

    public function __construct(private InMemoryMemberRepository $members)
    {
    }

    public function countUnreturnedItems(string $memberId): int
    {
        return count($this->unreturned($memberId));
    }

    public function memberHasOverdue(string $memberId, DateTimeImmutable $now): bool
    {
        foreach ($this->unreturned($memberId) as $item) {
            if ($item->isOverdue($now)) {
                return true;
            }
        }

        return false;
    }

    public function saveNew(Loan $loan, array $books): bool
    {
        if ($this->simulateConcurrentWithdraw) {
            return false;
        }
        $this->loans[$loan->id] = $loan;

        return true;
    }

    public function find(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    public function saveReturn(LoanItem $item, Book $book): void
    {
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        $lines = [];
        foreach ($this->loans as $loan) {
            foreach ($loan->items() as $item) {
                if ($item->isOverdue($now)) {
                    $lines[] = new OverdueLine($loan->id, $item->id, $item->bookTitle,
                        $this->members->find($loan->memberId)?->fullName ?? '', $item->dueAt);
                }
            }
        }

        return $lines;
    }

    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        $rows = [];
        foreach ($this->loans as $loan) {
            if ($loan->loanedAt < $from || $loan->loanedAt >= $toExclusive) {
                continue;
            }
            foreach ($loan->items() as $item) {
                $key = $item->bookId.'|'.$item->bookTitle.'|'.$item->categoryName;
                $rows[$key] ??= ['book_id' => $item->bookId, 'book_title' => $item->bookTitle, 'category_name' => $item->categoryName, 'times_borrowed' => 0];
                $rows[$key]['times_borrowed']++;
            }
        }

        return array_values($rows);
    }

    /** @return list<LoanItem> */
    private function unreturned(string $memberId): array
    {
        $items = [];
        foreach ($this->loans as $loan) {
            if ($loan->memberId === $memberId) {
                foreach ($loan->items() as $item) {
                    if (! $item->isReturned()) {
                        $items[] = $item;
                    }
                }
            }
        }

        return $items;
    }
}
