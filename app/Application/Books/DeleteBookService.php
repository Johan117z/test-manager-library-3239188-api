<?php

namespace App\Application\Books;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Domain\Shared\BusinessRuleViolation;

/** CA-03.5 / ADR-003: logical delete; refused while the book has open loans. */
final class DeleteBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookCatalog $catalog,
        private readonly Clock $clock,
    ) {
    }

    public function delete(string $id): void
    {
        $book = $this->catalog->activeBook($id);
        if ($this->books->hasOpenLoans($book->id())) {
            throw new BusinessRuleViolation('BOOK_HAS_OPEN_LOANS', "El libro «{$book->title()}» tiene préstamos sin devolver y no se puede dar de baja.");
        }

        $book->softDelete($this->clock->now());
        $this->books->save($book);
    }
}
