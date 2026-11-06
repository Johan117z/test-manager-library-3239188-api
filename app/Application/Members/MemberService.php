<?php

namespace App\Application\Members;

use App\Application\Ports\MemberRepository;
use App\Application\Ports\Page;
use App\Domain\Member\Member;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;

/** HU-05: create, list/search and detail of members. There is no delete (CA-05.3). */
final class MemberService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function create(string $fullName, string $documentNumber): Member
    {
        $member = Member::register(Uuid::v4(), $fullName, $documentNumber);
        if ($this->members->documentExists($member->documentNumber)) {
            throw self::documentTaken();
        }
        $this->members->add($member);

        return $member;
    }

    /** @return Page<Member> */
    public function search(?string $text, ?int $page, ?int $size): Page
    {
        [$page, $size] = Page::normalize($page, $size);
        $text = $text === null ? null : trim($text);

        return $this->members->search($text === '' ? null : $text, $page, $size);
    }

    /** @return array{member: Member, active_loans: int} */
    public function get(string $id): array
    {
        $member = Uuid::isValid($id) ? $this->members->find($id) : null;
        if ($member === null) {
            throw BusinessRuleViolation::notFound();
        }

        return ['member' => $member, 'active_loans' => $this->members->activeLoans($id)];
    }

    public static function documentTaken(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.', [
            ['field' => 'document_number', 'issue' => 'repetido'],
        ]);
    }
}
