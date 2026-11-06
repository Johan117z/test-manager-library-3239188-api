<?php

namespace App\Infrastructure\Persistence;

use App\Application\Auth\CreateLibrarianService;
use App\Application\Ports\UserRepository;
use App\Domain\User\Role;
use App\Domain\User\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DbUserRepository implements UserRepository
{
    private const TABLE = 'library.user';

    public function findByUsername(string $normalizedUsername): ?User
    {
        $row = DB::table(self::TABLE)->where('username', $normalizedUsername)->first();

        return $row === null ? null : new User($row->id, $row->username, $row->password_hash, Role::from($row->role));
    }

    public function add(User $user): void
    {
        try {
            DB::table(self::TABLE)->insert([
                'id' => $user->id,
                'username' => $user->username,
                'password_hash' => $user->passwordHash,
                'role' => $user->role->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent insert of the same username: the unique index is the last barrier.
            throw CreateLibrarianService::usernameTaken();
        }
    }
}
