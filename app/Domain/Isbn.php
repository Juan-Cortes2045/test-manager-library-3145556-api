<?php

namespace App\Domain;

/**
 * ISBN of a book: exactly 13 digits (CA-03.2; data-model.md §3, book.isbn char(13), digits only).
 */
final readonly class Isbn
{
    public string $value;

    public function __construct(string $value)
    {
        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw new InvalidIsbn('El ISBN debe tener exactamente 13 dígitos.');
        }
        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
