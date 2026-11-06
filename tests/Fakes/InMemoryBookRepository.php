<?php

namespace Tests\Fakes;

use App\Application\Books\CreateBookService;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Page;
use App\Domain\Book\Book;

final class InMemoryBookRepository implements BookRepository
{
    /** @var array<string, Book> */
    public array $books = [];

    /** @var array<string, true> book ids with unreturned loan lines */
    public array $openLoans = [];

    public function find(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;

        return $book !== null && $book->isActive() ? clone $book : null;
    }

    public function activeIsbnExists(string $isbn): bool
    {
        foreach ($this->books as $b) {
            if ($b->isActive() && $b->isbn()->value === $isbn) {
                return true;
            }
        }

        return false;
    }

    public function add(Book $book): void
    {
        if ($this->activeIsbnExists($book->isbn()->value)) {
            throw CreateBookService::isbnTaken();
        }
        $this->books[$book->id()] = clone $book;
    }

    public function save(Book $book): void
    {
        $this->books[$book->id()] = clone $book;
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return isset($this->openLoans[$bookId]);
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $hits = array_values(array_filter($this->books, fn (Book $b) => $b->isActive()
            && ($text === null || stripos($b->title(), $text) !== false || stripos($b->author(), $text) !== false)
            && ($categoryId === null || $b->categoryId() === $categoryId)));
        usort($hits, fn (Book $a, Book $b) => strcmp($a->title(), $b->title()));

        return new Page(array_slice($hits, ($page - 1) * $size, $size), $page, $size, count($hits));
    }
}
