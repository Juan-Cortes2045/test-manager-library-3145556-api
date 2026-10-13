<?php

namespace Tests\Fakes;

use App\Application\Ports\PasswordHasher;

final class FakeHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return 'hashed:'.strrev($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        return $hash === $this->hash($plain);
    }
}
