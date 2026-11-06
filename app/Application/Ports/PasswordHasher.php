<?php

namespace App\Application\Ports;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;

    public function verify(string $plainPassword, string $hash): bool;
}
