<?php

namespace App\Application\Auth;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Shared\Uuid;
use App\Domain\User\Role;
use App\Domain\User\User;
use InvalidArgumentException;

/**
 * Plan D-10: the deployment provisions the admin from the environment at startup, because only the
 * hash port can produce the hash. Idempotent: an existing user is never modified.
 */
final class EnsureInitialAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /** @return bool true when the admin was created now, false when it already existed */
    public function ensure(?string $username, ?string $password): bool
    {
        if ($username === null || trim($username) === '' || $password === null || $password === '') {
            throw new InvalidArgumentException('ADMIN_USERNAME and ADMIN_PASSWORD must be set in the environment.');
        }

        if ($this->users->findByUsername(User::normalizeUsername($username)) !== null) {
            return false;
        }

        $this->users->add(User::register(Uuid::v4(), $username, $this->hasher->hash($password), Role::Admin));

        return true;
    }
}
