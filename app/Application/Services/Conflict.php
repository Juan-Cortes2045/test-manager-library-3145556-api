<?php

namespace App\Application\Services;

use RuntimeException;

/** 409 with a business code from api-contract.md §2 (ISBN_TAKEN, BOOK_HAS_OPEN_LOANS, …). */
final class Conflict extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
