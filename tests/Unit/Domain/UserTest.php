<?php

namespace Tests\Unit\Domain;

use App\Domain\DomainRuleViolated;
use App\Domain\InvalidRole;
use App\Domain\Role;
use App\Domain\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_username_is_lowercased_and_trimmed(): void
    {
        $user = new User('id', 'Ana ', 'hash', Role::Librarian);
        $this->assertSame('ana', $user->username);
    }

    public function test_api_can_request_librarian(): void
    {
        $this->assertSame(Role::Librarian, Role::requestedByApi('librarian'));
    }

    public function test_api_cannot_request_admin(): void
    {
        $this->expectException(InvalidRole::class);
        Role::requestedByApi('admin');
    }

    public function test_api_cannot_request_unknown_role(): void
    {
        $this->expectException(InvalidRole::class);
        Role::requestedByApi('superuser');
    }

    public function test_empty_username_is_rejected(): void
    {
        $this->expectException(DomainRuleViolated::class);
        new User('id', '   ', 'hash', Role::Librarian);
    }
}
