<?php

namespace App\Adapters\Http;

use App\Application\Ports\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\Member;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

/** Domain → contract JSON (api-contract.md §3): snake_case, ISO 8601 UTC with Z, {items,page,size,total}. */
final class Json
{
    public static function ok(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, [], JSON_UNESCAPED_UNICODE);
    }

    public static function page(Page $page, callable $map): array
    {
        return ['items' => array_map($map, $page->items), 'page' => $page->page, 'size' => $page->size, 'total' => $page->total];
    }

    public static function instant(?DateTimeImmutable $t): ?string
    {
        return $t?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public static function book(Book $b, ?string $categoryName): array
    {
        return ['id' => $b->id, 'title' => $b->title(), 'author' => $b->author(), 'isbn' => $b->isbn()->value,
            'category' => ['id' => $b->categoryId(), 'name' => $categoryName],
            'total_copies' => $b->totalCopies(), 'available_copies' => $b->availableCopies(), 'cover_key' => $b->coverKey()];
    }

    public static function member(Member $m): array
    {
        return ['id' => $m->id, 'full_name' => $m->fullName, 'document_number' => $m->documentNumber->value];
    }

    public static function item(LoanItem $i): array
    {
        return ['id' => $i->id, 'book_id' => $i->bookId, 'book_title' => $i->bookTitle, 'isbn' => $i->isbn,
            'category_name' => $i->categoryName, 'due_at' => self::instant($i->dueAt), 'returned_at' => self::instant($i->returnedAt())];
    }

    public static function loan(Loan $l, DateTimeImmutable $now): array
    {
        return ['id' => $l->id, 'member_id' => $l->memberId, 'loaned_at' => self::instant($l->loanedAt),
            'status' => $l->status($now)->value, 'items' => array_map([self::class, 'item'], $l->items())];
    }
}
