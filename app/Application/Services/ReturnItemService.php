<?php

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Domain\ItemAlreadyReturned;
use App\Domain\LoanItem;

/** HU-07: return one item; never adds a copy twice (CA-07.2). */
final readonly class ReturnItemService
{
    public function __construct(
        private LoanRepository $loans,
        private BookRepository $books,
        private Clock $clock,
    ) {
    }

    public function return(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->find($loanId) ?? throw new NotFound('El préstamo no existe.');
        $item = null;
        foreach ($loan->items() as $candidate) {
            if ($candidate->id === $itemId) {
                $item = $candidate;
            }
        }
        if ($item === null) {
            throw new NotFound('La línea no existe en este préstamo.');
        }
        $book = $this->books->findActive($item->bookId) ?? throw new NotFound('El libro no existe.');
        try {
            $returned = $loan->returnItem($itemId, $book, $this->clock->now());
        } catch (ItemAlreadyReturned $e) {
            throw new Conflict('ITEM_ALREADY_RETURNED', $e->getMessage());
        }
        $this->loans->saveReturn($returned, $book);

        return $returned;
    }
}
