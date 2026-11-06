<?php

namespace App\Application\Books;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use App\Domain\Book\Book;
use App\Domain\Shared\BusinessRuleViolation;
use Throwable;

/** CA-03.4: JPG or PNG, at most 2 MB; the book stores an opaque key. Replacing deletes the old binary after saving. */
final class SetCoverService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookCatalog $catalog,
        private readonly CoverStorage $storage,
        private readonly int $maxBytes,
    ) {
    }

    public function set(string $bookId, string $bytes): Book
    {
        $book = $this->catalog->activeBook($bookId);
        if (strlen($bytes) > $this->maxBytes) {
            throw BusinessRuleViolation::validation('file', 'supera el tamaño máximo de 2 MB');
        }
        if (CoverImage::mimeOf($bytes) === null) {
            throw BusinessRuleViolation::validation('file', 'solo se admiten imágenes JPG o PNG');
        }

        $oldKey = $book->coverKey();
        $newKey = $this->storage->put($bytes);
        try {
            $book->setCover($newKey);
            $this->books->save($book);
        } catch (Throwable $e) {
            $this->storage->delete($newKey); // do not leave an orphan binary
            throw $e;
        }
        if ($oldKey !== null) {
            $this->storage->delete($oldKey);
        }

        return $book;
    }
}
