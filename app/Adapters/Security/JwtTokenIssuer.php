<?php

namespace App\Adapters\Security;

use App\Application\Ports\Clock;
use App\Application\Ports\TokenIssuer;
use App\Domain\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/** Plan D-11: JWT HS256, 8 hours, claims sub and role only (api-contract.md §4). */
final class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(private string $secret, private Clock $clock)
    {
    }

    public function issue(User $user): string
    {
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode(['sub' => $user->id, 'role' => $user->role->value, 'iat' => $now, 'exp' => $now + self::TTL_SECONDS], $this->secret, 'HS256');
    }

    /** @return array{sub: string, role: string} */
    public function decode(string $token): array
    {
        $claims = (array) JWT::decode($token, new Key($this->secret, 'HS256'));

        return ['sub' => (string) $claims['sub'], 'role' => (string) $claims['role']];
    }
}
