<?php

namespace App\Domain\Book;

use App\Domain\Shared\BusinessRuleViolation;

/** Value object: exactly 13 digits (CA-03.2). Lives inside the book row, no table. */
final class Isbn
{
    private function __construct(public readonly string $value)
    {
    }

    public static function from(string $raw): self
    {
        $value = trim($raw);
        if (! preg_match('/^\d{13}$/', $value)) {
            throw BusinessRuleViolation::validation('isbn', 'debe tener exactamente 13 dígitos');
        }

        return new self($value);
    }
}
