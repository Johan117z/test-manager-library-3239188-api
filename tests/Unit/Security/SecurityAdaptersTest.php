<?php

namespace Tests\Unit\Security;

use App\Infrastructure\Security\Argon2idPasswordHasher;
use App\Infrastructure\Security\JwtTokenIssuer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fakes\FixedClock;

/** T-14 evidence (adapter level): Argon2id hash and JWT HS256 with 8 hours, sub and role only. */
class SecurityAdaptersTest extends TestCase
{
    private const SECRET = 'unit-test-secret-0123456789abcdef0123456789';

    public function test_password_is_hashed_with_argon2id_and_never_kept_in_clear(): void
    {
        $hasher = new Argon2idPasswordHasher();
        $hash = $hasher->hash('S3cret-Pass!');

        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertStringNotContainsString('S3cret-Pass!', $hash);
        $this->assertTrue($hasher->verify('S3cret-Pass!', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function test_token_is_hs256_valid_8_hours_with_only_sub_and_role(): void
    {
        $clock = new FixedClock();
        $issuer = new JwtTokenIssuer($clock, self::SECRET, 28800);
        $issued = $issuer->issue('user-id-1', 'librarian');

        $this->assertSame(28800, $issued->expiresIn);
        [$header, $payload] = array_map(
            fn ($part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true),
            array_slice(explode('.', $issued->accessToken), 0, 2),
        );
        $this->assertSame('HS256', $header['alg']);
        $this->assertSame(['sub', 'role', 'iat', 'exp'], array_keys($payload));
        $this->assertSame(28800, $payload['exp'] - $payload['iat']);

        $claims = $issuer->verify($issued->accessToken);
        $this->assertSame('user-id-1', $claims->userId);
        $this->assertSame('librarian', $claims->role);
    }

    public function test_expired_tampered_or_foreign_tokens_are_rejected(): void
    {
        $clock = new FixedClock();
        $issuer = new JwtTokenIssuer($clock, self::SECRET, 28800);
        $token = $issuer->issue('u', 'admin')->accessToken;

        $this->assertNull($issuer->verify($token.'x'));
        $this->assertNull((new JwtTokenIssuer($clock, str_repeat('z', 40), 28800))->verify($token));

        $clock->advance('+8 hours +1 second');
        $this->assertNull($issuer->verify($token));
    }

    public function test_missing_secret_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);
        (new JwtTokenIssuer(new FixedClock(), null, 28800))->issue('u', 'admin');
    }
}
