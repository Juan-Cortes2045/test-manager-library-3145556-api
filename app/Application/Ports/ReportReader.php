<?php

namespace App\Application\Ports;

use DateTimeImmutable;

/**
 * Read port for HU-10 (ADR-004): one aggregated query grouped by book_id, book_title and category_name
 * as frozen in loan_item; never joins the live catalog. Range is [$from, $toExclusive).
 */
interface ReportReader
{
    /** @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}> */
    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array;
}
