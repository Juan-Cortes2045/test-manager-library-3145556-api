<?php

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryReader;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\Page;
use App\Domain\Book;
use App\Domain\DomainRuleViolated;
use App\Domain\Isbn;
use App\Domain\Quantity;

/** HU-03 and HU-04: create, edit, soft delete, cover, search and detail of books. */
final readonly class BookService
{
    public const MAX_PAGE_SIZE = 50;
    public const MAX_COVER_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private BookRepository $books,
        private CategoryReader $categories,
        private CoverStorage $covers,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function create(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): Book
    {
        $isbnValue = new Isbn($isbn);
        $this->requireCategory($categoryId);
        if ($this->books->existsActiveIsbn($isbnValue->value)) {
            throw new Conflict('ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.');
        }
        $book = Book::create($this->ids->next(), $title, $author, $isbnValue, $categoryId, new Quantity($totalCopies));
        $this->books->save($book);

        return $book;
    }

    public function update(string $id, string $title, string $author, string $categoryId, int $totalCopies): Book
    {
        $book = $this->get($id);
        $this->requireCategory($categoryId);
        $book->update($title, $author, $categoryId, new Quantity($totalCopies));
        $this->books->save($book);

        return $book;
    }

    public function get(string $id): Book
    {
        return $this->books->findActive($id) ?? throw new NotFound('El libro no existe.');
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        if ($page < 1 || $size < 1 || $size > self::MAX_PAGE_SIZE) {
            throw new DomainRuleViolated('page debe ser >= 1 y size entre 1 y 50.');
        }
        $text = $text !== null && trim($text) !== '' ? trim($text) : null;

        return $this->books->search($text, $categoryId, $page, $size);
    }

    /** Soft delete; refused with open loans (CA-03.5, ADR-003). */
    public function delete(string $id): void
    {
        $book = $this->get($id);
        if ($this->books->hasOpenLoans($book->id)) {
            throw new Conflict('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos abiertos y no se puede dar de baja.');
        }
        $book->softDelete($this->clock->now());
        $this->books->save($book);
    }

    /** JPG/PNG up to 2 MB, type checked by content (CA-03.4; api-contract.md §4). */
    public function setCover(string $id, string $bytes): Book
    {
        $book = $this->get($id);
        if (strlen($bytes) > self::MAX_COVER_BYTES) {
            throw new DomainRuleViolated('La portada supera 2 MB.');
        }
        $mime = match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            default => throw new DomainRuleViolated('La portada debe ser JPG o PNG.'),
        };
        $old = $book->coverKey();
        $book->setCover($this->covers->put($bytes, $mime));
        $this->books->save($book);
        if ($old !== null) {
            $this->covers->delete($old);
        }

        return $book;
    }

    /** Order required by data-model.md §7: null the key and commit first, then delete the binary. */
    public function removeCover(string $id): void
    {
        $book = $this->get($id);
        $old = $book->coverKey();
        $book->removeCover();
        $this->books->save($book);
        if ($old !== null) {
            $this->covers->delete($old);
        }
    }

    private function requireCategory(string $categoryId): void
    {
        if ($this->categories->nameOf($categoryId) === null) {
            throw new DomainRuleViolated('La categoría no existe.');
        }
    }
}
