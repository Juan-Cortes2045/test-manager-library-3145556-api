<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Page;
use App\Domain\Book;
use Illuminate\Support\Facades\DB;

final class DbBookRepository implements BookRepository
{
    public function findActive(string $id): ?Book
    {
        $row = DB::table('library.book')->where('id', $id)->whereNull('deleted_at')->first();

        return $row === null ? null : Rows::book($row);
    }

    public function existsActiveIsbn(string $isbn, ?string $exceptBookId = null): bool
    {
        return DB::table('library.book')->where('isbn', $isbn)->whereNull('deleted_at')
            ->when($exceptBookId !== null, fn ($q) => $q->where('id', '<>', $exceptBookId))->exists();
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return DB::table('library.loan_item')->where('book_id', $bookId)->whereNull('returned_at')->exists();
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $query = DB::table('library.book')->whereNull('deleted_at')
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->when($text !== null, fn ($q) => $q->where(fn ($w) => $w->where('title', 'ilike', '%'.$text.'%')->orWhere('author', 'ilike', '%'.$text.'%')));
        $total = (clone $query)->count();
        $rows = $query->orderBy('title')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page(array_map(fn ($r) => Rows::book($r), $rows->all()), $page, $size, $total);
    }

    public function save(Book $book): void
    {
        DB::table('library.book')->upsert([[
            'id' => $book->id, 'title' => $book->title(), 'author' => $book->author(), 'isbn' => $book->isbn()->value,
            'category_id' => $book->categoryId(), 'total_copies' => $book->totalCopies(), 'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(), 'deleted_at' => Rows::sql($book->deletedAt()),
        ]], ['id'], ['title', 'author', 'isbn', 'category_id', 'total_copies', 'available_copies', 'cover_key', 'deleted_at']);
    }
}
