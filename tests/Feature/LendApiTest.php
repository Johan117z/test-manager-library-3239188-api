<?php

namespace Tests\Feature;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Domain\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsUsers;
use Tests\Fakes\FixedClock;
use Tests\TestCase;

/** T-09 / T-13 evidence: HU-06 rules, the error order of team decision #5, and the last copy. */
class LendApiTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private FixedClock $clock;
    private array $admin;
    private array $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FixedClock('2026-10-14T14:15:00Z');
        $this->app->instance(Clock::class, $this->clock);
        $this->admin = $this->as(Role::Admin);
        $this->librarian = $this->as(Role::Librarian);
    }

    private function book(string $title, int $copies = 1): string
    {
        static $n = 0;
        $n++;

        return $this->postJson('/api/v1/books', [
            'title' => $title, 'author' => 'Autor', 'isbn' => sprintf('978%010d', $n),
            'category_id' => '11111111-1111-4111-8111-111111111111', 'total_copies' => $copies,
        ], $this->admin)->assertCreated()->json('id');
    }

    private function member(string $doc = 'CC-1'): string
    {
        return $this->postJson('/api/v1/members', ['full_name' => 'Socio '.$doc, 'document_number' => $doc], $this->librarian)->json('id');
    }

    private function lend(string $member, array $bookIds): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => array_map(fn ($id) => ['book_id' => $id], $bookIds)], $this->librarian);
    }

    private function available(string $bookId): int
    {
        return (int) DB::table('library.book')->where('id', $bookId)->value('available_copies');
    }

    public function test_lend_registers_lines_frozen_due_in_14_days_and_takes_copies(): void
    {
        $book = $this->book('Cien años de soledad', 2);
        $member = $this->member();

        $this->lend($member, [$book])->assertCreated()->assertExactJson([
            'id' => DB::table('library.loan')->value('id'),
            'member_id' => $member,
            'loaned_at' => '2026-10-14T14:15:00Z',
            'status' => 'open',
            'items' => [[
                'id' => DB::table('library.loan_item')->value('id'),
                'book_id' => $book, 'book_title' => 'Cien años de soledad', 'isbn' => '9780000000001',
                'category_name' => 'Novela', 'due_at' => '2026-10-28T14:15:00Z', 'returned_at' => null,
            ]],
        ]);
        $this->assertSame(1, $this->available($book));

        // Frozen: renaming the book does not change the line (RN-4).
        $this->putJson("/api/v1/books/{$book}", ['title' => 'Renombrado', 'author' => 'A', 'category_id' => '22222222-2222-4222-8222-222222222222', 'total_copies' => 2], $this->admin)->assertOk();
        $this->assertSame(['Cien años de soledad', 'Novela'], [
            DB::table('library.loan_item')->value('book_title'), DB::table('library.loan_item')->value('category_name'),
        ]);
    }

    public function test_shape_rules_are_422(): void
    {
        $member = $this->member();
        [$a, $b, $c, $d] = [$this->book('A'), $this->book('B'), $this->book('C'), $this->book('D')];

        $this->lend($member, [])->assertStatus(422)->assertJson(['code' => 'VALIDATION_ERROR']);
        $this->lend($member, [$a, $b, $c, $d])->assertStatus(422)->assertJson(['code' => 'VALIDATION_ERROR']);
        $this->lend($member, [$a, $a])->assertStatus(422)->assertJson(['code' => 'DUPLICATE_BOOK_IN_LOAN']);
        $this->lend('00000000-0000-4000-8000-000000000000', [$a])->assertStatus(422)->assertJsonPath('details.0.field', 'member_id');
        $this->lend($member, [$a, 'not-a-book'])->assertStatus(422)->assertJsonPath('details.0.field', 'items[1].book_id');
        $this->assertSame(0, DB::table('library.loan')->count());
    }

    public function test_limit_of_3_counts_the_requested_lines_and_reports_current_count(): void
    {
        $member = $this->member();
        $this->lend($member, [$this->book('A'), $this->book('B')])->assertCreated();

        $this->lend($member, [$this->book('C'), $this->book('D')])
            ->assertStatus(422)->assertJson(['code' => 'MEMBER_LIMIT_EXCEEDED', 'details' => [['field' => 'items', 'issue' => 'sin devolver: 2']]]);
        $this->lend($member, [$this->book('E')])->assertCreated();
    }

    public function test_member_with_overdue_book_cannot_borrow_and_error_order_is_respected(): void
    {
        $member = $this->member();
        [$a, $b] = [$this->book('A'), $this->book('B')];
        $this->lend($member, [$a, $this->book('X'), $this->book('Y')])->assertCreated(); // 3 unreturned
        $this->clock->advance('+15 days');                                                  // now overdue
        $this->librarian = $this->as(Role::Librarian);                                      // tokens last 8 hours

        // (1) shape before state: a repeated book wins over "has overdue".
        $this->lend($member, [$b, $b])->assertStatus(422)->assertJson(['code' => 'DUPLICATE_BOOK_IN_LOAN']);
        // (2) overdue (409) before the limit of 3 (422).
        $this->lend($member, [$b])->assertStatus(409)->assertJson(['code' => 'MEMBER_HAS_OVERDUE']);
    }

    public function test_limit_comes_before_no_copies(): void
    {
        $member = $this->member();
        $this->lend($member, [$this->book('A'), $this->book('B'), $this->book('C')])->assertCreated();
        $empty = $this->book('Agotado');
        $this->lend($this->member('CC-2'), [$empty])->assertCreated();

        // (3) limit before (4) no copies.
        $this->lend($member, [$empty])->assertStatus(422)->assertJson(['code' => 'MEMBER_LIMIT_EXCEEDED']);
    }

    public function test_no_copies_is_409_and_nothing_is_registered(): void
    {
        $free = $this->book('Libre', 3);
        $last = $this->book('Último');
        $this->lend($this->member('CC-1'), [$last])->assertCreated();
        $loansBefore = DB::table('library.loan')->count();

        $this->lend($this->member('CC-2'), [$free, $last])
            ->assertStatus(409)
            ->assertJson(['code' => 'BOOK_NOT_AVAILABLE', 'message' => 'No quedan ejemplares disponibles de «Último».', 'details' => [['field' => 'items[1].book_id', 'issue' => 'sin ejemplares']]]);

        $this->assertSame($loansBefore, DB::table('library.loan')->count());
        $this->assertSame(3, $this->available($free), 'the other line was not taken either');
        $this->assertSame(0, $this->available($last));
    }

    /**
     * RNF-2 deterministically: another librarian takes the last copy right after this request read the book.
     * The in-memory check passes, so only the conditional UPDATE can stop it: 409 and nothing saved.
     */
    public function test_conditional_update_stops_a_race_on_the_last_copy(): void
    {
        $last = $this->book('Último');
        $member = $this->member();
        $real = $this->app->make(BookRepository::class);
        $this->app->instance(BookRepository::class, new class($real) implements BookRepository {
            public function __construct(private BookRepository $inner) {}
            public function find(string $id): ?\App\Domain\Book\Book
            {
                $book = $this->inner->find($id);
                DB::table('library.book')->where('id', $id)->update(['available_copies' => 0]); // the other loan commits
                return $book;
            }
            public function activeIsbnExists(string $isbn): bool { return $this->inner->activeIsbnExists($isbn); }
            public function add(\App\Domain\Book\Book $book): void { $this->inner->add($book); }
            public function save(\App\Domain\Book\Book $book): void { $this->inner->save($book); }
            public function hasOpenLoans(string $bookId): bool { return $this->inner->hasOpenLoans($bookId); }
            public function search(?string $text, ?string $categoryId, int $page, int $size): \App\Application\Ports\Page { return $this->inner->search($text, $categoryId, $page, $size); }
        });

        $this->lend($member, [$last])->assertStatus(409)->assertJson(['code' => 'BOOK_NOT_AVAILABLE']);
        $this->assertSame(0, DB::table('library.loan')->count());
        $this->assertSame(0, $this->available($last), 'never below zero');
    }

    public function test_only_staff_can_lend(): void
    {
        $this->postJson('/api/v1/loans', ['member_id' => 'x', 'items' => []])->assertStatus(401);
    }
}
