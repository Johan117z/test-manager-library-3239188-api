<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\LoanRepository;
use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;
use App\Domain\Shared\BusinessRuleViolation;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class DbLoanRepository implements LoanRepository
{
    public function add(Loan $loan): void
    {
        DB::transaction(function () use ($loan) {
            // Lock rows in a stable order (by book id) so two loans with the same books cannot deadlock.
            $order = array_keys($loan->items);
            usort($order, fn ($a, $b) => strcmp($loan->items[$a]->bookId, $loan->items[$b]->bookId));

            foreach ($order as $i) {
                $item = $loan->items[$i];
                // Team decision #1: conditional update. 0 rows -> someone took the last copy.
                $taken = DB::update(
                    'UPDATE library.book SET available_copies = available_copies - 1
                     WHERE id = ? AND deleted_at IS NULL AND available_copies > 0',
                    [$item->bookId],
                );
                if ($taken === 0) {
                    throw new BusinessRuleViolation('BOOK_NOT_AVAILABLE', "No quedan ejemplares disponibles de «{$item->bookTitle}».", [
                        ['field' => "items[{$i}].book_id", 'issue' => 'sin ejemplares'],
                    ]);
                }
            }

            DB::table('library.loan')->insert([
                'id' => $loan->id,
                'member_id' => $loan->memberId,
                'loaned_by_user_id' => $loan->loanedByUserId,
                'loaned_at' => self::instant($loan->loanedAt),
            ]);
            DB::table('library.loan_item')->insert(array_map(fn (LoanItem $item) => [
                'id' => $item->id,
                'loan_id' => $loan->id,
                'book_id' => $item->bookId,
                'book_title' => $item->bookTitle,
                'isbn' => $item->isbn,
                'category_name' => $item->categoryName,
                'due_at' => self::instant($item->dueAt),
                'returned_at' => null,
            ], $loan->items));
        });
    }

    public function find(string $id): ?Loan
    {
        $row = DB::table('library.loan')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }
        $items = DB::table('library.loan_item')->where('loan_id', $id)->orderBy('due_at')->orderBy('book_title')->get()
            ->map(fn ($r) => self::toItem($r))->all();

        return new Loan($row->id, $row->member_id, $row->loaned_by_user_id, self::parse($row->loaned_at), $items);
    }

    public function memberSummary(string $memberId, DateTimeImmutable $now): array
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS unreturned, COUNT(*) FILTER (WHERE i.due_at < ?) AS overdue
             FROM library.loan_item i JOIN library.loan l ON l.id = i.loan_id
             WHERE l.member_id = ? AND i.returned_at IS NULL',
            [self::instant($now), $memberId],
        );

        return ['unreturned' => (int) $row->unreturned, 'overdue' => (int) $row->overdue];
    }

    public function returnItem(string $loanId, string $itemId, DateTimeImmutable $at): ?LoanItem
    {
        return DB::transaction(function () use ($loanId, $itemId, $at) {
            // Only an open line changes, so a second return never adds a copy twice (CA-07.2).
            $row = DB::selectOne(
                'UPDATE library.loan_item SET returned_at = ? WHERE id = ? AND loan_id = ? AND returned_at IS NULL RETURNING *',
                [self::instant($at), $itemId, $loanId],
            );
            if ($row === null) {
                return null;
            }
            // The CHECK keeps available_copies <= total_copies (CA-07.3).
            DB::update('UPDATE library.book SET available_copies = available_copies + 1 WHERE id = ?', [$row->book_id]);

            return self::toItem($row);
        });
    }

    public static function toItem(object $r): LoanItem
    {
        return new LoanItem($r->id, $r->book_id, $r->book_title, $r->isbn, $r->category_name, self::parse($r->due_at), $r->returned_at === null ? null : self::parse($r->returned_at));
    }

    public static function parse(string $value): DateTimeImmutable
    {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function instant(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }
}
