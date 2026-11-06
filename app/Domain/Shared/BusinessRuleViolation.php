<?php

namespace App\Domain\Shared;

use RuntimeException;

/**
 * Error raised by the domain or the application layer. It carries the business
 * code of api-contract.md §2 and a Spanish user-facing message. Only the HTTP
 * adapter translates the code into a status (architecture.md §5).
 */
final class BusinessRuleViolation extends RuntimeException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('NOT_FOUND', 'El recurso solicitado no existe.');
    }

    public static function validation(string $field, string $issue): self
    {
        return new self('VALIDATION_ERROR', 'Los datos enviados no son válidos.', [['field' => $field, 'issue' => $issue]]);
    }
}
