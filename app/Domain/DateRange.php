<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * Inclusive range of instants: end >= start (CA-10.2; code INVALID_DATE_RANGE in api-contract.md §2).
 */
final readonly class DateRange
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($end < $start) {
            throw new InvalidDateRange('La fecha final no puede ser anterior a la inicial.');
        }
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $instant >= $this->start && $instant <= $this->end;
    }
}
