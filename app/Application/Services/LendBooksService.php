<?php

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryReader;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Domain\BookNotAvailable;
use App\Domain\DuplicateBookInLoan;
use App\Domain\Loan;

/** HU-06: register a loan. Lends and does nothing else (Article IV, SRP). */
final readonly class LendBooksService
{
    public function __construct(
        private MemberRepository $members,
        private BookRepository $books,
        private CategoryReader $categories,
        private LoanRepository $loans,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /** @param list<string> $bookIds */
    public function lend(string $memberId, string $loanedByUserId, array $bookIds): Loan
    {
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new DuplicateBookInLoan('No se puede repetir el mismo libro en un préstamo.');
        }
        if ($this->members->find($memberId) === null) {
            throw new NotFound('El socio no existe.');
        }
        $now = $this->clock->now();
        if ($this->loans->memberHasOverdue($memberId, $now)) {
            throw new Conflict('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y no puede pedir otro.');
        }
        $current = $this->loans->countUnreturnedItems($memberId);
        if ($current + count($bookIds) > Loan::MAX_ITEMS) {
            throw new MemberLimitExceeded($current);
        }

        $lines = [];
        $books = [];
        foreach ($bookIds as $bookId) {
            $book = $this->books->findActive($bookId) ?? throw new NotFound('El libro no existe.');
            $lines[] = ['itemId' => $this->ids->next(), 'book' => $book, 'categoryName' => (string) $this->categories->nameOf($book->categoryId())];
            $books[] = $book;
        }

        try {
            $loan = Loan::lend($this->ids->next(), $memberId, $loanedByUserId, $now, $lines);
        } catch (BookNotAvailable $e) {
            throw new Conflict('BOOK_NOT_AVAILABLE', $e->getMessage());
        }
        if (! $this->loans->saveNew($loan, $books)) {
            throw new Conflict('BOOK_NOT_AVAILABLE', 'Otro préstamo tomó el último ejemplar; no se registró nada.');
        }

        return $loan;
    }
}
