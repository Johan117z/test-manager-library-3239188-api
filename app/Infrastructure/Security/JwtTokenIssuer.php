<?php

namespace App\Infrastructure\Security;

use App\Application\Ports\Clock;
use App\Application\Ports\IssuedToken;
use App\Application\Ports\TokenClaims;
use App\Application\Ports\TokenIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/** JWT HS256, 8 hours, key from JWT_SECRET (plan D-11). */
final class JwtTokenIssuer implements TokenIssuer
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly Clock $clock,
        private readonly ?string $secret,
        private readonly int $ttlSeconds,
    ) {
    }

    public function issue(string $userId, string $role): IssuedToken
    {
        $now = $this->clock->now()->getTimestamp();
        $token = JWT::encode(
            ['sub' => $userId, 'role' => $role, 'iat' => $now, 'exp' => $now + $this->ttlSeconds],
            $this->key(),
            self::ALGORITHM,
        );

        return new IssuedToken($token, $this->ttlSeconds);
    }

    public function verify(string $token): ?TokenClaims
    {
        try {
            JWT::$timestamp = $this->clock->now()->getTimestamp();
            $payload = JWT::decode($token, new Key($this->key(), self::ALGORITHM));
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$timestamp = null;
        }

        if (! is_string($payload->sub ?? null) || ! is_string($payload->role ?? null)) {
            return null;
        }

        return new TokenClaims($payload->sub, $payload->role);
    }

    private function key(): string
    {
        if ($this->secret === null || strlen($this->secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be set to at least 32 characters.');
        }

        return $this->secret;
    }
}
