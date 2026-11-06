<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\LoanQueries;
use App\Application\Ports\Page;
use App\Domain\Loan\Loan;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DbLoanQueries implements LoanQueries
{
    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $toExclusive, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page
    {
        $nowSql = DbLoanRepository::instant($now);
        $query = DB::table('library.loan as l');
        if ($from !== null) {
            $query->where('l.loaned_at', '>=', DbLoanRepository::instant($from));
        }
        if ($toExclusive !== null) {
            $query->where('l.loaned_at', '<', DbLoanRepository::instant($toExclusive));
        }
        if ($memberId !== null) {
            $query->where('l.member_id', $memberId);
        }
        $open = fn (Builder $q) => $q->from('library.loan_item as i')->whereColumn('i.loan_id', 'l.id')->whereNull('i.returned_at');
        $late = fn (Builder $q) => $open($q)->where('i.due_at', '<', $nowSql);
        match ($status) {
            'closed' => $query->whereNotExists($open),
            'overdue' => $query->whereExists($late),
            'open' => $query->whereExists($open)->whereNotExists($late),
            default => null,
        };

        $total = (clone $query)->count();
        $rows = $query->select('l.*')->orderByDesc('l.loaned_at')->orderBy('l.id')->offset(($page - 1) * $size)->limit($size)->get();

        $items = DB::table('library.loan_item')->whereIn('loan_id', $rows->pluck('id')->all())
            ->orderBy('due_at')->orderBy('book_title')->get()->groupBy('loan_id');
        $loans = $rows->map(fn ($r) => new Loan(
            $r->id, $r->member_id, $r->loaned_by_user_id, DbLoanRepository::parse($r->loaned_at),
            ($items[$r->id] ?? collect())->map(fn ($i) => DbLoanRepository::toItem($i))->values()->all(),
        ))->all();

        return new Page($loans, $page, $size, $total);
    }

    public function overdue(DateTimeImmutable $now, int $page, int $size): Page
    {
        $nowSql = DbLoanRepository::instant($now);
        $base = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('i.returned_at')
            ->where('i.due_at', '<', $nowSql);

        $total = (clone $base)->count();
        $rows = $base
            ->select('i.loan_id', 'i.id as item_id', 'i.book_title', 'm.full_name as member_name', 'i.due_at')
            // Days started late, rounded up: 1 hour late is 1 day late.
            ->selectRaw('CEIL(EXTRACT(EPOCH FROM (?::timestamptz - i.due_at)) / 86400)::int AS days_late', [$nowSql])
            ->orderBy('i.due_at')->orderBy('i.id')
            ->offset(($page - 1) * $size)->limit($size)->get();

        return new Page($rows->map(fn ($r) => [
            'loan_id' => $r->loan_id, 'item_id' => $r->item_id, 'book_title' => $r->book_title,
            'member_name' => $r->member_name, 'due_at' => DbLoanRepository::parse($r->due_at), 'days_late' => (int) $r->days_late,
        ])->all(), $page, $size, $total);
    }
}
