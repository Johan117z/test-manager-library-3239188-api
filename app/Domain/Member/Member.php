<?php

namespace App\Domain\Member;

use App\Domain\Shared\BusinessRuleViolation;

/** A person who borrows books. Does not log in (spec §1). Members are never deleted (CA-05.3). */
final class Member
{
    public function __construct(
        public readonly string $id,
        public readonly string $fullName,
        public readonly string $documentNumber,
    ) {
    }

    public static function register(string $id, string $fullName, string $documentNumber): self
    {
        $fullName = trim($fullName);
        $documentNumber = trim($documentNumber);
        if ($fullName === '' || mb_strlen($fullName) > 200) {
            throw BusinessRuleViolation::validation('full_name', 'obligatorio, máximo 200 caracteres');
        }
        if ($documentNumber === '' || mb_strlen($documentNumber) > 30) {
            throw BusinessRuleViolation::validation('document_number', 'obligatorio, máximo 30 caracteres');
        }

        return new self($id, $fullName, $documentNumber);
    }
}
