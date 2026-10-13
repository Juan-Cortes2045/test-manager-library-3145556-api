<?php

namespace App\Application\Ports;

/** Pagination result: maps to {items, page, size, total} (api-contract.md §1). */
final readonly class Page
{
    /** @param list<mixed> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $size,
        public int $total,
    ) {
    }
}
