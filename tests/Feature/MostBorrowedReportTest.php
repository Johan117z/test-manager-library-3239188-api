<?php

namespace Tests\Feature;

use App\Application\Ports\Clock;
use App\Domain\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsUsers;
use Tests\Fakes\FixedClock;
use Tests\TestCase;

/** T-11 evidence: grouped by frozen values (old title kept after rename), inclusive range, admin only. */
class MostBorrowedReportTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_report_keeps_frozen_title_counts_inclusive_end_day_and_is_admin_only(): void
    {
        $clock = new FixedClock('2026-10-31T23:30:00Z'); // late on the last day of the range
        $this->app->instance(Clock::class, $clock);
        $admin = $this->as(Role::Admin);
        $book = $this->postJson('/api/v1/books', ['title' => 'Cien años de soledad', 'author' => 'GGM', 'isbn' => '9780307474728', 'category_id' => '11111111-1111-4111-8111-111111111111', 'total_copies' => 5], $admin)->json('id');
        foreach (['CC-1', 'CC-2'] as $doc) {
            $member = $this->postJson('/api/v1/members', ['full_name' => "Socio {$doc}", 'document_number' => $doc], $admin)->json('id');
            $this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [['book_id' => $book]]], $admin)->assertCreated();
        }

        // Rename after lending: a closed period does not change (CA-10.4).
        $this->putJson("/api/v1/books/{$book}", ['title' => 'Título nuevo', 'author' => 'GGM', 'category_id' => '11111111-1111-4111-8111-111111111111', 'total_copies' => 5], $admin)->assertOk();

        $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $admin)->assertOk()->assertExactJson([
            'from' => '2026-10-01', 'to' => '2026-10-31',
            'rows' => [['book_id' => $book, 'book_title' => 'Cien años de soledad', 'category_name' => 'Novela', 'times_borrowed' => 2]],
        ]);
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-11-01&to=2026-11-30', $admin)->assertOk()->assertJson(['rows' => []]);
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-31&to=2026-10-01', $admin)->assertStatus(422)->assertJson(['code' => 'INVALID_DATE_RANGE']);
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01', $admin)->assertStatus(422)->assertJson(['code' => 'VALIDATION_ERROR']);
        $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $this->as(Role::Librarian))->assertStatus(403);
    }
}
