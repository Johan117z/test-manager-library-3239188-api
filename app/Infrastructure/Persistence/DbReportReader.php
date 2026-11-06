<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\ReportReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DbReportReader implements ReportReader
{
    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        // Groups by the values frozen in the line, never by the live catalog (ADR-004).
        $rows = DB::select(
            'SELECT i.book_id, i.book_title, i.category_name, COUNT(*)::int AS times_borrowed
             FROM library.loan_item i
             JOIN library.loan l ON l.id = i.loan_id
             WHERE l.loaned_at >= ? AND l.loaned_at < ?
             GROUP BY i.book_id, i.book_title, i.category_name
             ORDER BY times_borrowed DESC, i.book_title ASC, i.category_name ASC',
            [DbLoanRepository::instant($from), DbLoanRepository::instant($toExclusive)],
        );

        return array_map(fn ($r) => [
            'book_id' => $r->book_id,
            'book_title' => $r->book_title,
            'category_name' => $r->category_name,
            'times_borrowed' => (int) $r->times_borrowed,
        ], $rows);
    }
}
