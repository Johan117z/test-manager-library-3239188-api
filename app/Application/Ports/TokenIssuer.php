<?php

namespace App\Application\Ports;

interface TokenIssuer
{
    /** Signed token with `sub` and `role` only (no personal data). */
    public function issue(string $userId, string $role): IssuedToken;

    /** Returns the claims of a valid, unexpired token, or null. */
    public function verify(string $token): ?TokenClaims;
}
