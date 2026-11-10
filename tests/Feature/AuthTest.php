<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_visitor_can_register_and_is_logged_in(): void
    {
        $this->get(route('register'))->assertOk();

        $this->post(route('register'), [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('games.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect(route('games.index'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_games_require_login(): void
    {
        $this->get(route('games.index'))->assertRedirect(route('login'));
    }

    public function test_google_button_is_hidden_until_configured(): void
    {
        config(['services.google.client_id' => null]);
        $this->get(route('login'))->assertDontSee('Continue with Google');

        config(['services.google.client_id' => 'abc']);
        $this->get(route('login'))->assertSee('Continue with Google');

        config(['services.google.client_id' => null]);
        $this->get(route('google.redirect'))->assertRedirect(route('login'));
    }
}
