<?php

namespace App\Application\Books;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Page;
use App\Domain\Book\Book;

/** HU-04: partial text in title or author, category filter, active only, by title, paginated with total. */
final class SearchBooksService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookCatalog $catalog,
    ) {
    }

    /** @return Page<Book> */
    public function search(?string $text, ?string $categoryId, ?int $page, ?int $size): Page
    {
        [$page, $size] = Page::normalize($page, $size);
        $text = $text === null ? null : trim($text);
        // An unknown category is just a filter with no results, not an error.

        return $this->books->search($text === '' ? null : $text, $categoryId, $page, $size);
    }

    public function get(string $id): Book
    {
        return $this->catalog->activeBook($id);
    }
}
