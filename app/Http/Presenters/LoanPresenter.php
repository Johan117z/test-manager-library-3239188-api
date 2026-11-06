<?php

namespace App\Http\Presenters;

use App\Application\Ports\Clock;
use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;
use DateTimeImmutable;
use DateTimeZone;

/** Loan JSON of api-contract.md §3.6. Instants in ISO 8601 UTC with Z. */
final class LoanPresenter
{
    public function __construct(private readonly Clock $clock)
    {
    }

    /** @return array<string, mixed> */
    public function loan(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_at' => self::instant($loan->loanedAt),
            'status' => $loan->status($this->clock->now()),
            'items' => array_map(fn (LoanItem $i) => self::item($i), $loan->items),
        ];
    }

    /** @return array<string, mixed> */
    public static function item(LoanItem $i): array
    {
        return [
            'id' => $i->id,
            'book_id' => $i->bookId,
            'book_title' => $i->bookTitle,
            'isbn' => $i->isbn,
            'category_name' => $i->categoryName,
            'due_at' => self::instant($i->dueAt),
            'returned_at' => $i->returnedAt === null ? null : self::instant($i->returnedAt),
        ];
    }

    public static function instant(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
