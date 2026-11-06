<?php

namespace Tests\Fakes;

use App\Application\Ports\IssuedToken;
use App\Application\Ports\TokenClaims;
use App\Application\Ports\TokenIssuer;

final class FakeTokenIssuer implements TokenIssuer
{
    public function issue(string $userId, string $role): IssuedToken
    {
        return new IssuedToken("token:{$userId}:{$role}", 28800);
    }

    public function verify(string $token): ?TokenClaims
    {
        $parts = explode(':', $token);

        return count($parts) === 3 && $parts[0] === 'token' ? new TokenClaims($parts[1], $parts[2]) : null;
    }
}
