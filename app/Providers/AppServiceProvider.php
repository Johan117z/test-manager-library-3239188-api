<?php

namespace App\Providers;

use App\Application\Books\SetCoverService;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\LoanQueries;
use App\Application\Ports\LoanRepository;
use App\Infrastructure\Persistence\DbLoanQueries;
use App\Application\Ports\MemberRepository;
use App\Infrastructure\Persistence\DbLoanRepository;
use App\Infrastructure\Persistence\DbMemberRepository;
use App\Infrastructure\Storage\DiskCoverStorage;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Infrastructure\Persistence\DbBookRepository;
use App\Infrastructure\Persistence\DbCategoryRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Infrastructure\Persistence\DbUserRepository;
use App\Infrastructure\Security\Argon2idPasswordHasher;
use App\Infrastructure\Security\JwtTokenIssuer;
use App\Infrastructure\SystemClock;
use Illuminate\Support\ServiceProvider;

/**
 * Single composition point (constitution, article III): the only place where a
 * port meets its adapter.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(PasswordHasher::class, Argon2idPasswordHasher::class);
        $this->app->singleton(UserRepository::class, DbUserRepository::class);
        $this->app->scoped(CategoryRepository::class, DbCategoryRepository::class);
        $this->app->singleton(BookRepository::class, DbBookRepository::class);
        $this->app->singleton(MemberRepository::class, DbMemberRepository::class);
        $this->app->singleton(LoanRepository::class, DbLoanRepository::class);
        $this->app->singleton(LoanQueries::class, DbLoanQueries::class);
        $this->app->singleton(CoverStorage::class, fn () => new DiskCoverStorage(config('library.covers_dir')));
        $this->app->when(SetCoverService::class)->needs('$maxBytes')->give(fn () => (int) config('library.cover_max_bytes'));
        $this->app->singleton(TokenIssuer::class, fn ($app) => new JwtTokenIssuer(
            $app->make(Clock::class),
            config('library.jwt_secret'),
            (int) config('library.jwt_ttl'),
        ));
    }

    public function boot(): void
    {
    }
}
