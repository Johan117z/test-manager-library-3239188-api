<?php

namespace Tests\Concerns;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Domain\Shared\Uuid;
use App\Domain\User\Role;
use App\Domain\User\User;

trait ActsAsUsers
{
    protected function createUser(string $username, string $password, Role $role): User
    {
        $user = new User(Uuid::v4(), $username, app(PasswordHasher::class)->hash($password), $role);
        app(UserRepository::class)->add($user);

        return $user;
    }

    /** @return array<string, string> Authorization header for a fresh user with the role. */
    protected function as(Role $role): array
    {
        $user = $this->createUser($role->value.'-'.substr(Uuid::v4(), 0, 8), 'irrelevant', $role);

        return ['Authorization' => 'Bearer '.app(TokenIssuer::class)->issue($user->id, $role->value)->accessToken];
    }
}
