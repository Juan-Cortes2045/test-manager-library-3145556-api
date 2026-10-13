<?php

namespace Tests\Fakes;

use App\Application\Ports\UserRepository;
use App\Domain\User;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    public array $byUsername = [];

    public function findByUsername(string $normalizedUsername): ?User
    {
        return $this->byUsername[$normalizedUsername] ?? null;
    }

    public function save(User $user): void
    {
        $this->byUsername[$user->username] = $user;
    }
}
