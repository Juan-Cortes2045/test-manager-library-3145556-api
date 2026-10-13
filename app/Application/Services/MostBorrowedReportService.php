<?php

namespace App\Application\Services;

use App\Application\Ports\ReportReader;
use App\Domain\DateRange;
use DateTimeImmutable;
use DateTimeZone;

/** HU-10: most borrowed books by frozen title and category; dates inclusive, UTC (api-contract.md §3.7). */
final readonly class MostBorrowedReportService
{
    public function __construct(private ReportReader $reader)
    {
    }

    /** @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}> */
    public function report(string $from, string $to): array
    {
        $utc = new DateTimeZone('UTC');
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $utc);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $utc);
        if ($start === false || $end === false) {
            throw new \App\Domain\DomainRuleViolated('from y to deben tener formato YYYY-MM-DD.');
        }
        new DateRange($start, $end);

        $rows = $this->reader->mostBorrowed($start, $end->modify('+1 day'));
        usort($rows, fn (array $a, array $b) => [$b['times_borrowed'], $a['book_title']] <=> [$a['times_borrowed'], $b['book_title']]);

        return $rows;
    }
}
