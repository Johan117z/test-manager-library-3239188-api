<?php

namespace App\Application\Ports;

use App\Domain\Member\Member;

interface MemberRepository
{
    public function find(string $id): ?Member;

    public function documentExists(string $documentNumber): bool;

    /** @throws \App\Domain\Shared\BusinessRuleViolation DOCUMENT_TAKEN on a concurrent duplicate */
    public function add(Member $member): void;

    /** @return Page<Member> by name or document, ordered by name */
    public function search(?string $text, int $page, int $size): Page;

    /** Number of unreturned loan lines of the member. */
    public function activeLoans(string $memberId): int;
}
