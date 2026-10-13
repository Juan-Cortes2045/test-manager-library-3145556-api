<?php

namespace App\Application\Ports;

use DateTimeImmutable;

final readonly class OverdueLine
{
    public function __construct(
        public string $loanId,
        public string $itemId,
        public string $bookTitle,
        public string $memberName,
        public DateTimeImmutable $dueAt,
    ) {
    }
}
