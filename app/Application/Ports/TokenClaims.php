<?php

namespace App\Application\Ports;

final class TokenClaims
{
    public function __construct(
        public readonly string $userId,
        public readonly string $role,
    ) {
    }
}
