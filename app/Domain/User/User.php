<?php

namespace App\Domain\User;

use App\Domain\Shared\BusinessRuleViolation;

/** Internal operator. Holds only the password hash, never the clear password. */
final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly Role $role,
    ) {
    }

    /** Username is stored lowercased and trimmed (CA-02.2): "Ana " -> "ana". */
    public static function normalizeUsername(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }

    public static function register(string $id, string $rawUsername, string $passwordHash, Role $role): self
    {
        $username = self::normalizeUsername($rawUsername);
        if ($username === '' || mb_strlen($username) > 120) {
            throw BusinessRuleViolation::validation('username', 'obligatorio, máximo 120 caracteres');
        }

        return new self($id, $username, $passwordHash, $role);
    }
}
