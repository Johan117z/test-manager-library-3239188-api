<?php

namespace App\Application\Loans;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Domain\Loan\LoanItem;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;

/** HU-07: one line at a time; returning twice is 409 and never adds a copy twice. */
final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    public function return(string $loanId, string $itemId): LoanItem
    {
        $loan = Uuid::isValid($loanId) && Uuid::isValid($itemId) ? $this->loans->find($loanId) : null;
        $item = null;
        foreach ($loan?->items ?? [] as $candidate) {
            if ($candidate->id === $itemId) {
                $item = $candidate;
            }
        }
        if ($item === null) {
            throw BusinessRuleViolation::notFound();
        }

        $returned = $item->isReturned() ? null : $this->loans->returnItem($loanId, $itemId, $this->clock->now());

        return $returned ?? throw new BusinessRuleViolation(
            'ITEM_ALREADY_RETURNED',
            "La línea de «{$item->bookTitle}» ya fue devuelta.",
        );
    }
}
