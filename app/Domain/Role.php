<?php

namespace App\Domain;

/**
 * User role (spec.md §1; data-model.md §3, user.role in admin|librarian).
 */
enum Role: string
{
    case Admin = 'admin';
    case Librarian = 'librarian';

    /** Role requested through the API: only "librarian" (CA-02.3); "admin" comes from the environment only. */
    public static function requestedByApi(string $value): self
    {
        if ($value !== self::Librarian->value) {
            throw new InvalidRole('Solo se puede crear el rol librarian por la API.');
        }

        return self::Librarian;
    }
}
