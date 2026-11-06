<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T-02 evidence: the queries of data-model.md §10 return what the document declares.
 */
class InitialMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = [
        'book' => [
            ['id', 'uuid', 'NO'], ['title', 'character varying', 'NO'], ['author', 'character varying', 'NO'],
            ['isbn', 'character', 'NO'], ['category_id', 'uuid', 'NO'], ['total_copies', 'integer', 'NO'],
            ['available_copies', 'integer', 'NO'], ['cover_key', 'character varying', 'YES'],
            ['deleted_at', 'timestamp with time zone', 'YES'],
        ],
        'category' => [['id', 'uuid', 'NO'], ['name', 'character varying', 'NO']],
        'loan' => [
            ['id', 'uuid', 'NO'], ['member_id', 'uuid', 'NO'], ['loaned_by_user_id', 'uuid', 'NO'],
            ['loaned_at', 'timestamp with time zone', 'NO'],
        ],
        'loan_item' => [
            ['id', 'uuid', 'NO'], ['loan_id', 'uuid', 'NO'], ['book_id', 'uuid', 'NO'],
            ['book_title', 'character varying', 'NO'], ['isbn', 'character', 'NO'],
            ['category_name', 'character varying', 'NO'], ['due_at', 'timestamp with time zone', 'NO'],
            ['returned_at', 'timestamp with time zone', 'YES'],
        ],
        'member' => [
            ['id', 'uuid', 'NO'], ['full_name', 'character varying', 'NO'], ['document_number', 'character varying', 'NO'],
        ],
        'user' => [
            ['id', 'uuid', 'NO'], ['username', 'character varying', 'NO'],
            ['password_hash', 'character varying', 'NO'], ['role', 'character varying', 'NO'],
        ],
    ];

    public function test_columns_match_data_model_and_have_no_default(): void
    {
        $rows = DB::select("SELECT table_name, column_name, data_type, is_nullable, column_default
            FROM information_schema.columns WHERE table_schema = 'library' ORDER BY 1, ordinal_position");

        $actual = [];
        foreach ($rows as $r) {
            $this->assertNull($r->column_default, "{$r->table_name}.{$r->column_name} must not have a DEFAULT");
            $actual[$r->table_name][] = [$r->column_name, $r->data_type, $r->is_nullable];
        }

        $this->assertSame(self::COLUMNS, $actual);
    }

    public function test_foreign_keys_and_checks_are_declared(): void
    {
        $defs = collect(DB::select("SELECT c.conname, pg_get_constraintdef(c.oid) AS def
            FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library' AND c.contype IN ('f', 'c')"))->pluck('def', 'conname');

        $this->assertFkTo('category', 'RESTRICT', $defs['fk_book_category']);
        $this->assertFkTo('member', 'RESTRICT', $defs['fk_loan_member']);
        $this->assertFkTo('"user"', 'RESTRICT', $defs['fk_loan_user']);
        $this->assertFkTo('loan', 'CASCADE', $defs['fk_loan_item_loan']);
        $this->assertFkTo('book', 'RESTRICT', $defs['fk_loan_item_book']);
        $this->assertArrayHasKey('ck_book_available_range', $defs);
        $this->assertArrayHasKey('ck_book_total_positive', $defs);
        $this->assertArrayHasKey('ck_user_role', $defs);
        $this->assertCount(8, $defs, 'exactly 5 FKs and 3 CHECKs; category_name has no FK');
    }

    public function test_indexes_are_declared(): void
    {
        $idx = collect(DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'library'"))
            ->pluck('indexdef', 'indexname');

        foreach (['ux_category_name', 'ux_member_document_number', 'ux_user_username', 'ux_loan_item_loan_book', 'ux_book_isbn_active'] as $u) {
            $this->assertStringContainsString('UNIQUE', $idx[$u], $u);
        }
        $this->assertStringContainsString('(isbn) WHERE (deleted_at IS NULL)', $idx['ux_book_isbn_active']);
        $this->assertStringContainsString('(category_id, title) WHERE (deleted_at IS NULL)', $idx['ix_book_category_title_active']);
        $this->assertStringContainsString('(loaned_at)', $idx['ix_loan_loaned_at']);
        $this->assertStringContainsString('(book_id)', $idx['ix_loan_item_book_id']);
        $this->assertStringContainsString('(due_at) WHERE (returned_at IS NULL)', $idx['ix_loan_item_due_open']);
    }

    public function test_five_categories_are_seeded_with_fixed_ids(): void
    {
        $this->assertSame([
            '22222222-2222-4222-8222-222222222222' => 'Ciencia',
            '44444444-4444-4444-8444-444444444444' => 'Historia',
            '55555555-5555-4555-8555-555555555555' => 'Infantil',
            '11111111-1111-4111-8111-111111111111' => 'Novela',
            '33333333-3333-4333-8333-333333333333' => 'Tecnología',
        ], DB::table('library.category')->orderBy('name')->pluck('name', 'id')->all());
    }

    public function test_engine_rejects_rows_that_break_the_checks(): void
    {
        $this->assertRejected(fn () => $this->insertBook(total: 0, available: 0));   // ck_book_total_positive
        $this->assertRejected(fn () => $this->insertBook(total: 2, available: 3));   // ck_book_available_range
        $this->assertRejected(fn () => $this->insertBook(total: 2, available: -1));  // ck_book_available_range
        $this->assertRejected(fn () => DB::table('library.user')->insert([
            'id' => (string) Str::uuid(), 'username' => 'x', 'password_hash' => 'h', 'role' => 'superuser',
        ]));                                                                         // ck_user_role

        $this->insertBook(total: 2, available: 2);
        $this->assertSame(1, DB::table('library.book')->count());
    }

    public function test_isbn_is_unique_only_among_active_books(): void
    {
        $this->insertBook(isbn: '9780307474728', deletedAt: '2026-11-01T00:00:00Z');
        $this->insertBook(isbn: '9780307474728');
        $this->assertRejected(fn () => $this->insertBook(isbn: '9780307474728'));
    }

    private function insertBook(int $total = 1, int $available = 1, string $isbn = '9780000000001', ?string $deletedAt = null): void
    {
        DB::table('library.book')->insert([
            'id' => (string) Str::uuid(), 'title' => 'T', 'author' => 'A', 'isbn' => $isbn,
            'category_id' => '11111111-1111-4111-8111-111111111111',
            'total_copies' => $total, 'available_copies' => $available, 'cover_key' => null, 'deleted_at' => $deletedAt,
        ]);
    }

    /** pg_get_constraintdef omits the schema when it is on the search_path, so accept both forms. */
    private function assertFkTo(string $table, string $onDelete, string $def): void
    {
        $this->assertMatchesRegularExpression(
            '/REFERENCES (library\.)?'.preg_quote($table, '/').'\(id\) ON DELETE '.$onDelete.'$/',
            $def,
        );
    }

    /** Runs the insert in a savepoint so a rejected row does not abort the test transaction. */
    private function assertRejected(callable $insert): void
    {
        DB::beginTransaction();
        try {
            $insert();
            $this->fail('The engine accepted a row it must reject.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        } finally {
            DB::rollBack();
        }
    }
}
