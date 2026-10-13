<?php

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;

/** HU-09: overdue items, most days late first; days_late computed, never stored (Article VII, I-06). */
final readonly class OverdueService
{
    public function __construct(
        private LoanRepository $loans,
        private Clock $clock,
    ) {
    }

    /** @return list<array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: string, days_late: int}> */
    public function list(): array
    {
        $now = $this->clock->now();
        $rows = [];
        foreach ($this->loans->overdueLines($now) as $line) {
            $rows[] = [
                'loan_id' => $line->loanId,
                'item_id' => $line->itemId,
                'book_title' => $line->bookTitle,
                'member_name' => $line->memberName,
                'due_at' => $line->dueAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                'days_late' => (int) ceil(($now->getTimestamp() - $line->dueAt->getTimestamp()) / 86400),
            ];
        }
        usort($rows, fn (array $a, array $b) => $b['days_late'] <=> $a['days_late']);

        return $rows;
    }
}
