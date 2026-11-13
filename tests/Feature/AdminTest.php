<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return tap(User::factory()->create(['email' => 'boss@example.com']), fn ($u) => $u->forceFill(['is_admin' => true])->save());
    }

    public function test_only_admins_can_open_the_admin_area(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        foreach (['admin.dashboard', 'admin.users', 'admin.games'] as $r) {
            $this->get(route($r))->assertForbidden();
        }
        $this->get(route('games.index'))->assertDontSee('🛡️ Admin');

        // A guest flagged by mistake is still refused.
        $this->actingAs(tap(User::factory()->create(['is_guest' => true]), fn ($u) => $u->forceFill(['is_admin' => true])->save()));
        $this->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs($this->admin());
        foreach (['admin.dashboard', 'admin.users', 'admin.games'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('games.index'))->assertSee('🛡️ Admin');
    }

    public function test_is_admin_is_not_mass_assignable(): void
    {
        $u = User::create(['name' => 'Sneaky', 'email' => 's@example.com', 'is_admin' => true]);
        $this->assertFalse($u->fresh()->isAdmin());
    }

    public function test_admin_actions(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $player = User::factory()->create(['name' => 'Rude Word']);
        $this->post(route('play'));
        $this->actingAs($admin);

        $this->get(route('admin.users', ['q' => 'rude']))->assertSee('Rude Word');
        $this->post(route('admin.users.admin', $player))->assertSessionHasNoErrors();
        $this->assertTrue($player->fresh()->isAdmin());
        $this->post(route('admin.users.admin', $player));
        $this->assertFalse($player->fresh()->isAdmin());

        $this->post(route('admin.users.admin', $admin))->assertSessionHasErrors('admin');
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('admin');

        $this->post(route('admin.users.reset-name', $player));
        $this->assertSame('Ruler '.$player->id, $player->fresh()->name);

        $game = Game::firstOrFail();
        $this->get(route('admin.games'))->assertSee($game->name);
        $this->delete(route('admin.games.destroy', $game));
        $this->assertModelMissing($game);

        $this->delete(route('admin.users.destroy', $player));
        $this->assertModelMissing($player);
    }

    public function test_admin_commands(): void
    {
        $u = User::factory()->create(['email' => 'ops@example.com']);
        $this->artisan('admin:grant', ['email' => 'OPS@example.com'])->assertSuccessful();
        $this->assertTrue($u->fresh()->isAdmin());
        $this->artisan('admin:list')->expectsOutputToContain('ops@example.com')->assertSuccessful();
        $this->artisan('admin:revoke', ['email' => 'ops@example.com'])->assertSuccessful();
        $this->assertFalse($u->fresh()->isAdmin());
        $this->artisan('admin:grant', ['email' => 'nobody@example.com'])->assertFailed();

        config(['auth.admin_emails' => ['ops@example.com', 'later@example.com']]);
        $this->artisan('admin:sync')->expectsOutputToContain('not yet registered: later@example.com')->assertSuccessful();
        $this->assertTrue($u->fresh()->isAdmin());
    }
}
