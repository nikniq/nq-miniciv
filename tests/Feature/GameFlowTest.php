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
            ->post(route('games.store'), ['empire' => 'Rome', 'rivals' => 2, 'size' => 'normal', 'difficulty' => 'prince'])
            ->assertRedirect();

        return Game::with(['empires.cities', 'cities', 'tiles'])->firstOrFail();
    }

    public function test_a_new_game_has_a_map_and_a_capital_for_every_empire(): void
    {
        $game = $this->startGame(User::factory()->create());

        $this->assertCount(Rules::MAP_SIZES['normal']['width'] * Rules::MAP_SIZES['normal']['height'], $game->tiles);
        $this->assertTrue($game->tiles->contains(fn ($t) => $t->river), 'maps have rivers');
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

    public function test_guest_can_play_without_an_account_and_register_later(): void
    {
        $this->post(route('play'))->assertRedirect();
        $this->assertAuthenticated();
        $guest = auth()->user();
        $this->assertTrue($guest->isGuest());
        $this->assertSame(1, Game::where('user_id', $guest->id)->count());

        $this->post(route('register'), [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('games.index'));

        $guest->refresh();
        $this->assertFalse($guest->isGuest());
        $this->assertSame('ada@example.com', $guest->email);
        $this->assertSame(1, Game::where('user_id', $guest->id)->count());
    }

    public function test_army_can_capture_a_bordering_enemy_city(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $actions = app(\App\Game\Actions::class);

        $enemyCity = $game->cities->first(fn ($c) => $c->empire_id !== $player->id);
        $enemy = $game->empires->firstWhere('id', $enemyCity->empire_id);

        // Make the enemy city border the player's land by handing the player
        // a tile next to it, then arm the player heavily.
        $centre = $game->tiles->firstWhere('id', $enemyCity->tile_id);
        $neighbour = $game->tiles->first(fn ($t) => $t->distanceTo($centre) === 2 && $t->empire_id === null);
        $neighbour->update(['empire_id' => $player->id]);
        $game->load('tiles');

        $this->assertSame('You have no army. Build units in a city first.', $actions->attackProblem($player, $enemyCity, $game->tiles));

        $player->update(['army' => 50]);

        // Peace forbids it; declare war, then take the city.
        $this->actingAs($user)->post(route('games.attack', [$game, $enemyCity]))->assertSessionHas('error');
        $this->actingAs($user)->post(route('games.war', [$game, $enemy]))->assertSessionHas('status');
        $this->assertTrue($game->fresh()->atWar($player->id, $enemy->id));
        $this->actingAs($user)->post(route('games.attack', [$game, $enemyCity]))->assertSessionHas('status');

        $enemyCity->refresh();
        $this->assertSame($player->id, $enemyCity->empire_id);
        $this->assertFalse($enemyCity->is_capital);
        $this->assertTrue($game->tiles()->where('city_id', $enemyCity->id)->get()->every(fn ($t) => $t->empire_id === $player->id));
        $this->assertTrue($enemy->fresh()->eliminated);
        $this->assertLessThan(50, $player->fresh()->army);
    }

    public function test_tile_panel_endpoint_returns_just_the_panel(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $capital = $game->playerEmpire()->cities->first();

        $this->actingAs($user)->get(route('games.panel', ['game' => $game, 'tile' => $capital->tile_id]))
            ->assertOk()
            ->assertSee('id="tile-panel"', false)
            ->assertSee($capital->name)
            ->assertDontSee('End turn');
    }

    public function test_city_focus_changes_which_tiles_are_worked(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $capital = $game->playerEmpire()->cities->first();

        $this->actingAs($user)->post(route('games.focus', [$game, $capital]), ['focus' => 'gold'])->assertRedirect();
        $this->assertSame('gold', $capital->fresh()->focus);

        $this->actingAs($user)->post(route('games.focus', [$game, $capital]), ['focus' => 'nonsense'])->assertSessionHas('error');
    }

    public function test_barbarian_camps_exist_and_can_be_cleared_with_an_army(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $camp = $game->tiles->firstWhere('camp', true);
        $this->assertNotNull($camp, 'a new game should have barbarian camps');

        // Put the camp in reach by giving the player a tile next to it.
        $near = $game->tiles->first(fn ($t) => $t->distanceTo($camp) === 1 && ! $t->camp);
        $near->update(['empire_id' => $player->id]);

        $this->actingAs($user)->post(route('games.clear-camp', $game), ['tile' => $camp->id])->assertSessionHas('error');

        $player->update(['army' => 10, 'gold' => 0]);
        $this->actingAs($user)->post(route('games.clear-camp', $game), ['tile' => $camp->id])->assertSessionHas('status');
        $this->assertFalse($camp->fresh()->camp);
        $this->assertSame(8, $player->fresh()->army);
        $this->assertGreaterThan(0, $player->fresh()->gold);
    }

    public function test_map_size_and_difficulty_options_apply(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('games.store'), ['empire' => 'Egypt', 'rivals' => 5, 'size' => 'small', 'difficulty' => 'king'])
            ->assertRedirect();

        $game = Game::firstOrFail();
        $this->assertSame(Rules::MAP_SIZES['small']['width'], $game->width);
        $this->assertSame('king', $game->difficulty);
        $this->assertCount(3, $game->empires, 'a small map trims rivals to 2');

        $this->actingAs($user)->post(route('games.end-turn', $game));
        $this->assertCount(1, $game->fresh()->history);
    }

    public function test_guide_and_hall_of_fame_are_public(): void
    {
        $this->get(route('guide'))->assertOk()->assertSee('Ways to win');
        $this->get(route('hall'))->assertOk()->assertSee('Hall of Fame');

        $user = User::factory()->create();
        $game = $this->startGame($user);
        $game->update(['status' => 'won', 'winner_empire_id' => $game->playerEmpire()->id]);
        $game->playerEmpire()->update(['score' => 321]);

        $this->get(route('hall'))->assertSee('321')->assertSee($user->name);
        $this->actingAs($user)->get(route('games.show', $game))->assertSee('Victory')->assertSee('Play again');
    }

    public function test_production_queue_feeds_the_next_build(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $capital = $game->playerEmpire()->cities->first();

        $this->actingAs($user)->post(route('games.build', [$game, $capital]), ['build' => 'warriors'])->assertRedirect();
        $this->actingAs($user)->post(route('games.build', [$game, $capital]), ['build' => 'monument', 'queue' => 1])->assertSessionMissing('error');
        $this->actingAs($user)->post(route('games.build', [$game, $capital]), ['build' => 'monument', 'queue' => 1])->assertSessionHas('error');
        $this->assertSame(['monument'], $capital->fresh()->queue);

        // Pay for the warriors so they finish this turn; the queue should take over.
        $game->playerEmpire()->update(['gold' => 999]);
        $this->actingAs($user)->post(route('games.buy', [$game, $capital]))->assertSessionHas('status');
        $this->actingAs($user)->post(route('games.end-turn', $game));
        $capital->refresh();
        $this->assertSame('monument', $capital->current_build);
        $this->assertSame([], $capital->queue);

        $this->actingAs($user)->post(route('games.build', [$game, $capital]), ['build' => 'warriors', 'queue' => 1]);
        $this->actingAs($user)->post(route('games.dequeue', [$game, $capital]), ['position' => 0])->assertRedirect();
        $this->assertSame([], $capital->fresh()->queue);
    }

    public function test_sustained_happiness_triggers_a_golden_age(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $player->update(['golden_points' => Rules::GOLDEN_POINTS_NEEDED - 1]);

        $this->actingAs($user)->post(route('games.end-turn', $game));
        $player->refresh();
        $this->assertTrue($player->inGoldenAge($game->fresh()->turn));
        $this->assertStringContainsString('Golden Age', implode(' ', $game->fresh()->events));
    }

    public function test_farms_improve_grassland_yield_for_that_city_only(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $capital = $player->cities->first();
        $grass = $game->tiles->first(fn ($t) => $t->terrain === 'grassland');

        $before = Rules::tileYield($grass, $player, $capital)['food'];
        $capital->update(['buildings' => ['farms']]);
        $this->assertSame($before + 1, Rules::tileYield($grass, $player, $capital->fresh())['food']);
        $this->assertSame($before, Rules::tileYield($grass, $player)['food']);
    }

    public function test_cultural_pressure_flips_a_weak_neighbouring_tile(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $capital = $player->cities->first();
        $centre = $game->tiles->firstWhere('id', $capital->tile_id);

        // Give a rival a lonely low-culture tile two steps from our very cultured capital.
        $rival = $game->empires->firstWhere('is_ai', true);
        $rivalCity = $rival->cities->first();
        $target = $game->tiles->first(fn ($t) => $t->distanceTo($centre) === 2 && $t->isLand() && $t->empire_id === null);
        $this->assertNotNull($target);
        $target->update(['empire_id' => $rival->id, 'city_id' => $rivalCity->id]);
        $capital->update(['culture' => 500]);
        $rivalCity->update(['culture' => 10]);

        $this->actingAs($user)->post(route('games.end-turn', $game));
        $this->assertSame($player->id, $target->fresh()->empire_id);
        $this->assertStringContainsString('culture', strtolower(implode(' ', $game->fresh()->events)));
    }
}
