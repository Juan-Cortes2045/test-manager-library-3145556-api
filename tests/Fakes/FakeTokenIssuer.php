<?php

namespace Tests\Fakes;

use App\Application\Ports\TokenIssuer;
use App\Domain\User;

final class FakeTokenIssuer implements TokenIssuer
{
    public function issue(User $user): string
    {
        return 'token-for-'.$user->id;
    }
}
