<?php

namespace App\Domain\Loan;

use App\Domain\Book\Book;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;
use DateTimeImmutable;

/**
 * A completed fact: who borrowed, who registered it and when. 1 to 3 lines, no repeated book,
 * due 14 days after lending, immutable except returns (CA-06.x). The status is computed (article VII).
 */
final class Loan
{
    public const MAX_ITEMS = 3;
    public const LOAN_DAYS = 14;
    public const MAX_UNRETURNED_PER_MEMBER = 3;

    /** @param list<LoanItem> $items */
    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
        public readonly array $items,
    ) {
    }

    /**
     * Withdraws one copy of each book and creates its line in the same operation (architecture.md §3).
     *
     * @param list<array{book: Book, category_name: string}> $lines
     */
    public static function lend(string $memberId, string $userId, DateTimeImmutable $loanedAt, array $lines): self
    {
        if (count($lines) < 1 || count($lines) > self::MAX_ITEMS) {
            throw BusinessRuleViolation::validation('items', 'debe tener de 1 a 3 libros');
        }
        self::assertNoRepeatedBook(array_map(fn ($l) => $l['book']->id(), $lines));

        $dueAt = $loanedAt->modify('+'.self::LOAN_DAYS.' days');
        $items = [];
        foreach ($lines as $i => $line) {
            $book = $line['book'];
            try {
                $book->withdraw();
            } catch (BusinessRuleViolation $e) {
                throw new BusinessRuleViolation($e->errorCode, $e->getMessage(), [['field' => "items[{$i}].book_id", 'issue' => 'sin ejemplares']]);
            }
            $items[] = new LoanItem(Uuid::v4(), $book->id(), $book->title(), $book->isbn()->value, $line['category_name'], $dueAt);
        }

        return new self(Uuid::v4(), $memberId, $userId, $loanedAt, $items);
    }

    /** CA-06.2. @param list<string> $bookIds */
    public static function assertNoRepeatedBook(array $bookIds): void
    {
        $seen = [];
        foreach ($bookIds as $i => $id) {
            if (isset($seen[$id])) {
                throw new BusinessRuleViolation('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir el mismo libro en un préstamo.', [
                    ['field' => "items[{$i}].book_id", 'issue' => 'repetido'],
                ]);
            }
            $seen[$id] = true;
        }
    }

    /** open: unreturned lines, none overdue; overdue: some unreturned and overdue; closed: all returned. */
    public function status(DateTimeImmutable $now): string
    {
        $unreturned = array_filter($this->items, fn (LoanItem $i) => ! $i->isReturned());
        if ($unreturned === []) {
            return 'closed';
        }
        foreach ($unreturned as $item) {
            if ($item->isOverdue($now)) {
                return 'overdue';
            }
        }

        return 'open';
    }
}
