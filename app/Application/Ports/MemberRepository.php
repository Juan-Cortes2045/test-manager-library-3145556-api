<?php

namespace App\Application\Ports;

use App\Domain\Member;

interface MemberRepository
{
    public function find(string $id): ?Member;

    public function existsDocument(string $documentNumber): bool;

    public function save(Member $member): void;

    /** Name or document, partial, paged (CA-05.2). */
    public function search(?string $text, int $page, int $size): Page;
}
