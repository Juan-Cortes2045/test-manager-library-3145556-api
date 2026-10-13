<?php

namespace App\Domain;

/**
 * Number of copies of a book: always >= 1 (CA-03.3; data-model.md §4, total_copies >= 1 is domain-only until T-18).
 */
final readonly class Quantity
{
    public int $value;

    public function __construct(int $value)
    {
        if ($value < 1) {
            throw new InvalidQuantity('El total de ejemplares debe ser al menos 1.');
        }
        $this->value = $value;
    }
}
