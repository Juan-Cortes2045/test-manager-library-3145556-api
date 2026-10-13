<?php

namespace Tests\Unit\Application;

use App\Application\Services\CreateLibrarianService;
use App\Application\Services\InvalidCredentials;
use App\Application\Services\LoginService;
use App\Application\Services\UsernameTaken;
use App\Domain\InvalidRole;
use App\Domain\Role;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeHasher;
use Tests\Fakes\FakeTokenIssuer;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Fakes\SequentialIds;

final class AuthServicesTest extends TestCase
{
    private InMemoryUserRepository $users;
    private CreateLibrarianService $create;
    private LoginService $login;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $hasher = new FakeHasher();
        $this->create = new CreateLibrarianService($this->users, $hasher, new SequentialIds());
        $this->login = new LoginService($this->users, $hasher, new FakeTokenIssuer());
    }

    public function test_hu02_creates_librarian_normalized_and_hashed(): void
    {
        $user = $this->create->create('Ana ', 'secreta', 'librarian');
        $this->assertSame('ana', $user->username);
        $this->assertSame(Role::Librarian, $user->role);
        $this->assertNotSame('secreta', $user->passwordHash);
    }

    public function test_hu02_repeated_username_is_rejected(): void
    {
        $this->create->create('ana', 'x1', 'librarian');
        $this->expectException(UsernameTaken::class);
        $this->create->create(' ANA', 'x2', 'librarian');
    }

    public function test_hu02_admin_role_is_rejected(): void
    {
        $this->expectException(InvalidRole::class);
        $this->create->create('boss', 'x', 'admin');
    }

    public function test_hu01_valid_credentials_return_8_hour_token_and_role(): void
    {
        $this->create->create('ana', 'secreta', 'librarian');
        $result = $this->login->login('Ana', 'secreta');
        $this->assertSame(28800, $result->expiresIn);
        $this->assertSame(Role::Librarian, $result->role);
        $this->assertNotSame('', $result->accessToken);
    }

    public function test_hu01_wrong_password_and_unknown_user_fail_the_same_way(): void
    {
        $this->create->create('ana', 'secreta', 'librarian');
        $messages = [];
        foreach ([['ana', 'mala'], ['nadie', 'secreta']] as [$u, $p]) {
            try {
                $this->login->login($u, $p);
                $this->fail('login must fail');
            } catch (InvalidCredentials $e) {
                $messages[] = $e->getMessage();
            }
        }
        $this->assertSame($messages[0], $messages[1]);
    }
}
