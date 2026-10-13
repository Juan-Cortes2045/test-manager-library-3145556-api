<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * Book aggregate (architecture.md §3): available copies always between 0 and total (RN-1),
 * total >= 1 (Quantity), title not empty, soft delete only (ADR-003). Plain PHP (Article I).
 */
final class Book
{
    private function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        private Isbn $isbn,
        private string $categoryId,
        private Quantity $totalCopies,
        private int $availableCopies,
        private ?string $coverKey,
        private ?DateTimeImmutable $deletedAt,
    ) {
    }

    /** New book: available copies start equal to the total (CA-03.1). */
    public static function create(string $id, string $title, string $author, Isbn $isbn, string $categoryId, Quantity $totalCopies): self
    {
        return new self($id, self::requiredText($title, 'título'), self::requiredText($author, 'autor'), $isbn, $categoryId, $totalCopies, $totalCopies->value, null, null);
    }

    /** Rebuilds a stored book; still enforces every invariant. */
    public static function restore(string $id, string $title, string $author, Isbn $isbn, string $categoryId, Quantity $totalCopies, int $availableCopies, ?string $coverKey, ?DateTimeImmutable $deletedAt): self
    {
        if ($availableCopies < 0 || $availableCopies > $totalCopies->value) {
            throw new InvalidAvailableCopies('Los ejemplares disponibles deben estar entre 0 y el total.');
        }
        if ($coverKey === '') {
            throw new DomainRuleViolated('La clave de portada no puede ser una cadena vacía.');
        }

        return new self($id, self::requiredText($title, 'título'), self::requiredText($author, 'autor'), $isbn, $categoryId, $totalCopies, $availableCopies, $coverKey, $deletedAt);
    }

    /** Lending one copy (CA-06.3): never below 0. */
    public function withdraw(): void
    {
        if ($this->availableCopies === 0) {
            throw new BookNotAvailable(sprintf('No quedan ejemplares disponibles de «%s».', $this->title));
        }
        $this->availableCopies--;
    }

    /** Returning one copy (CA-07.1, CA-07.3): never above the total. */
    public function restock(): void
    {
        if ($this->availableCopies === $this->totalCopies->value) {
            throw new InvalidAvailableCopies('Los ejemplares disponibles no pueden superar el total.');
        }
        $this->availableCopies++;
    }

    /** Soft delete (CA-03.5, ADR-003). The "no open loans" check belongs to the use case (T-08). */
    public function softDelete(DateTimeImmutable $at): void
    {
        if ($this->deletedAt === null) {
            $this->deletedAt = $at;
        }
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies->value;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    private static function requiredText(string $value, string $field): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new DomainRuleViolated(sprintf('El %s no puede estar vacío.', $field));
        }

        return $trimmed;
    }
}
