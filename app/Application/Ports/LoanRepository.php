<?php

namespace App\Application\Ports;

use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;
use DateTimeImmutable;

interface LoanRepository
{
    /**
     * Saves the loan and takes one copy of each book in ONE transaction, with a conditional update
     * (available_copies > 0). If any copy is gone, nothing is saved (CA-06.3, team decision #1).
     *
     * @throws \App\Domain\Shared\BusinessRuleViolation BOOK_NOT_AVAILABLE
     */
    public function add(Loan $loan): void;

    public function find(string $id): ?Loan;

    /** @return array{unreturned: int, overdue: int} unreturned lines of the member and how many are overdue */
    public function memberSummary(string $memberId, DateTimeImmutable $now): array;

    /**
     * Sets returned_at only if the line is still open, and gives the copy back, in one transaction.
     * Returns the updated line, or null if it was already returned.
     */
    public function returnItem(string $loanId, string $itemId, DateTimeImmutable $at): ?LoanItem;
}
