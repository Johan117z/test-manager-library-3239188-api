<?php

namespace App\Application\Ports;

use DateTimeImmutable;

/** Read side for loan listings (status is computed in SQL, never stored). */
interface LoanQueries
{
    /**
     * Loans with loaned_at in [from, toExclusive), status and member filters, newest first.
     *
     * @return Page<\App\Domain\Loan\Loan>
     */
    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $toExclusive, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page;

    /**
     * Unreturned lines with due_at before now, most days late first (HU-09).
     *
     * @return Page<array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: DateTimeImmutable, days_late: int}>
     */
    public function overdue(DateTimeImmutable $now, int $page, int $size): Page;
}
