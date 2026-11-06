<?php

namespace App\Application\Ports;

use App\Domain\Book\Book;

interface BookRepository
{
    /** Active book by id, or null (deleted books are not found: ADR-003). */
    public function find(string $id): ?Book;

    public function activeIsbnExists(string $isbn): bool;

    /** @throws \App\Domain\Shared\BusinessRuleViolation ISBN_TAKEN on a concurrent duplicate */
    public function add(Book $book): void;

    /** Persists the book; available copies are applied as a delta (atomic). */
    public function save(Book $book): void;

    public function hasOpenLoans(string $bookId): bool;

    /** @return Page<Book> active books, ordered by title */
    public function search(?string $text, ?string $categoryId, int $page, int $size): Page;
}
