<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Create an account');
    }

    public function test_registration_creates_a_customer_account_and_signs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Citra Hapsari',
            'email' => 'citra@example.com',
            'password' => 'supreme-password',
            'password_confirmation' => 'supreme-password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $user = User::whereEmail('citra@example.com')->firstOrFail();
        $this->assertSame('CUSTOMER', $user->role->value);
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Citra Hapsari',
            'email' => 'citra@example.com',
            'password' => 'supreme-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Sign in');
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->operator()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
