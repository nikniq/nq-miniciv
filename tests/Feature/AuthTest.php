<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use App\Notifications\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
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

    public function test_visitor_signs_up_with_just_an_email_link(): void
    {
        Notification::fake();
        $this->get(route('register'))->assertOk()->assertSee('Email me a sign-up link')->assertDontSee('Confirm password');

        $this->post(route('magic.send'), ['email' => 'Ada.Lovelace@Example.com'])->assertOk()->assertSee('Check your inbox');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada.lovelace@example.com']);

        $url = null;
        Notification::assertSentOnDemand(MagicLink::class, function (MagicLink $n) use (&$url) {
            $url = $n->url;

            return $n->isNew;
        });

        $this->openLink($url)->assertRedirect(route('games.index'));
        $this->assertAuthenticated();
        $user = User::where('email', 'ada.lovelace@example.com')->firstOrFail();
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->isGuest());

        // Single use: the same link is refused afterwards.
        $this->post(route('logout'));
        $this->get($url)->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->post($url)->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_keeps_games_when_claiming_by_email(): void
    {
        $this->post(route('play'))->assertRedirect();
        $guest = auth()->user();
        $this->assertTrue($guest->isGuest());
        $game = Game::firstOrFail();

        $url = URL::temporarySignedRoute('magic.verify', now()->addMinutes(30), ['email' => 'ruler@example.com', 'guest' => $guest->id]);
        $this->openLink($url)->assertRedirect(route('games.index'));
        $this->assertAuthenticatedAs($guest->fresh());
        $this->assertSame('ruler@example.com', $guest->fresh()->email);
        $this->assertFalse($guest->fresh()->isGuest());
        $this->assertSame($guest->id, $game->fresh()->user_id);
    }

    public function test_guest_games_move_to_an_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'old@example.com']);
        $this->post(route('play'))->assertRedirect();
        $guest = auth()->user();
        $game = Game::firstOrFail();

        $url = URL::temporarySignedRoute('magic.verify', now()->addMinutes(30), ['email' => 'old@example.com', 'guest' => $guest->id]);
        $this->openLink($url)->assertRedirect(route('games.index'));
        $this->assertAuthenticatedAs($existing);
        $this->assertSame($existing->id, $game->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
    }

    public function test_tampered_or_expired_links_are_refused(): void
    {
        $url = URL::temporarySignedRoute('magic.verify', now()->subMinute(), ['email' => 'x@example.com']);
        $this->get($url)->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->get(route('magic.verify', ['email' => 'x@example.com']))->assertRedirect(route('login'));
        $this->assertGuest();
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

    public function test_a_link_cannot_attach_someone_elses_guest_account(): void
    {
        // The attacker plays as a guest and requests a link for a victim's address.
        $this->post(route('play'))->assertRedirect();
        $attacker = auth()->user();
        $url = URL::temporarySignedRoute('magic.verify', now()->addMinutes(30), ['email' => 'victim@example.com', 'guest' => $attacker->id]);
        auth()->logout();

        // The victim opens it in their own browser: they get a fresh account, not the attacker's.
        $this->openLink($url)->assertRedirect(route('games.index'));
        $victim = User::where('email', 'victim@example.com')->firstOrFail();
        $this->assertNotSame($attacker->id, $victim->id);
        $this->assertTrue($attacker->fresh()->isGuest());
        $this->assertAuthenticatedAs($victim);
    }

    public function test_opening_the_link_does_not_use_it_up(): void
    {
        // A mail scanner fetching the link must change nothing.
        $url = URL::temporarySignedRoute('magic.verify', now()->addMinutes(30), ['email' => 'scan@example.com']);
        $this->get($url)->assertOk()->assertSee('Sign in');
        $this->get($url)->assertOk();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'scan@example.com']);

        $this->openLink($url)->assertRedirect(route('games.index'));
        $this->assertAuthenticated();
    }

    private function openLink(string $url): TestResponse
    {
        $this->get($url)->assertOk()->assertSee('Sign in');

        return $this->post($url);
    }

    public function test_nickname_check_and_change(): void
    {
        User::factory()->create(['name' => 'Cleopatra']);
        $user = User::factory()->create(['name' => 'Ada']);
        $this->actingAs($user);

        $this->get(route('profile'))->assertOk()->assertSee('Nickname');
        $this->getJson(route('profile.nickname.check', ['nickname' => 'cleopatra']))->assertJson(['available' => false, 'message' => 'That nickname is taken.']);
        $this->getJson(route('profile.nickname.check', ['nickname' => 'ab']))->assertJson(['available' => false]);
        $this->getJson(route('profile.nickname.check', ['nickname' => 'Admin']))->assertJson(['available' => false]);
        $this->getJson(route('profile.nickname.check', ['nickname' => '<b>x</b>']))->assertJson(['available' => false]);
        $this->getJson(route('profile.nickname.check', ['nickname' => '  Queen   Ada ']))->assertJson(['available' => true, 'nickname' => 'Queen Ada']);

        $this->put(route('profile.nickname'), ['nickname' => 'CLEOPATRA'])->assertSessionHasErrors('nickname');
        $this->assertSame('Ada', $user->fresh()->name);
        $this->put(route('profile.nickname'), ['nickname' => '  Queen   Ada '])->assertSessionHasNoErrors();
        $this->assertSame('Queen Ada', $user->fresh()->name);
    }

    public function test_profile_menu_shows_google_photo_or_initial(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ada', 'avatar' => 'https://lh3.googleusercontent.com/a/photo']));
        $this->get(route('games.index'))->assertOk()->assertSee('Profile menu')->assertSee('https://lh3.googleusercontent.com/a/photo')->assertSee('Log out');

        $this->actingAs(User::factory()->create(['name' => 'zeno', 'avatar' => null]));
        $this->get(route('games.index'))->assertOk()->assertSee('Profile menu')->assertSee('>Z</span>', false);
    }
}
