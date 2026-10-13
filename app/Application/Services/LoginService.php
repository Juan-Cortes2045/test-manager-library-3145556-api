<?php

namespace App\Application\Services;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;

/** HU-01: login with username and password, returns an 8-hour token. */
final readonly class LoginService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
    ) {
    }

    public function login(string $username, string $password): LoginResult
    {
        $user = $this->users->findByUsername(mb_strtolower(trim($username)));
        if ($user === null || ! $this->hasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials('Usuario o clave incorrectos.');
        }

        return new LoginResult($this->tokens->issue($user), TokenIssuer::TTL_SECONDS, $user->role);
    }
}
