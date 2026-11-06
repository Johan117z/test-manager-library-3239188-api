<?php

namespace Tests\Fakes;

use App\Application\Ports\PasswordHasher;

/** Deterministic, fast stand-in for the Argon2id adapter. */
final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'fake$'.hash('sha256', $plainPassword);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return hash_equals($hash, $this->hash($plainPassword));
    }
}
