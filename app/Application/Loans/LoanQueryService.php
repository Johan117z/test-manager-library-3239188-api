<?php

namespace App\Application\Loans;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanQueries;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\Page;
use App\Application\Shared\DateRange;
use App\Domain\Loan\Loan;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;

/** HU-08 (detail and list) and HU-09 (overdue lines). */
final class LoanQueryService
{
    public const STATUSES = ['open', 'overdue', 'closed'];

    public function __construct(
        private readonly LoanRepository $loans,
        private readonly LoanQueries $queries,
        private readonly Clock $clock,
    ) {
    }

    public function get(string $id): Loan
    {
        $loan = Uuid::isValid($id) ? $this->loans->find($id) : null;

        return $loan ?? throw BusinessRuleViolation::notFound();
    }

    /** @return Page<Loan> */
    public function list(?string $from, ?string $to, ?string $status, ?string $memberId, ?int $page, ?int $size): Page
    {
        $range = DateRange::of($from, $to);
        if ($status !== null && ! in_array($status, self::STATUSES, true)) {
            throw BusinessRuleViolation::validation('status', 'debe ser open, overdue o closed');
        }
        if ($memberId !== null && ! Uuid::isValid($memberId)) {
            throw BusinessRuleViolation::validation('member_id', 'debe ser un UUID');
        }
        [$page, $size] = Page::normalize($page, $size);

        return $this->queries->list($range->from, $range->toExclusive, $status, $memberId, $this->clock->now(), $page, $size);
    }

    public function overdue(?int $page, ?int $size): Page
    {
        [$page, $size] = Page::normalize($page, $size);

        return $this->queries->overdue($this->clock->now(), $page, $size);
    }
}
