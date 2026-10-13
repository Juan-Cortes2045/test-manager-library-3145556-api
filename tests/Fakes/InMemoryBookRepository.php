<?php

namespace Tests\Fakes;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Page;
use App\Domain\Book;

final class InMemoryBookRepository implements BookRepository
{
    /** @var array<string, Book> */
    public array $books = [];

    /** @var array<string, true> */
    public array $withOpenLoans = [];

    public function findActive(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;

        return $book !== null && $book->isActive() ? $book : null;
    }

    public function existsActiveIsbn(string $isbn, ?string $exceptBookId = null): bool
    {
        foreach ($this->books as $book) {
            if ($book->isActive() && $book->isbn()->value === $isbn && $book->id !== $exceptBookId) {
                return true;
            }
        }

        return false;
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return isset($this->withOpenLoans[$bookId]);
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $found = array_values(array_filter($this->books, function (Book $b) use ($text, $categoryId) {
            if (! $b->isActive() || ($categoryId !== null && $b->categoryId() !== $categoryId)) {
                return false;
            }

            return $text === null || stripos($b->title(), $text) !== false || stripos($b->author(), $text) !== false;
        }));
        usort($found, fn (Book $a, Book $b) => strcmp($a->title(), $b->title()));

        return new Page(array_slice($found, ($page - 1) * $size, $size), $page, $size, count($found));
    }

    public function save(Book $book): void
    {
        $this->books[$book->id] = $book;
    }
}
