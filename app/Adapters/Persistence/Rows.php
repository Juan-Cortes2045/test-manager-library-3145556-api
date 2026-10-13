<?php

namespace App\Adapters\Persistence;

use App\Domain\Book;
use App\Domain\Isbn;
use App\Domain\Quantity;
use DateTimeImmutable;
use DateTimeZone;

/** Row ↔ domain mapping helpers. Only adapters know table and column names. */
final class Rows
{
    public static function book(object $r): Book
    {
        return Book::restore($r->id, $r->title, $r->author, new Isbn(trim($r->isbn)), $r->category_id,
            new Quantity((int) $r->total_copies), (int) $r->available_copies, $r->cover_key, self::instant($r->deleted_at));
    }

    public static function instant(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function sql(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
