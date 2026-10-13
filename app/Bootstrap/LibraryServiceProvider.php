<?php

namespace App\Bootstrap;

use App\Adapters\Persistence\DbBookRepository;
use App\Adapters\Persistence\DbCategoryReader;
use App\Adapters\Persistence\DbLoanRepository;
use App\Adapters\Persistence\DbMemberRepository;
use App\Adapters\Persistence\DbUserRepository;
use App\Adapters\Security\Argon2PasswordHasher;
use App\Adapters\Security\JwtTokenIssuer;
use App\Adapters\Security\SystemClock;
use App\Adapters\Security\UuidGenerator;
use App\Adapters\Storage\DiskCoverStorage;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryReader;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\ReportReader;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Single composition root (Article III): the only place where a port is bound to its adapter.
 */
final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, UuidGenerator::class);
        $this->app->singleton(PasswordHasher::class, Argon2PasswordHasher::class);
        $this->app->singleton(JwtTokenIssuer::class, fn ($app) => new JwtTokenIssuer((string) env('JWT_SECRET', ''), $app->make(Clock::class)));
        $this->app->singleton(TokenIssuer::class, JwtTokenIssuer::class);
        $this->app->singleton(UserRepository::class, DbUserRepository::class);
        $this->app->singleton(BookRepository::class, DbBookRepository::class);
        $this->app->singleton(DbCategoryReader::class);
        $this->app->singleton(CategoryReader::class, DbCategoryReader::class);
        $this->app->singleton(MemberRepository::class, DbMemberRepository::class);
        $this->app->singleton(DbLoanRepository::class);
        $this->app->singleton(LoanRepository::class, DbLoanRepository::class);
        $this->app->singleton(ReportReader::class, DbLoanRepository::class);
        $this->app->singleton(DiskCoverStorage::class, fn ($app) => new DiskCoverStorage(
            base_path((string) env('COVERS_DIR', 'storage/app/covers')), $app->make(IdGenerator::class)));
        $this->app->singleton(CoverStorage::class, DiskCoverStorage::class);
    }
}
