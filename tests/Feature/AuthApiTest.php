<?php

namespace Tests\Feature;

use App\Domain\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsUsers;
use Tests\TestCase;

/** HU-01, HU-02 and RNF-3 through HTTP with the real adapters (Argon2id, JWT, PostgreSQL). */
class AuthApiTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_login_returns_bearer_token_valid_8_hours(): void
    {
        $this->createUser('ana', 'clave-ana-123', Role::Librarian);

        $this->postJson('/api/v1/auth/login', ['username' => 'Ana ', 'password' => 'clave-ana-123'])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'role'])
            ->assertJson(['token_type' => 'Bearer', 'expires_in' => 28800, 'role' => 'librarian']);
    }

    public function test_invalid_credentials_do_not_reveal_which_part_failed(): void
    {
        $this->createUser('ana', 'clave-ana-123', Role::Librarian);

        $a = $this->postJson('/api/v1/auth/login', ['username' => 'ana', 'password' => 'mala'])->assertStatus(401);
        $b = $this->postJson('/api/v1/auth/login', ['username' => 'nadie', 'password' => 'clave-ana-123'])->assertStatus(401);

        $a->assertJson(['code' => 'INVALID_CREDENTIALS', 'details' => []]);
        $this->assertSame($a->json(), $b->json());
    }

    public function test_protected_route_without_token_is_401_and_with_wrong_role_is_403(): void
    {
        $body = ['username' => 'luis', 'password' => 'x', 'role' => 'librarian'];

        $this->postJson('/api/v1/users', $body)->assertStatus(401)->assertJson(['code' => 'INVALID_CREDENTIALS']);
        $this->postJson('/api/v1/users', $body, ['Authorization' => 'Bearer not-a-token'])->assertStatus(401);
        $this->postJson('/api/v1/users', $body, $this->as(Role::Librarian))->assertStatus(403)->assertJson(['code' => 'FORBIDDEN_ROLE']);
    }

    public function test_admin_creates_librarian_without_exposing_password(): void
    {
        $admin = $this->as(Role::Admin);

        $response = $this->postJson('/api/v1/users', ['username' => 'Ana ', 'password' => 'Secreta-123', 'role' => 'librarian'], $admin)
            ->assertCreated()
            ->assertJson(['username' => 'ana', 'role' => 'librarian']);
        $this->assertSame(['id', 'username', 'role'], array_keys($response->json()));

        $this->postJson('/api/v1/users', ['username' => 'ANA', 'password' => 'x', 'role' => 'librarian'], $admin)
            ->assertStatus(409)->assertJson(['code' => 'USERNAME_TAKEN']);
        $this->postJson('/api/v1/users', ['username' => 'jefe', 'password' => 'x', 'role' => 'admin'], $admin)
            ->assertStatus(422)->assertJson(['code' => 'VALIDATION_ERROR', 'details' => [['field' => 'role']]]);
        $this->postJson('/api/v1/users', ['username' => '', 'role' => 'librarian'], $admin)
            ->assertStatus(422)->assertJsonPath('details.0.field', 'username');

        $this->assertStringStartsWith('$argon2id$', DB::table('library.user')->where('username', 'ana')->value('password_hash'));
    }

    /** T-14 evidence (RNF-3): the password never shows up in a response or in the log. */
    public function test_password_never_appears_in_responses_or_logs(): void
    {
        $log = storage_path('logs/laravel.log');
        $before = is_file($log) ? filesize($log) : 0;
        $secret = 'Pw-'.bin2hex(random_bytes(6));
        $admin = $this->as(Role::Admin);

        $responses = [
            $this->postJson('/api/v1/users', ['username' => 'eva', 'password' => $secret, 'role' => 'librarian'], $admin),
            $this->postJson('/api/v1/users', ['username' => 'eva', 'password' => $secret, 'role' => 'librarian'], $admin),
            $this->postJson('/api/v1/auth/login', ['username' => 'eva', 'password' => $secret]),
            $this->postJson('/api/v1/auth/login', ['username' => 'eva', 'password' => $secret.'x']),
        ];

        foreach ($responses as $r) {
            $this->assertStringNotContainsString($secret, $r->getContent());
            $this->assertStringNotContainsString('password', $r->getContent());
        }
        $newLog = is_file($log) ? (string) file_get_contents($log, false, null, $before) : '';
        $this->assertStringNotContainsString($secret, $newLog);
    }
}
