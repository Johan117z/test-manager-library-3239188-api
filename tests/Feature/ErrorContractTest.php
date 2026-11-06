<?php

namespace Tests\Feature;

use App\Domain\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\ActsAsUsers;
use Tests\TestCase;

/** T-16 evidence: every route of api-contract.md answers errors with the single body and the right status. */
class ErrorContractTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private const ID = '00000000-0000-4000-8000-000000000000';

    /** Every protected route of the contract, with the roles allowed. */
    private const ROUTES = [
        ['POST', '/users', ['admin']],
        ['GET', '/categories', ['admin', 'librarian']],
        ['GET', '/books', ['admin', 'librarian']],
        ['GET', '/books/'.self::ID, ['admin', 'librarian']],
        ['POST', '/books', ['admin']],
        ['PUT', '/books/'.self::ID, ['admin']],
        ['DELETE', '/books/'.self::ID, ['admin']],
        ['PUT', '/books/'.self::ID.'/cover', ['admin']],
        ['DELETE', '/books/'.self::ID.'/cover', ['admin']],
        ['GET', '/books/'.self::ID.'/cover', ['admin', 'librarian']],
        ['GET', '/members', ['admin', 'librarian']],
        ['GET', '/members/'.self::ID, ['admin', 'librarian']],
        ['POST', '/members', ['admin', 'librarian']],
        ['POST', '/loans', ['admin', 'librarian']],
        ['GET', '/loans', ['admin', 'librarian']],
        ['GET', '/loans/'.self::ID, ['admin', 'librarian']],
        ['POST', '/loans/'.self::ID.'/items/'.self::ID.'/return', ['admin', 'librarian']],
        ['GET', '/loans/overdue', ['admin', 'librarian']],
        ['GET', '/reports/most-borrowed', ['admin']],
    ];

    private function assertErrorBody(TestResponse $r, int $status, string $code): void
    {
        $r->assertStatus($status)->assertJson(['code' => $code]);
        $this->assertSame(['code', 'message', 'details'], array_keys($r->json()), 'single error body');
        $this->assertIsString($r->json('message'));
        $this->assertIsArray($r->json('details'));
        foreach (['trace', 'exception', 'file', 'SQLSTATE', '/app/'] as $leak) {
            $this->assertStringNotContainsString($leak, $r->getContent());
        }
    }

    public function test_every_protected_route_is_401_without_token_and_403_for_wrong_role(): void
    {
        $librarian = $this->as(Role::Librarian);
        foreach (self::ROUTES as [$method, $uri, $roles]) {
            $this->assertErrorBody($this->json($method, "/api/v1{$uri}"), 401, 'INVALID_CREDENTIALS');
            $this->assertErrorBody($this->json($method, "/api/v1{$uri}", [], ['Authorization' => 'Bearer x.y.z']), 401, 'INVALID_CREDENTIALS');
            if (! in_array('librarian', $roles, true)) {
                $this->assertErrorBody($this->json($method, "/api/v1{$uri}", [], $librarian), 403, 'FORBIDDEN_ROLE');
            }
        }
    }

    public function test_not_found_validation_and_unknown_routes_use_the_same_body(): void
    {
        $admin = $this->as(Role::Admin);

        foreach (['/books/', '/members/', '/loans/'] as $prefix) {
            $this->assertErrorBody($this->getJson("/api/v1{$prefix}".self::ID, $admin), 404, 'NOT_FOUND');
            $this->assertErrorBody($this->getJson("/api/v1{$prefix}not-a-uuid", $admin), 404, 'NOT_FOUND');
        }
        $this->assertErrorBody($this->postJson('/api/v1/loans/'.self::ID.'/items/'.self::ID.'/return', [], $admin), 404, 'NOT_FOUND');
        $this->assertErrorBody($this->postJson('/api/v1/books', [], $admin), 422, 'VALIDATION_ERROR');
        $this->assertErrorBody($this->postJson('/api/v1/members', ['full_name' => 'X'], $admin), 422, 'VALIDATION_ERROR');
        $this->assertErrorBody($this->getJson('/api/v1/loans?status=lost', $admin), 422, 'VALIDATION_ERROR');
        $this->assertErrorBody($this->getJson('/api/v1/books?category_id=bad', $admin), 422, 'VALIDATION_ERROR');
        $this->assertErrorBody($this->postJson('/api/v1/auth/login', []), 422, 'VALIDATION_ERROR');
        $this->assertErrorBody($this->getJson('/api/v1/nothing-here', $admin), 404, 'NOT_FOUND');
        $this->assertErrorBody($this->getJson('/api/v1/auth/login'), 404, 'NOT_FOUND'); // wrong method
    }

    public function test_unexpected_errors_are_500_with_the_same_body_and_no_internals(): void
    {
        Route::middleware('api')->get('/api/v1/__boom', fn () => throw new RuntimeException('SQLSTATE secret /app/x.php'));

        $this->assertErrorBody($this->getJson('/api/v1/__boom'), 500, 'INTERNAL_ERROR');
    }
}
