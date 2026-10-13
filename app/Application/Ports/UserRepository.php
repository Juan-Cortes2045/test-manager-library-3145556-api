<?php

namespace App\Application\Ports;

use App\Domain\User;

interface UserRepository
{
    public function findByUsername(string $normalizedUsername): ?User;

    public function save(User $user): void;
}
