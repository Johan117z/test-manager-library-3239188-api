<?php

namespace App\Application\Auth;

final class LoginResult
{
    public function __construct(
        public readonly string $accessToken,
        public readonly int $expiresIn,
        public readonly string $role,
    ) {
    }
}
