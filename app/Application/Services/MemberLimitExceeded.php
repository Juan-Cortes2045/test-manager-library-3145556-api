<?php

namespace App\Application\Services;

use RuntimeException;

/** CA-06.4: 422 MEMBER_LIMIT_EXCEEDED with the current count in details (I-05). */
final class MemberLimitExceeded extends RuntimeException
{
    public function __construct(public readonly int $currentCount)
    {
        parent::__construct(sprintf('El socio tiene %d libros sin devolver; máximo 3.', $currentCount));
    }
}
