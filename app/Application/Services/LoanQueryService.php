<?php

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\Page;
use App\Domain\DateRange;
use App\Domain\DomainRuleViolated;
use App\Domain\Loan;
use DateTimeImmutable;
use DateTimeZone;

/** HU-08: loan detail and list (CA-08.1, CA-08.2); from/to are inclusive UTC dates (I-06). */
final readonly class LoanQueryService
{
    public function __construct(private LoanRepository $loans, private Clock $clock)
    {
    }

    public function get(string $id): Loan
    {
        return $this->loans->find($id) ?? throw new NotFound('El préstamo no existe.');
    }

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function list(?string $from, ?string $to, ?string $status, ?string $memberId, int $page, int $size): Page
    {
        if ($page < 1 || $size < 1 || $size > 50) {
            throw new DomainRuleViolated('page debe ser >= 1 y size entre 1 y 50.');
        }
        if ($status !== null && ! in_array($status, ['open', 'overdue', 'closed'], true)) {
            throw new DomainRuleViolated('status debe ser open, overdue o closed.');
        }
        $start = $from === null ? null : $this->date($from);
        $end = $to === null ? null : $this->date($to);
        if ($start !== null && $end !== null) {
            new DateRange($start, $end);
        }

        return $this->loans->list($start, $end?->modify('+1 day'), $status, $memberId, $this->clock->now(), $page, $size);
    }

    private function date(string $value): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $d ?: throw new DomainRuleViolated('Las fechas deben tener formato YYYY-MM-DD.');
    }
}
