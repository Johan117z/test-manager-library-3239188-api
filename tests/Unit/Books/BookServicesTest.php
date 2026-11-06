<?php

namespace Tests\Unit\Books;

use App\Application\Books\BookCatalog;
use App\Application\Books\CreateBookService;
use App\Application\Books\DeleteBookService;
use App\Application\Books\SearchBooksService;
use App\Application\Books\UpdateBookService;
use App\Domain\Book\Book;
use App\Domain\Shared\BusinessRuleViolation;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCategoryRepository as Cat;

/** T-08 evidence: HU-03 and HU-04 pass against fakes. */
class BookServicesTest extends TestCase
{
    private InMemoryBookRepository $books;
    private BookCatalog $catalog;

    protected function setUp(): void
    {
        $this->books = new InMemoryBookRepository();
        $this->catalog = new BookCatalog($this->books, new Cat());
    }

    private function create(string $title = 'Cien años de soledad', string $isbn = '9780307474728', int $total = 4, string $author = 'Gabriel García Márquez', string $cat = Cat::NOVELA): Book
    {
        return (new CreateBookService($this->books, $this->catalog))->create($title, $author, $isbn, $cat, $total);
    }

    public function test_ca_03_1_available_copies_start_equal_to_total(): void
    {
        $book = $this->create(total: 4);

        $this->assertSame([4, 4], [$book->totalCopies(), $book->availableCopies()]);
        $this->assertNull($book->coverKey());
    }

    public function test_ca_03_2_isbn_has_13_digits_and_is_unique_among_active(): void
    {
        $this->assertCode('VALIDATION_ERROR', fn () => $this->create(isbn: '978-030747472'));
        $this->assertCode('VALIDATION_ERROR', fn () => $this->create(isbn: '978030747472'));
        $this->create();
        $this->assertCode('ISBN_TAKEN', fn () => $this->create(title: 'Otro'));
    }

    public function test_ca_03_3_total_change_moves_available_and_never_below_zero(): void
    {
        $book = $this->create(total: 4);
        $stored = $this->books->find($book->id());
        $stored->withdraw();
        $stored->withdraw();
        $stored->withdraw(); // 3 lent, 1 available
        $this->books->save($stored);
        $update = new UpdateBookService($this->books, $this->catalog);

        $updated = $update->update($book->id(), 'Cien años', 'GGM', Cat::CIENCIA, 6);
        $this->assertSame([6, 3], [$updated->totalCopies(), $updated->availableCopies()]);

        $this->assertCode('VALIDATION_ERROR', fn () => $update->update($book->id(), 'Cien años', 'GGM', Cat::NOVELA, 2));
        $this->assertCode('VALIDATION_ERROR', fn () => $update->update($book->id(), 'Cien años', 'GGM', Cat::NOVELA, 0));
        $this->assertSame(3, $this->books->find($book->id())->availableCopies());
    }

    public function test_unknown_category_is_a_validation_error(): void
    {
        $this->assertCode('VALIDATION_ERROR', fn () => $this->create(cat: '99999999-9999-4999-8999-999999999999'));
    }

    public function test_ca_03_5_delete_is_logical_and_refused_with_open_loans(): void
    {
        $book = $this->create();
        $delete = new DeleteBookService($this->books, $this->catalog, new FixedClock());

        $this->books->openLoans[$book->id()] = true;
        $this->assertCode('BOOK_HAS_OPEN_LOANS', fn () => $delete->delete($book->id()));

        unset($this->books->openLoans[$book->id()]);
        $delete->delete($book->id());
        $this->assertNotNull($this->books->books[$book->id()]->deletedAt(), 'row kept, marked as deleted');
        $this->assertCode('NOT_FOUND', fn () => $this->catalog->activeBook($book->id()));

        $this->create(); // ISBN can be registered again once the old book is deleted
    }

    public function test_ca_04_search_text_category_active_order_and_total(): void
    {
        $this->create('Zorro', '9780000000001', author: 'Ana');
        $this->create('Ábaco de la ciencia', '9780000000002', cat: Cat::CIENCIA, author: 'Luis');
        $this->create('Momo', '9780000000003', author: 'Michael Ende');
        $search = new SearchBooksService($this->books, $this->catalog);

        $byAuthor = $search->search('ende', null, 1, 20);
        $this->assertSame(['Momo'], array_map(fn (Book $b) => $b->title(), $byAuthor->items));

        $all = $search->search(null, null, 1, 2);
        $this->assertSame(3, $all->total);
        $this->assertCount(2, $all->items);

        $this->assertSame(1, $search->search(null, Cat::CIENCIA, 1, 20)->total);
        $this->assertSame(50, $search->search(null, null, 1, 500)->size, 'size is capped at 50');
        $this->assertCode('NOT_FOUND', fn () => $search->get('not-a-uuid'));
    }

    private function assertCode(string $code, callable $action): void
    {
        try {
            $action();
        } catch (BusinessRuleViolation $e) {
            $this->assertSame($code, $e->errorCode);

            return;
        }
        $this->fail("Expected {$code}.");
    }
}
