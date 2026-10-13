<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\User;
use Illuminate\Support\Facades\DB;

final class DbUserRepository implements UserRepository
{
    public function findByUsername(string $normalizedUsername): ?User
    {
        $r = DB::table('library.user')->where('username', $normalizedUsername)->first();

        return $r === null ? null : new User($r->id, $r->username, $r->password_hash, Role::from($r->role));
    }

    public function save(User $user): void
    {
        DB::table('library.user')->upsert([['id' => $user->id, 'username' => $user->username, 'password_hash' => $user->passwordHash, 'role' => $user->role->value]],
            ['id'], ['username', 'password_hash', 'role']);
    }
}
