<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\CreateLibrarianService;
use App\Application\Auth\LoginService;
use App\Domain\Shared\BusinessRuleViolation;
use App\Domain\User\Role;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenIssuer;
use Tests\Fakes\InMemoryUserRepository;

/** T-07 evidence: HU-01 and HU-02 pass against fakes (no database, no framework). */
class AuthServicesTest extends TestCase
{
    private InMemoryUserRepository $users;
    private FakePasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->hasher = new FakePasswordHasher();
        $this->users->add(new User('u-1', 'ana', $this->hasher->hash('clave-ana'), Role::Librarian));
    }

    private function login(): LoginService
    {
        return new LoginService($this->users, $this->hasher, new FakeTokenIssuer());
    }

    private function creator(): CreateLibrarianService
    {
        return new CreateLibrarianService($this->users, $this->hasher);
    }

    public function test_ca_01_1_valid_credentials_return_8_hour_token_and_role(): void
    {
        $result = $this->login()->login(' Ana ', 'clave-ana');

        $this->assertSame('token:u-1:librarian', $result->accessToken);
        $this->assertSame(28800, $result->expiresIn);
        $this->assertSame('librarian', $result->role);
    }

    public function test_ca_01_2_wrong_password_and_unknown_user_fail_the_same_way(): void
    {
        $wrongPassword = $this->violation(fn () => $this->login()->login('ana', 'otra'));
        $unknownUser = $this->violation(fn () => $this->login()->login('nadie', 'clave-ana'));

        $this->assertSame('INVALID_CREDENTIALS', $wrongPassword->errorCode);
        $this->assertSame([$wrongPassword->errorCode, $wrongPassword->getMessage()], [$unknownUser->errorCode, $unknownUser->getMessage()]);
    }

    public function test_ca_02_1_and_02_2_librarian_is_created_with_normalized_username(): void
    {
        $user = $this->creator()->create('  Pedro ', 'secreta', 'librarian');

        $this->assertSame('pedro', $user->username);
        $this->assertSame(Role::Librarian, $user->role);
        $this->assertNotSame('secreta', $user->passwordHash);
        $this->assertSame($user, $this->users->findByUsername('pedro'));
    }

    public function test_ca_02_2_repeated_username_is_taken(): void
    {
        $this->assertSame('USERNAME_TAKEN', $this->violation(fn () => $this->creator()->create('ANA', 'x', 'librarian'))->errorCode);
    }

    public function test_ca_02_3_admin_role_cannot_be_requested(): void
    {
        $e = $this->violation(fn () => $this->creator()->create('nuevo', 'x', 'admin'));

        $this->assertSame('VALIDATION_ERROR', $e->errorCode);
        $this->assertSame('role', $e->details[0]['field']);
        $this->assertNull($this->users->findByUsername('nuevo'));
    }

    private function violation(callable $action): BusinessRuleViolation
    {
        try {
            $action();
        } catch (BusinessRuleViolation $e) {
            return $e;
        }
        $this->fail('Expected a BusinessRuleViolation.');
    }
}
