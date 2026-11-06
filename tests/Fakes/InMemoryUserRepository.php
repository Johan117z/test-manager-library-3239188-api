<?php

namespace Tests\Fakes;

use App\Application\Auth\CreateLibrarianService;
use App\Application\Ports\UserRepository;
use App\Domain\User\User;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    public array $byUsername = [];

    public function findByUsername(string $normalizedUsername): ?User
    {
        return $this->byUsername[$normalizedUsername] ?? null;
    }

    public function add(User $user): void
    {
        if (isset($this->byUsername[$user->username])) {
            throw CreateLibrarianService::usernameTaken();
        }
        $this->byUsername[$user->username] = $user;
    }
}
