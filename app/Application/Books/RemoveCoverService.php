<?php

namespace App\Application\Books;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;

/** data-model.md §7: first null the key and commit, then delete the binary. */
final class RemoveCoverService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookCatalog $catalog,
        private readonly CoverStorage $storage,
    ) {
    }

    public function remove(string $bookId): void
    {
        $book = $this->catalog->activeBook($bookId);
        $oldKey = $book->coverKey();
        if ($oldKey === null) {
            return;
        }

        $book->removeCover();
        $this->books->save($book);
        $this->storage->delete($oldKey);
    }
}
