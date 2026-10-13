<?php

namespace App\Application\Ports;

/** The five seeded categories (data-model.md §9); read-only. */
interface CategoryReader
{
    public function nameOf(string $categoryId): ?string;
}
