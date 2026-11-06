<?php

namespace App\Application\Books;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Domain\Book\Book;
use App\Domain\Book\Category;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;

/** Shared lookups for the book use cases. */
final class BookCatalog
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
    ) {
    }

    /** CA-04.3: unknown or deleted -> 404. A malformed id is also just "not found". */
    public function activeBook(string $id): Book
    {
        $book = Uuid::isValid($id) ? $this->books->find($id) : null;

        return $book ?? throw BusinessRuleViolation::notFound();
    }

    public function existingCategory(string $id): Category
    {
        $category = Uuid::isValid($id) ? $this->categories->find($id) : null;

        return $category ?? throw BusinessRuleViolation::validation('category_id', 'la categoría no existe');
    }
}
