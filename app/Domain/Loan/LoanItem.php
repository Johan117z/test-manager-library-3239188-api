<?php

namespace App\Domain\Loan;

use DateTimeImmutable;

/** One book inside a loan. Title, ISBN and category name are frozen at lending time (RN-4, ADR-004). */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        public readonly ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }
}
