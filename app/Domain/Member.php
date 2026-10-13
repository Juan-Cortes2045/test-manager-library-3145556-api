<?php

namespace App\Domain;

/**
 * Member (architecture.md §3): borrows books, never logs in, never deleted (CA-05.3).
 */
final readonly class Member
{
    public string $fullName;

    public function __construct(
        public string $id,
        string $fullName,
        public DocumentNumber $documentNumber,
    ) {
        $trimmed = trim($fullName);
        if ($trimmed === '') {
            throw new DomainRuleViolated('El nombre del socio no puede estar vacío.');
        }
        $this->fullName = $trimmed;
    }
}
