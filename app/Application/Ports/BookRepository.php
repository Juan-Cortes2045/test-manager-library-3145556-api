<?php

namespace App\Application\Ports;

use App\Domain\Book;

interface BookRepository
{
    /** Active book by id; null if unknown or soft-deleted (CA-04.3). */
    public function findActive(string $id): ?Book;

    public function existsActiveIsbn(string $isbn, ?string $exceptBookId = null): bool;

    public function hasOpenLoans(string $bookId): bool;

    /** Active books, partial text in title or author, optional category, ordered by title (CA-04.1). */
    public function search(?string $text, ?string $categoryId, int $page, int $size): Page;

    public function save(Book $book): void;
}
