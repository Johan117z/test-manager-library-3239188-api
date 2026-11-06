<?php

namespace Tests\Feature;

use App\Domain\Shared\Uuid;
use App\Domain\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsUsers;
use Tests\TestCase;

/** HU-03 and HU-04 through HTTP against PostgreSQL. */
class BooksApiTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private const NOVELA = '11111111-1111-4111-8111-111111111111';
    private const CIENCIA = '22222222-2222-4222-8222-222222222222';

    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->as(Role::Admin);
    }

    private function book(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Cien años de soledad', 'author' => 'Gabriel García Márquez',
            'isbn' => '9780307474728', 'category_id' => self::NOVELA, 'total_copies' => 4,
        ], $overrides);
    }

    public function test_admin_creates_book_and_contract_shape_is_respected(): void
    {
        $this->postJson('/api/v1/books', $this->book(), $this->admin)
            ->assertCreated()
            ->assertJson([
                'title' => 'Cien años de soledad', 'isbn' => '9780307474728',
                'category' => ['id' => self::NOVELA, 'name' => 'Novela'],
                'total_copies' => 4, 'available_copies' => 4, 'cover_key' => null,
            ])
            ->assertJsonStructure(['id', 'title', 'author', 'isbn', 'category' => ['id', 'name'], 'total_copies', 'available_copies', 'cover_key']);
    }

    public function test_validation_and_duplicates(): void
    {
        $this->postJson('/api/v1/books', $this->book(['isbn' => '123']), $this->admin)
            ->assertStatus(422)->assertJson(['code' => 'VALIDATION_ERROR'])->assertJsonPath('details.0.field', 'isbn');
        $this->postJson('/api/v1/books', $this->book(['total_copies' => 0]), $this->admin)->assertStatus(422);
        $this->postJson('/api/v1/books', $this->book(['category_id' => Uuid::v4()]), $this->admin)
            ->assertStatus(422)->assertJsonPath('details.0.field', 'category_id');

        $this->postJson('/api/v1/books', $this->book(), $this->admin)->assertCreated();
        $this->postJson('/api/v1/books', $this->book(['title' => 'Otro']), $this->admin)
            ->assertStatus(409)->assertJson(['code' => 'ISBN_TAKEN']);
    }

    public function test_librarian_reads_but_cannot_write(): void
    {
        $id = $this->postJson('/api/v1/books', $this->book(), $this->admin)->json('id');
        $librarian = $this->as(Role::Librarian);

        $this->getJson("/api/v1/books/{$id}", $librarian)->assertOk();
        $this->getJson('/api/v1/categories', $librarian)->assertOk()->assertJsonCount(5)
            ->assertJsonPath('0.name', 'Ciencia');
        $this->postJson('/api/v1/books', $this->book(['isbn' => '9780000000009']), $librarian)->assertStatus(403);
        $this->putJson("/api/v1/books/{$id}", $this->book(), $librarian)->assertStatus(403);
        $this->deleteJson("/api/v1/books/{$id}", [], $librarian)->assertStatus(403);
        $this->getJson('/api/v1/books')->assertStatus(401);
    }

    public function test_update_adjusts_available_and_never_below_zero(): void
    {
        $id = $this->postJson('/api/v1/books', $this->book(['total_copies' => 3]), $this->admin)->json('id');
        DB::table('library.book')->where('id', $id)->update(['available_copies' => 1]); // 2 copies lent

        $this->putJson("/api/v1/books/{$id}", $this->book(['total_copies' => 5, 'title' => 'Nuevo título', 'isbn' => '9999999999999']), $this->admin)
            ->assertOk()->assertJson(['title' => 'Nuevo título', 'isbn' => '9780307474728', 'total_copies' => 5, 'available_copies' => 3]);
        $this->putJson("/api/v1/books/{$id}", $this->book(['total_copies' => 1]), $this->admin)
            ->assertStatus(422)->assertJsonPath('details.0.field', 'total_copies');
    }

    public function test_soft_delete_hides_book_keeps_row_and_frees_isbn(): void
    {
        $id = $this->postJson('/api/v1/books', $this->book(), $this->admin)->json('id');

        $this->openLoanFor($id);
        $this->deleteJson("/api/v1/books/{$id}", [], $this->admin)->assertStatus(409)->assertJson(['code' => 'BOOK_HAS_OPEN_LOANS']);

        DB::table('library.loan_item')->update(['returned_at' => '2026-10-20T10:00:00Z']);
        $this->deleteJson("/api/v1/books/{$id}", [], $this->admin)->assertNoContent();

        $this->getJson("/api/v1/books/{$id}", $this->admin)->assertNotFound()->assertJson(['code' => 'NOT_FOUND']);
        $this->getJson('/api/v1/books', $this->admin)->assertJson(['total' => 0]);
        $this->assertNotNull(DB::table('library.book')->where('id', $id)->value('deleted_at'));
        $this->postJson('/api/v1/books', $this->book(), $this->admin)->assertCreated();
    }

    public function test_search_by_partial_text_category_order_pagination_and_total(): void
    {
        foreach ([['Zorro', 'Ana Pérez', self::NOVELA], ['Momo', 'Michael Ende', self::NOVELA], ['Breve historia del tiempo', 'Stephen Hawking', self::CIENCIA], ['100% real_text', 'X', self::NOVELA]] as $i => [$t, $a, $c]) {
            $this->postJson('/api/v1/books', $this->book(['title' => $t, 'author' => $a, 'category_id' => $c, 'isbn' => '978000000000'.$i]), $this->admin)->assertCreated();
        }

        $this->getJson('/api/v1/books?q=ENDE', $this->admin)->assertJson(['total' => 1, 'items' => [['title' => 'Momo']]]);
        $this->getJson('/api/v1/books?q=%25', $this->admin)->assertJson(['total' => 1], 'percent sign is literal');
        $this->getJson('/api/v1/books?category_id='.self::CIENCIA, $this->admin)->assertJson(['total' => 1]);

        $page = $this->getJson('/api/v1/books?page=2&size=2', $this->admin)->assertOk();
        $page->assertJson(['page' => 2, 'size' => 2, 'total' => 4]);
        $this->assertSame(['Momo', 'Zorro'], array_column($page->json('items'), 'title'));

        $this->getJson('/api/v1/books?size=500', $this->admin)->assertJson(['size' => 50]);
        $this->getJson('/api/v1/books/not-a-uuid', $this->admin)->assertNotFound();
        $this->getJson('/api/v1/books/'.Uuid::v4(), $this->admin)->assertNotFound();
    }

    private function openLoanFor(string $bookId): void
    {
        $member = Uuid::v4();
        $loan = Uuid::v4();
        $user = DB::table('library.user')->value('id');
        DB::table('library.member')->insert(['id' => $member, 'full_name' => 'Socio', 'document_number' => 'D-1']);
        DB::table('library.loan')->insert(['id' => $loan, 'member_id' => $member, 'loaned_by_user_id' => $user, 'loaned_at' => '2026-10-14T14:15:00Z']);
        DB::table('library.loan_item')->insert([
            'id' => Uuid::v4(), 'loan_id' => $loan, 'book_id' => $bookId, 'book_title' => 'Cien años de soledad',
            'isbn' => '9780307474728', 'category_name' => 'Novela', 'due_at' => '2026-10-28T14:15:00Z', 'returned_at' => null,
        ]);
    }
}
