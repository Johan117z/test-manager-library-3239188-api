<?php

namespace App\Application\Auth;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;
use App\Domain\User\Role;
use App\Domain\User\User;

/** HU-02: the admin creates librarians. The admin role is never granted through the API (CA-02.3). */
final class CreateLibrarianService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    public function create(string $username, string $password, string $role): User
    {
        if ($role !== Role::Librarian->value) {
            throw BusinessRuleViolation::validation('role', 'solo se admite "librarian"');
        }
        if ($password === '') {
            throw BusinessRuleViolation::validation('password', 'obligatoria');
        }

        $user = User::register(Uuid::v4(), $username, $this->hasher->hash($password), Role::Librarian);
        if ($this->users->findByUsername($user->username) !== null) {
            throw self::usernameTaken();
        }
        $this->users->add($user);

        return $user;
    }

    public static function usernameTaken(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('USERNAME_TAKEN', 'El nombre de usuario ya está en uso.', [
            ['field' => 'username', 'issue' => 'repetido'],
        ]);
    }
}
