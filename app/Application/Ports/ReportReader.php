<?php

namespace App\Application\Ports;

use DateTimeImmutable;

interface ReportReader
{
    /**
     * One row per frozen book_id + book_title + category_name with loaned_at in [from, toExclusive),
     * by times_borrowed desc, then title. Never exposes members or librarians (ADR-004).
     *
     * @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>
     */
    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array;
}
