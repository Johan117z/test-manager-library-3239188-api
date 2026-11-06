<?php

namespace App\Infrastructure\Persistence;

use App\Application\Members\MemberService;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\Page;
use App\Domain\Member\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DbMemberRepository implements MemberRepository
{
    private const TABLE = 'library.member';

    public function find(string $id): ?Member
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : new Member($row->id, $row->full_name, $row->document_number);
    }

    public function documentExists(string $documentNumber): bool
    {
        return DB::table(self::TABLE)->where('document_number', $documentNumber)->exists();
    }

    public function add(Member $member): void
    {
        try {
            DB::table(self::TABLE)->insert([
                'id' => $member->id,
                'full_name' => $member->fullName,
                'document_number' => $member->documentNumber,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw MemberService::documentTaken();
        }
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $query = DB::table(self::TABLE);
        if ($text !== null) {
            $like = '%'.addcslashes($text, '\\%_').'%';
            $query->where(fn ($q) => $q->where('full_name', 'ilike', $like)->orWhere('document_number', 'ilike', $like));
        }
        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page($rows->map(fn ($r) => new Member($r->id, $r->full_name, $r->document_number))->all(), $page, $size, $total);
    }

    public function activeLoans(string $memberId): int
    {
        return DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('i.returned_at')
            ->count();
    }
}
