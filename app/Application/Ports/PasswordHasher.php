<?php

namespace App\Application\Ports;

/** Password hashing port (plan D-09, Article IX): the domain never sees the plain password. */
interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;
}
