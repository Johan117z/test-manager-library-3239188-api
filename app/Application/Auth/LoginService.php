<?php

namespace App\Application\Auth;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\User\User;

/** HU-01: credentials -> 8-hour token. The error never says whether the user or the password failed (CA-01.2). */
final class LoginService
{
    private ?string $dummyHash = null;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    public function login(string $username, string $password): LoginResult
    {
        $user = $this->users->findByUsername(User::normalizeUsername($username));

        // Verify against a dummy hash when the user does not exist, so timing does not reveal it.
        $valid = $this->hasher->verify($password, $user?->passwordHash ?? $this->dummyHash());
        if ($user === null || ! $valid) {
            throw new BusinessRuleViolation('INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
        }

        $token = $this->tokens->issue($user->id, $user->role->value);

        return new LoginResult($token->accessToken, $token->expiresIn, $user->role->value);
    }

    private function dummyHash(): string
    {
        return $this->dummyHash ??= $this->hasher->hash(bin2hex(random_bytes(16)));
    }
}
