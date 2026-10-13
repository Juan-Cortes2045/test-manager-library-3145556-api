<?php

namespace App\Application\Ports;

use App\Domain\User;

/** Token port (plan D-11): signed token with sub and role, valid 8 hours (CA-01.1). */
interface TokenIssuer
{
    public const TTL_SECONDS = 28800;

    public function issue(User $user): string;
}
