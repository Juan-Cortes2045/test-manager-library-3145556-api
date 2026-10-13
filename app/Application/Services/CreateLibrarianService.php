<?php

namespace App\Application\Services;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\DomainRuleViolated;
use App\Domain\Role;
use App\Domain\User;

/** HU-02: the admin creates librarians. Role admin via API → rejected (CA-02.3). */
final readonly class CreateLibrarianService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private IdGenerator $ids,
    ) {
    }

    public function create(string $username, string $password, string $requestedRole): User
    {
        $role = Role::requestedByApi($requestedRole);
        if ($password === '') {
            throw new DomainRuleViolated('La clave no puede estar vacía.');
        }
        $normalized = mb_strtolower(trim($username));
        if ($this->users->findByUsername($normalized) !== null) {
            throw new UsernameTaken('El nombre de usuario ya existe.');
        }
        $user = new User($this->ids->next(), $normalized, $this->hasher->hash($password), $role);
        $this->users->save($user);

        return $user;
    }
}
