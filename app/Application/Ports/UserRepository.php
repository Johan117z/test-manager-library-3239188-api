<?php

namespace App\Application\Ports;

use App\Domain\User\User;

interface UserRepository
{
    public function findByUsername(string $normalizedUsername): ?User;

    /** @throws \App\Domain\Shared\BusinessRuleViolation USERNAME_TAKEN when the username already exists */
    public function add(User $user): void;
}
