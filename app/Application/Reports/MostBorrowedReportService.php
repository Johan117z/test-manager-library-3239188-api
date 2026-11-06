<?php

namespace App\Application\Reports;

use App\Application\Ports\ReportReader;
use App\Application\Shared\DateRange;

/** HU-10: calculated on demand from frozen values, never stored (ADR-004). An empty range is an empty list. */
final class MostBorrowedReportService
{
    public function __construct(private readonly ReportReader $reader)
    {
    }

    /** @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}> */
    public function run(string $from, string $to): array
    {
        $range = DateRange::of($from, $to);

        return $this->reader->mostBorrowed($range->from, $range->toExclusive);
    }
}
