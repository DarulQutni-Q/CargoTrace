<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_issues_a_customer_account_and_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Dewi Anggraini',
            'email' => 'dewi@example.com',
            'password' => 'supreme-password',
            'password_confirmation' => 'supreme-password',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
        $this->assertSame('CUSTOMER', $response->json('user.role'));
    }

    public function test_login_returns_a_sanctum_token(): void
    {
        $user = User::factory()->operator()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('token'));
    }

    public function test_invalid_login_returns_422_without_leaking_details(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
        $response->assertJsonMissing(['password']);
    }

    public function test_protected_endpoints_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/shipments')->assertUnauthorized();
        $this->postJson('/api/shipments', [])->assertUnauthorized();
    }

    public function test_token_grants_access_to_the_user_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        // The request guard caches its user for the lifetime of the app
        // instance, which spans several requests inside one test. Real
        // requests never share it, so drop the cache before re-authing.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }
}
