<?php

namespace App\Adapters\Security;

use App\Application\Ports\PasswordHasher;

/** Plan D-09: Argon2id. The plain password is never logged nor returned (Article IX, RNF-3). */
final class Argon2PasswordHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
