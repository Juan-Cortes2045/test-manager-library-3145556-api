<?php

namespace App\Domain;

/**
 * Member document (CA-05.1; data-model.md §3, member.document_number varchar(30), unique).
 */
final readonly class DocumentNumber
{
    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || mb_strlen($trimmed) > 30) {
            throw new DomainRuleViolated('El documento es obligatorio y tiene máximo 30 caracteres.');
        }
        $this->value = $trimmed;
    }
}
