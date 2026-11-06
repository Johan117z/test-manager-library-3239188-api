<?php

namespace App\Infrastructure\Persistence;

use App\Application\Books\CreateBookService;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Page;
use App\Domain\Book\Book;
use App\Domain\Book\Isbn;
use App\Domain\Shared\BusinessRuleViolation;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DbBookRepository implements BookRepository
{
    private const TABLE = 'library.book';

    public function find(string $id): ?Book
    {
        $row = DB::table(self::TABLE)->where('id', $id)->whereNull('deleted_at')->first();

        return $row === null ? null : self::toBook($row);
    }

    public function activeIsbnExists(string $isbn): bool
    {
        return DB::table(self::TABLE)->where('isbn', $isbn)->whereNull('deleted_at')->exists();
    }

    public function add(Book $book): void
    {
        try {
            DB::table(self::TABLE)->insert([
                'id' => $book->id(),
                'title' => $book->title(),
                'author' => $book->author(),
                'isbn' => $book->isbn()->value,
                'category_id' => $book->categoryId(),
                'total_copies' => $book->totalCopies(),
                'available_copies' => $book->availableCopies(),
                'cover_key' => $book->coverKey(),
                'deleted_at' => self::instant($book->deletedAt()),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw CreateBookService::isbnTaken(); // partial unique index is the last barrier
        }
    }

    public function save(Book $book): void
    {
        try {
            DB::table(self::TABLE)->where('id', $book->id())->update([
                'title' => $book->title(),
                'author' => $book->author(),
                'category_id' => $book->categoryId(),
                'total_copies' => $book->totalCopies(),
                // Atomic delta: a concurrent loan or return is never overwritten.
                'available_copies' => DB::raw('available_copies + '.(int) $book->availableDelta()),
                'cover_key' => $book->coverKey(),
                'deleted_at' => self::instant($book->deletedAt()),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23514') { // check_violation: ck_book_available_range
                throw BusinessRuleViolation::validation('total_copies', 'no puede ser menor que los ejemplares prestados');
            }
            throw $e;
        }
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return DB::table('library.loan_item')->where('book_id', $bookId)->whereNull('returned_at')->exists();
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $query = DB::table(self::TABLE)->whereNull('deleted_at');
        if ($text !== null) {
            $like = '%'.addcslashes($text, '\\%_').'%';
            $query->where(fn ($q) => $q->where('title', 'ilike', $like)->orWhere('author', 'ilike', $like));
        }
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('title')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page($rows->map(fn ($row) => self::toBook($row))->all(), $page, $size, $total);
    }

    private static function toBook(object $row): Book
    {
        return new Book(
            $row->id, $row->title, $row->author, Isbn::from($row->isbn), $row->category_id,
            (int) $row->total_copies, (int) $row->available_copies, $row->cover_key,
            $row->deleted_at === null ? null : new DateTimeImmutable($row->deleted_at),
        );
    }

    private static function instant(?DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }
}
