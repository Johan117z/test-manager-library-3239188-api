<?php

namespace App\Application\Books;

use App\Application\Ports\BookRepository;
use App\Domain\Book\Book;

/** PUT /books/{id}: replaces title, author, category and total (CA-03.3). The ISBN does not change. */
final class UpdateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookCatalog $catalog,
    ) {
    }

    public function update(string $id, string $title, string $author, string $categoryId, int $totalCopies): Book
    {
        $book = $this->catalog->activeBook($id);
        $this->catalog->existingCategory($categoryId);
        $book->update($title, $author, $categoryId, $totalCopies);
        $this->books->save($book);

        return $book;
    }
}
