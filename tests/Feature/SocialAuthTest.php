<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nikniq.client_id' => 'cid', 'services.nikniq.client_secret' => 'secret']);
    }

    private function fakeNikniq(array $raw): void
    {
        $user = (new SocialUser)->setRaw($raw)->map(['id' => $raw['id'], 'name' => $raw['name'] ?? null, 'email' => $raw['email'] ?? null, 'avatar' => $raw['avatar'] ?? null]);
        Socialite::shouldReceive('driver')->with('nikniq')->andReturn(\Mockery::mock(['user' => $user]));
    }

    public function test_nikniq_button_is_hidden_until_configured(): void
    {
        config(['services.nikniq.client_id' => null]);
        $this->get(route('login'))->assertDontSee('Continue with nikniq');
        $this->get(route('nikniq.redirect'))->assertRedirect(route('login'));

        config(['services.nikniq.client_id' => 'cid']);
        $this->get(route('login'))->assertSee('Continue with nikniq');
        $this->get(route('register'))->assertSee('Continue with nikniq');
    }

    public function test_redirect_goes_to_nikniq_authorize_endpoint(): void
    {
        $location = $this->get(route('nikniq.redirect'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://nikniq.com/oauth/authorize?', $location);
        $this->assertStringContainsString('client_id=cid', $location);
        $this->assertStringContainsString('code_challenge=', $location);
    }

    public function test_new_player_signs_up_with_nikniq(): void
    {
        $this->fakeNikniq(['id' => 42, 'name' => 'Nik Player', 'email' => 'Nik@Example.com', 'email_verified_at' => '2026-01-01', 'avatar' => 'https://nikniq.com/a.png']);
        $this->get(route('nikniq.callback'))->assertRedirect(route('games.index'));
        $user = User::where('nikniq_id', '42')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('nik@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('https://nikniq.com/a.png', $user->avatar);
    }

    public function test_unverified_nikniq_email_cannot_take_over_an_account(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com']);
        $this->fakeNikniq(['id' => 7, 'name' => 'Mallory', 'email' => 'victim@example.com']);
        $this->get(route('nikniq.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($victim->fresh()->nikniq_id);
    }

    public function test_verified_nikniq_email_links_the_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $this->fakeNikniq(['id' => 9, 'email' => 'me@example.com', 'email_verified' => true]);
        $this->get(route('nikniq.callback'))->assertRedirect(route('games.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('9', $user->fresh()->nikniq_id);
    }

    public function test_guest_keeps_games_when_signing_in_with_nikniq(): void
    {
        $this->post(route('play'));
        $guest = auth()->user();
        $game = Game::firstOrFail();
        $this->fakeNikniq(['id' => 5, 'name' => 'Ruler', 'email' => 'ruler@example.com', 'email_verified_at' => '2026-01-01']);
        $this->get(route('nikniq.callback'))->assertRedirect(route('games.index'));
        $this->assertAuthenticatedAs($guest->fresh());
        $this->assertFalse($guest->fresh()->isGuest());
        $this->assertSame($guest->id, $game->fresh()->user_id);
    }

    public function test_layout_links_back_to_nikniq(): void
    {
        $this->get(route('home'))->assertSee('Back to nikniq.com')->assertSee('Register');
    }
}
