<?php

namespace Tests\Feature;

use App\Application\Ports\Clock;
use App\Domain\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\Support;
use Tests\Concerns\ActsAsUsers;
use Tests\Fakes\FixedClock;
use Tests\TestCase;

/** T-10 evidence: return twice -> ITEM_ALREADY_RETURNED and the copy is added once; overdue lines with days late. */
class ReturnAndOverdueTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_return_once_overdue_listing_and_computed_status(): void
    {
        $clock = new FixedClock('2026-10-01T10:00:00Z');
        $this->app->instance(Clock::class, $clock);
        $admin = $this->as(Role::Admin);
        $book = fn (string $t, string $isbn) => $this->postJson('/api/v1/books', ['title' => $t, 'author' => 'A', 'isbn' => $isbn, 'category_id' => '33333333-3333-4333-8333-333333333333', 'total_copies' => 1], $admin)->json('id');
        [$a, $b] = [$book('Libro A', '9780000000011'), $book('Libro B', '9780000000012')];
        $member = $this->postJson('/api/v1/members', ['full_name' => 'Ana Ruiz', 'document_number' => 'CC-9'], $admin)->json('id');
        $loan = $this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [['book_id' => $a], ['book_id' => $b]]], $admin)->assertCreated()->json();
        $itemA = collect($loan['items'])->firstWhere('book_id', $a)['id'];

        $clock->advance('+16 days +1 hour'); // now 2026-10-17T11:00Z: due 2026-10-15T10:00Z, 2 days 1 hour late -> 3 started days
        $admin = $this->as(Role::Admin);

        $this->getJson('/api/v1/loans/overdue', $admin)->assertOk()
            ->assertJson(['total' => 2, 'items' => [['loan_id' => $loan['id'], 'member_name' => 'Ana Ruiz', 'due_at' => '2026-10-15T10:00:00Z', 'days_late' => 3]]]);
        $this->getJson("/api/v1/loans/{$loan['id']}", $admin)->assertJson(['status' => 'overdue']);

        $this->postJson("/api/v1/loans/{$loan['id']}/items/{$itemA}/return", [], $admin)
            ->assertOk()->assertJson(['id' => $itemA, 'returned_at' => '2026-10-17T11:00:00Z']);
        $this->postJson("/api/v1/loans/{$loan['id']}/items/{$itemA}/return", [], $admin)
            ->assertStatus(409)->assertJson(['code' => 'ITEM_ALREADY_RETURNED']);
        $this->assertSame(1, (int) DB::table('library.book')->where('id', $a)->value('available_copies'), 'added once, never above total');

        $itemB = collect($loan['items'])->firstWhere('book_id', $b)['id'];
        $this->postJson("/api/v1/loans/{$loan['id']}/items/{$itemB}/return", [], $admin)->assertOk();
        $this->getJson("/api/v1/loans/{$loan['id']}", $admin)->assertJson(['status' => 'closed']);
        $this->getJson('/api/v1/loans/overdue', $admin)->assertJson(['total' => 0]);
        $this->getJson("/api/v1/loans?status=closed&member_id={$member}&from=2026-10-01&to=2026-10-01", $admin)->assertJson(['total' => 1]);
        $this->getJson('/api/v1/loans?from=2026-10-02&to=2026-10-01', $admin)->assertStatus(422)->assertJson(['code' => 'INVALID_DATE_RANGE']);
        $this->postJson("/api/v1/loans/{$loan['id']}/items/".$loan['id'].'/return', [], $admin)->assertNotFound();
    }
}
