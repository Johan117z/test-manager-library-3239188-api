<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** T-17 evidence: on an empty database, startup creates the admin from the environment and you can log in as admin. */
class InitialAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_startup_creates_admin_from_environment_and_admin_can_log_in(): void
    {
        $this->assertSame(0, DB::table('library.user')->count(), 'the database starts without users');

        $this->artisan('library:ensure-admin')->expectsOutput('Initial admin created.')->assertSuccessful();

        $this->postJson('/api/v1/auth/login', ['username' => config('library.admin_username'), 'password' => config('library.admin_password')])
            ->assertOk()
            ->assertJson(['role' => 'admin']);
    }

    public function test_startup_is_idempotent_and_never_changes_an_existing_admin(): void
    {
        $this->artisan('library:ensure-admin')->assertSuccessful();
        $hash = DB::table('library.user')->value('password_hash');

        config(['library.admin_password' => 'a-different-password']);
        $this->artisan('library:ensure-admin')->expectsOutput('Initial admin already exists; nothing changed.')->assertSuccessful();

        $this->assertSame(1, DB::table('library.user')->count());
        $this->assertSame($hash, DB::table('library.user')->value('password_hash'));
    }

    public function test_startup_fails_loudly_without_admin_credentials(): void
    {
        config(['library.admin_password' => null]);

        $this->artisan('library:ensure-admin')->assertFailed();
        $this->assertSame(0, DB::table('library.user')->count());
    }
}
