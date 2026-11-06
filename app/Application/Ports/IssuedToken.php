<?php

namespace App\Application\Ports;

final class IssuedToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly int $expiresIn,
    ) {
    }
}
