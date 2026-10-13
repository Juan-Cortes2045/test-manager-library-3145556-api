<?php

namespace App\Domain;

/**
 * Internal operator (architecture.md §3): username stored lowercase and trimmed (CA-02.2),
 * role admin or librarian. Holds only the password hash, never the plain password (Article IX).
 */
final readonly class User
{
    public string $username;

    public function __construct(
        public string $id,
        string $username,
        public string $passwordHash,
        public Role $role,
    ) {
        $normalized = mb_strtolower(trim($username));
        if ($normalized === '') {
            throw new DomainRuleViolated('El nombre de usuario no puede estar vacío.');
        }
        if ($passwordHash === '') {
            throw new DomainRuleViolated('El hash de la clave no puede estar vacío.');
        }
        $this->username = $normalized;
    }
}
