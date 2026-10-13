<?php

namespace App\Adapters\Console;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\User;
use Illuminate\Console\Command;

/** Plan D-10 / T-17: creates the initial admin from ADMIN_USERNAME / ADMIN_PASSWORD. Idempotent. Never prints the password. */
final class BootstrapAdmin extends Command
{
    protected $signature = 'library:bootstrap-admin';

    protected $description = 'Crea el administrador inicial desde las variables de entorno';

    public function handle(UserRepository $users, PasswordHasher $hasher, IdGenerator $ids): int
    {
        $username = mb_strtolower(trim((string) env('ADMIN_USERNAME', '')));
        $password = (string) env('ADMIN_PASSWORD', '');
        if ($username === '' || $password === '') {
            $this->error('ADMIN_USERNAME y ADMIN_PASSWORD son obligatorias.');

            return self::FAILURE;
        }
        if ($users->findByUsername($username) !== null) {
            $this->info('El administrador inicial ya existe.');

            return self::SUCCESS;
        }
        $users->save(new User($ids->next(), $username, $hasher->hash($password), Role::Admin));
        $this->info('Administrador inicial creado.');

        return self::SUCCESS;
    }
}
