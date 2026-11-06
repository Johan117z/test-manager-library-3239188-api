<?php

namespace App\Http\Presenters;

use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Page;
use App\Domain\Book\Book;

/** Book JSON of api-contract.md §3.4. */
final class BookPresenter
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /** @return array<string, mixed> */
    public function one(Book $book): array
    {
        $category = $this->categories->find($book->categoryId());

        return [
            'id' => $book->id(),
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn()->value,
            'category' => ['id' => $book->categoryId(), 'name' => $category?->name],
            'total_copies' => $book->totalCopies(),
            'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(),
        ];
    }

    /** @param Page<Book> $page @return array<string, mixed> */
    public function page(Page $page): array
    {
        return [
            'items' => array_map(fn (Book $b) => $this->one($b), $page->items),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ];
    }
}
