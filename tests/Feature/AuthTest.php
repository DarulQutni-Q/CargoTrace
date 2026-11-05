<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_customer_account_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Dewi Lestari',
            'email' => 'dewi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', User::ROLE_CUSTOMER)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'role'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'dewi@example.com', 'role' => 'CUSTOMER']);
    }

    public function test_register_rejects_duplicate_email_and_short_password(): void
    {
        User::create(['name' => 'A', 'email' => 'dup@example.com', 'password' => 'password123', 'role' => User::ROLE_CUSTOMER]);

        $this->postJson('/api/v1/register', [
            'name' => 'B',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/register', [
            'name' => 'C',
            'email' => 'short@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable();
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::create(['name' => 'Aria', 'email' => 'admin@cargotrace.io', 'password' => 'password123', 'role' => User::ROLE_ADMIN]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@cargotrace.io',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'admin@cargotrace.io')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_wrong_password_and_unknown_email_with_401(): void
    {
        User::create(['name' => 'Aria', 'email' => 'admin@cargotrace.io', 'password' => 'password123', 'role' => User::ROLE_ADMIN]);

        $this->postJson('/api/v1/login', [
            'email' => 'admin@cargotrace.io',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/login', [
            'email' => 'nobody@cargotrace.io',
            'password' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_protected_endpoints_require_a_token_and_logout_revokes_it(): void
    {
        $user = User::create(['name' => 'Aria', 'email' => 'admin@cargotrace.io', 'password' => 'password123', 'role' => User::ROLE_ADMIN]);

        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->getJson('/api/v1/shipments')->assertUnauthorized();

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@cargotrace.io');

        // Same guard-caching note as CreatesCorridor::authHeaders(): the
        // guard resolved for the previous request must be forgotten, or the
        // next request would reuse the cached user instead of re-checking
        // the (now revoked) token.
        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/logout')
            ->assertOk();

        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }
}
