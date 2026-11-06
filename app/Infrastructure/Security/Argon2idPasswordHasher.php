<?php

namespace App\Infrastructure\Security;

use App\Application\Ports\PasswordHasher;

/** Argon2id (plan D-09). The plain password is never stored or logged. */
final class Argon2idPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_ARGON2ID);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
