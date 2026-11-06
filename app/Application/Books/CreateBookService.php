<?php

namespace App\Application\Books;

use App\Application\Ports\BookRepository;
use App\Domain\Book\Book;
use App\Domain\Book\Isbn;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\Shared\Uuid;

/** CA-03.1, CA-03.2. */
final class CreateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookCatalog $catalog,
    ) {
    }

    public function create(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): Book
    {
        $isbn = Isbn::from($isbn);
        $this->catalog->existingCategory($categoryId);
        $book = Book::create(Uuid::v4(), $title, $author, $isbn, $categoryId, $totalCopies);

        if ($this->books->activeIsbnExists($isbn->value)) {
            throw self::isbnTaken();
        }
        $this->books->add($book);

        return $book;
    }

    public static function isbnTaken(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.', [
            ['field' => 'isbn', 'issue' => 'repetido'],
        ]);
    }
}
