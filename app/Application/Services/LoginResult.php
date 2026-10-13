<?php

namespace App\Application\Services;

use App\Domain\Role;

final readonly class LoginResult
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public Role $role,
    ) {
    }
}
