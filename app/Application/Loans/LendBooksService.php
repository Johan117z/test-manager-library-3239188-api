<?php

namespace App\Application\Loans;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Domain\Loan\Loan;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;

/**
 * HU-06. Rule order (team decision #5): (1) 422 request shape, including repeated book;
 * (2) 409 member has overdue books; (3) 422 limit of 3; (4) 409 no copies.
 */
final class LendBooksService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    /** @param list<string> $bookIds */
    public function lend(string $memberId, array $bookIds, string $userId): Loan
    {
        // (1) shape
        if (count($bookIds) < 1 || count($bookIds) > Loan::MAX_ITEMS) {
            throw BusinessRuleViolation::validation('items', 'debe tener de 1 a 3 libros');
        }
        Loan::assertNoRepeatedBook($bookIds);
        if (! Uuid::isValid($memberId) || $this->members->find($memberId) === null) {
            throw BusinessRuleViolation::validation('member_id', 'el socio no existe');
        }
        $lines = [];
        foreach ($bookIds as $i => $bookId) {
            $book = Uuid::isValid($bookId) ? $this->books->find($bookId) : null;
            if ($book === null) {
                throw BusinessRuleViolation::validation("items[{$i}].book_id", 'el libro no existe');
            }
            $lines[] = ['book' => $book, 'category_name' => (string) $this->categories->find($book->categoryId())?->name];
        }

        $now = $this->clock->now();
        $summary = $this->loans->memberSummary($memberId, $now);

        // (2) member with overdue books cannot borrow (CA-06.5)
        if ($summary['overdue'] > 0) {
            throw new BusinessRuleViolation('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y debe devolverlos antes de pedir otro.', [
                ['field' => 'member_id', 'issue' => "{$summary['overdue']} libro(s) vencido(s)"],
            ]);
        }

        // (3) at most 3 unreturned books, counting the ones requested now (CA-06.4, team decision #4)
        if ($summary['unreturned'] + count($bookIds) > Loan::MAX_UNRETURNED_PER_MEMBER) {
            throw new BusinessRuleViolation(
                'MEMBER_LIMIT_EXCEEDED',
                "El socio ya tiene {$summary['unreturned']} libro(s) sin devolver; el máximo es ".Loan::MAX_UNRETURNED_PER_MEMBER.'.',
                [['field' => 'items', 'issue' => "sin devolver: {$summary['unreturned']}"]],
            );
        }

        // (4) copies: checked by the domain, then enforced atomically by the repository
        $loan = Loan::lend($memberId, $userId, $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s')), $lines);
        $this->loans->add($loan);

        return $loan;
    }
}
