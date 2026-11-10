<?php

namespace Tests\Feature;

use App\Game\Rules;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function startGame(User $user): Game
    {
        $this->actingAs($user)
            ->post(route('games.store'), ['empire' => 'Rome', 'rivals' => 2])
            ->assertRedirect();

        return Game::with(['empires.cities', 'cities', 'tiles'])->firstOrFail();
    }

    public function test_a_new_game_has_a_map_and_a_capital_for_every_empire(): void
    {
        $game = $this->startGame(User::factory()->create());

        $this->assertCount(Rules::MAP_WIDTH * Rules::MAP_HEIGHT, $game->tiles);
        $this->assertCount(3, $game->empires);
        $this->assertCount(3, $game->cities);
        $this->assertTrue($game->playerEmpire()->cities->first()->is_capital);
        $this->assertGreaterThan(0, $game->tiles->where('empire_id', $game->playerEmpire()->id)->count());
    }

    public function test_the_game_screen_renders_and_the_turn_advances(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);

        $this->actingAs($user)->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('End turn')
            ->assertSee('Rome');

        $this->actingAs($user)->post(route('games.end-turn', $game))->assertRedirect();
        $this->assertSame(2, $game->fresh()->turn);
    }

    public function test_player_can_set_research_and_a_build_and_found_a_city(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $capital = $player->cities->first();

        $this->actingAs($user)->post(route('games.research', $game), ['tech' => 'agriculture'])->assertRedirect();
        $this->assertSame('agriculture', $player->fresh()->current_tech);

        $this->actingAs($user)->post(route('games.build', [$game, $capital]), ['build' => 'settlers'])->assertRedirect();
        $this->assertSame('settlers', $capital->fresh()->current_build);

        // A granary needs Agriculture, which is not known yet.
        $this->actingAs($user)->post(route('games.build', [$game, $capital]), ['build' => 'granary'])
            ->assertSessionHas('error');

        // The player starts with one band of settlers: found a city on a legal tile.
        $actions = app(\App\Game\Actions::class);
        $site = $game->tiles->first(fn ($t) => $actions->foundingProblem($game, $player, $t, $game->tiles) === null);
        $this->assertNotNull($site, 'expected at least one legal city site');

        $this->actingAs($user)->post(route('games.found', $game), ['tile' => $site->id])->assertSessionHas('status');
        $this->assertCount(2, $player->fresh()->cities);
        $this->assertSame(0, $player->fresh()->settlers);

        // No settlers left: founding again is refused.
        $this->actingAs($user)->post(route('games.found', $game), ['tile' => $site->id])->assertSessionHas('error');
    }

    public function test_research_completes_and_unlocks_buildings_over_turns(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $this->actingAs($user)->post(route('games.research', $game), ['tech' => 'agriculture']);

        for ($i = 0; $i < 25 && ! $player->fresh()->knows('agriculture'); $i++) {
            $this->actingAs($user)->post(route('games.end-turn', $game));
        }

        $this->assertTrue($player->fresh()->knows('agriculture'));
        $capital = $player->cities()->first();
        $this->actingAs($user)->post(route('games.build', [$game, $capital]), ['build' => 'granary'])->assertSessionMissing('error');
    }

    public function test_other_users_cannot_see_or_act_on_a_game(): void
    {
        $game = $this->startGame(User::factory()->create());
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('games.show', $game))->assertForbidden();
        $this->actingAs($stranger)->post(route('games.end-turn', $game))->assertForbidden();
    }
}
