<?php

namespace Tests\Feature;

use App\Game\Actions;
use App\Game\Fog;
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
        $actions = app(Actions::class);
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
        $actions = app(Actions::class);

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

    public function test_gifts_tribute_and_raids(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $rival = $game->empires->firstWhere('is_ai', true);

        $player->update(['gold' => 200, 'army' => 40]);
        $this->actingAs($user)->post(route('games.gift', [$game, $rival]), ['gold' => 100])->assertSessionHas('status');
        $this->assertSame(10, $game->fresh()->attitude($player->id, $rival->id));
        $this->assertSame(100, $player->fresh()->gold);

        $rival->update(['gold' => 80, 'army' => 2]);
        $this->actingAs($user)->post(route('games.tribute', [$game, $rival]))->assertSessionHas('status');
        $this->assertLessThan(80, $rival->fresh()->gold);
        $this->assertLessThan(10, $game->fresh()->attitude($player->id, $rival->id));

        // Raid needs war and a shared border.
        $city = $rival->cities->first();
        $centre = $game->tiles->firstWhere('id', $city->tile_id);
        $near = $game->tiles->first(fn ($t) => $t->distanceTo($centre) === 2 && $t->empire_id === null);
        $near->update(['empire_id' => $player->id]);
        $this->actingAs($user)->post(route('games.raid', [$game, $city]))->assertSessionHas('error');
        $this->actingAs($user)->post(route('games.war', [$game, $rival]));
        $goldBefore = $player->fresh()->gold;
        $this->actingAs($user)->post(route('games.raid', [$game, $city]))->assertSessionHas('status');
        $this->assertGreaterThan($goldBefore, $player->fresh()->gold);
        $this->assertSame(39, $player->fresh()->army);
    }

    public function test_natural_wonders_exist_and_cannot_be_settled(): void
    {
        $game = $this->startGame(User::factory()->create());
        $wonder = $game->tiles->first(fn ($t) => $t->feature !== null);
        $this->assertNotNull($wonder);
        $this->assertFalse($wonder->canSettle());
        $this->assertGreaterThan(0, Rules::tileYield($wonder)['culture']);
    }

    public function test_governments_require_tech_and_cause_anarchy(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();

        $this->actingAs($user)->post(route('games.government', $game), ['government' => 'monarchy'])->assertSessionHas('error');
        $player->update(['technologies' => ['agriculture', 'writing', 'code_of_laws']]);
        $this->actingAs($user)->post(route('games.government', $game), ['government' => 'monarchy'])->assertSessionHas('status');
        $player->refresh();
        $this->assertSame('monarchy', $player->government);
        $this->assertTrue($player->inAnarchy($game->turn));
        $this->actingAs($user)->post(route('games.government', $game), ['government' => 'despotism'])->assertSessionHas('error');
    }

    public function test_units_are_tracked_and_lost_weakest_first(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();

        $player->addUnit('warriors');
        $player->addUnit('swordsmen');
        $player->save();
        $this->assertSame(6, $player->army);
        $player->loseStrength(1);
        $this->assertSame(['swordsmen' => 1], $player->units);
        $this->assertSame(4, $player->army);

        $this->actingAs($user)->get(route('games.show', $game))->assertSee('Swordsmen × 1');
    }

    public function test_cities_stop_growing_at_the_size_cap_without_an_aqueduct(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $capital = $game->playerEmpire()->cities->first();
        $capital->update(['population' => Rules::SIZE_CAP, 'food_stored' => 999, 'focus' => 'food']);

        $this->actingAs($user)->post(route('games.end-turn', $game));
        // 999 stored food would grow any uncapped city; a random plague may
        // also take one citizen the same turn, so only the ceiling is exact.
        $this->assertLessThanOrEqual(Rules::SIZE_CAP, $capital->fresh()->population);
        $this->assertGreaterThanOrEqual(Rules::SIZE_CAP - 1, $capital->fresh()->population);
        $this->assertStringContainsString('Aqueduct', implode(' ', $game->fresh()->events));
    }

    public function test_profile_shows_achievements_once_earned(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('Achievements');

        // Founding the second city earns Settler.
        $player = $game->playerEmpire();
        $actions = app(Actions::class);
        $site = $game->tiles->first(fn ($t) => $actions->foundingProblem($game, $player, $t, $game->tiles) === null);
        $this->actingAs($user)->post(route('games.found', $game), ['tile' => $site->id]);
        $this->actingAs($user)->post(route('games.end-turn', $game));
        $this->assertArrayHasKey('second_city', $user->fresh()->achievements ?? []);
        $this->assertStringContainsString('Achievement unlocked', implode(' ', $game->fresh()->events));
    }

    public function test_great_people_arrive_from_buildings_and_can_be_spent(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $capital = $player->cities->first();
        $capital->buildings = ['library', 'university'];
        $capital->current_build = 'monument';
        $capital->production_stored = 0;
        $capital->save();
        $player->great_progress = ['scientist' => Rules::GREAT_POINTS_BASE - 1];
        $player->current_tech = 'writing';
        $player->technologies = [];
        $player->save();

        $this->actingAs($user)->post(route('games.end-turn', $game))->assertRedirect();
        $player->refresh();
        $this->assertSame(['scientist'], $player->great_people);
        $this->assertSame(1, (int) $player->great_count);
        $this->assertSame([], $player->great_progress ?? [], 'points reset after an arrival');

        $this->actingAs($user)->post(route('games.great-person', $game), ['kind' => 'scientist'])->assertSessionHas('status');
        $player->refresh();
        $this->assertContains('writing', $player->technologies);
        $this->assertSame([], $player->great_people);

        $this->actingAs($user)->post(route('games.great-person', $game), ['kind' => 'merchant'])->assertSessionHas('error');
        $this->assertArrayHasKey('great_person', $user->fresh()->achievements ?? []);
    }

    public function test_friendly_rivals_trade_techs_for_techs_or_gold(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $rival = $game->empires->firstWhere('is_ai', true);
        $player->update(['technologies' => ['agriculture'], 'gold' => 500]);
        $rival->update(['technologies' => ['mining']]);

        // Neutral but below zero: no offers.
        $game->adjustAttitude($player->id, $rival->id, -5);
        $game->save();
        $this->actingAs($user)->get(route('games.show', $game))->assertOk()->assertDontSee('🔁 techs');

        // Devoted, so even a Scholar (who asks more) accepts a cheap tech.
        $game->adjustAttitude($player->id, $rival->id, 65);
        $game->save();
        $this->actingAs($user)->get(route('games.show', $game))->assertOk()->assertSee('🔁 techs');

        $this->actingAs($user)->post(route('games.trade', [$game, $rival]), ['get' => 'mining', 'give' => 'agriculture'])->assertSessionHas('status');
        $this->assertContains('mining', $player->fresh()->technologies);
        $this->assertContains('agriculture', $rival->fresh()->technologies);

        // Cooldown: a second deal straight away is refused.
        $rival->update(['technologies' => ['mining', 'agriculture', 'pottery']]);
        $this->actingAs($user)->post(route('games.trade', [$game, $rival]), ['get' => 'pottery'])->assertSessionHas('error');
    }

    public function test_dilemmas_can_be_answered_and_default_when_ignored(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $city = $player->cities->first();
        $game->update(['choice' => ['kind' => 'prophet', 'turn' => $game->turn, 'city' => $city->id, 'rival' => null]]);

        $this->actingAs($user)->get(route('games.show', $game))->assertOk()->assertSee('A prophet preaches');
        $culture = $city->culture;
        $this->actingAs($user)->post(route('games.choose', $game), ['option' => 'a'])->assertSessionHas('status');
        $this->assertGreaterThan($culture, $city->fresh()->culture);
        $this->assertNull($game->fresh()->choice);

        // Ignored for a turn: option b (gold) is taken at the next end turn.
        $game->update(['choice' => ['kind' => 'prophet', 'turn' => $game->turn - 1, 'city' => $city->id, 'rival' => null]]);
        $gold = $player->fresh()->gold;
        $this->actingAs($user)->post(route('games.end-turn', $game))->assertRedirect();
        $this->assertGreaterThan($gold - 10, $player->fresh()->gold);
        $this->assertNotSame($game->fresh()->choice['kind'] ?? null, 'prophet');
    }

    public function test_fog_of_war_hides_the_map_until_explored(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $capital = $player->cities->first();
        $centre = $game->tiles->firstWhere('id', $capital->tile_id);

        $pct = Fog::percent($game, $player);
        $this->assertGreaterThan(0, $pct);
        $this->assertLessThan(60, $pct, 'most of the map starts hidden');
        $this->assertTrue(Fog::visible($game, $player, $centre));
        $this->assertNull($game->empires->firstWhere('is_ai', true)->explored, 'rivals see everything');

        $page = $this->actingAs($user)->get(route('games.show', $game));
        $page->assertOk()->assertSee('url(#fogp)', false)->assertSee('Unexplored');

        // A hidden tile cannot be settled, even with settlers in hand.
        $hidden = $game->tiles->first(fn ($t) => $t->canSettle() && ! $t->empire_id && ! Fog::visible($game, $player, $t) && $centre->distanceTo($t) > 8);
        $this->assertNotNull($hidden);
        $this->actingAs($user)->post(route('games.found', $game), ['tile' => $hidden->id])->assertSessionHas('error');

        // Scouts reveal, cost gold, and refuse when nothing is left to see.
        $player->update(['gold' => 100]);
        $this->actingAs($user)->post(route('games.scout', [$game, $capital]))->assertSessionHas('status');
        $player->refresh();
        $this->assertSame(100 - Rules::SCOUT_COST, $player->gold);
        $this->assertGreaterThan($pct, Fog::percent($game, $player));
        $this->actingAs($user)->post(route('games.scout', [$game, $capital]))->assertSessionHas('error');

        // Borders push the fog back each turn.
        $before = Fog::percent($game, $player);
        $player->update(['explored' => str_repeat('0', $game->width * $game->height)]);
        $this->actingAs($user)->post(route('games.end-turn', $game))->assertRedirect();
        $this->assertGreaterThan(0, Fog::percent($game, $player->fresh()));
    }

    public function test_launching_the_grand_expedition_wins_a_science_victory(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $capital = $player->cities->first();
        $player->update(['technologies' => array_keys(Rules::TECHS), 'gold' => 99999]);
        $capital->update(['current_build' => 'grand_expedition', 'production_stored' => 0]);

        // Gold cannot buy it.
        $this->actingAs($user)->post(route('games.buy', [$game, $capital]))->assertSessionHas('error');
        $this->assertSame(0, $capital->fresh()->production_stored);

        $capital->update(['production_stored' => Rules::BUILDINGS['grand_expedition']['cost']]);
        $this->actingAs($user)->post(route('games.end-turn', $game))->assertRedirect();

        $game->refresh();
        $this->assertSame('won', $game->status);
        $this->assertStringContainsString('Science victory', $game->events[0]);
        $this->assertArrayHasKey('win_science', $user->fresh()->achievements ?? []);
    }

    public function test_veterancy_lifts_strength_and_old_units_can_be_retrained(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $player->addUnit('warriors');
        $player->addUnit('warriors');
        $player->update(['technologies' => ['mining', 'bronze_working'], 'gold' => 200, 'veterancy' => 10]);
        $player->refresh();

        $this->assertSame('Veteran', $player->rankName());
        $this->assertSame((int) floor(4 * 1.2), $player->strength());

        $page = $this->actingAs($user)->get(route('games.show', $game));
        $page->assertSee('Veteran')->assertSee('Retrain 2 Warriors as Swordsmen');

        $this->actingAs($user)->post(route('games.upgrade', $game), ['from' => 'warriors'])->assertSessionHas('status');
        $player->refresh();
        $this->assertSame(['swordsmen' => 2], $player->units);
        $this->assertSame(8, $player->army);
        $this->assertSame(200 - 2 * (Rules::UNITS['swordsmen']['cost'] - Rules::UNITS['warriors']['cost']), $player->gold);

        $this->actingAs($user)->post(route('games.upgrade', $game), ['from' => 'warriors'])->assertSessionHas('error');
    }

    public function test_turns_record_replay_frames_shown_when_the_game_ends(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $this->actingAs($user)->post(route('games.end-turn', $game));
        $this->actingAs($user)->post(route('games.end-turn', $game));

        $game->refresh();
        $this->assertCount(2, $game->replay);
        [$turn, $row] = $game->replay[1];
        $this->assertSame(2, $turn);
        $this->assertSame($game->width * $game->height, strlen($row));
        $this->assertSame($game->cities()->count(), preg_match_all('/[A-Z]/', $row), 'every city marked');

        $this->actingAs($user)->get(route('games.show', $game))->assertDontSee('replay-canvas', false);
        $game->update(['status' => 'won']);
        $this->actingAs($user)->get(route('games.show', $game))->assertSee('replay-canvas', false);
    }

    public function test_barbarian_war_bands_sack_weak_cities_and_break_on_strong_ones(): void
    {
        $user = User::factory()->create();
        $game = $this->startGame($user);
        $player = $game->playerEmpire();
        $city = $player->cities->first();
        $camp = $game->tiles->first(fn ($t) => $t->canSettle() && ! $t->empire_id);
        $camp->update(['camp' => true]);
        $city->update(['population' => 4, 'food_stored' => 30]);
        $player->update(['gold' => 100]);

        // Arriving this turn with overwhelming strength: the city is sacked.
        $game->update(['raids' => [['camp' => $camp->id, 'city' => $city->id, 'strength' => 99, 'arrives' => $game->turn]]]);
        $this->actingAs($user)->post(route('games.end-turn', $game));
        $city->refresh();
        $this->assertSame(3, $city->population);
        $this->assertLessThan(100, $player->fresh()->gold);
        $this->assertStringContainsString('sacked', implode(' ', $game->fresh()->events ?? []).' '.collect($game->fresh()->log ?? [])->flatten()->implode(' '));

        // Announced for next turn: the banner shows, then a weak band breaks.
        $game->refresh();
        $game->update(['raids' => [['camp' => $camp->id, 'city' => $city->id, 'strength' => 1, 'arrives' => $game->turn]]]);
        $this->actingAs($user)->get(route('games.show', $game))->assertSee('Barbarians march on')->assertSee('The city should hold.');
        $pop = $city->fresh()->population;
        $this->actingAs($user)->post(route('games.end-turn', $game));
        $this->assertGreaterThanOrEqual($pop - 1, $city->fresh()->population);
        $this->assertStringContainsString('broke against', collect($game->fresh()->log ?? [])->flatten()->implode(' '));

        // Clearing the camp calls a raid off.
        $game->refresh();
        $game->update(['raids' => [['camp' => $camp->id, 'city' => $city->id, 'strength' => 99, 'arrives' => $game->turn]]]);
        $camp->update(['camp' => false]);
        $before = $city->fresh()->population;
        $this->actingAs($user)->post(route('games.end-turn', $game));
        $this->assertStringNotContainsString('sacked', collect(($game->fresh()->log ?? [])[count($game->fresh()->log) - 1] ?? [])->flatten()->implode(' '));
    }
}
