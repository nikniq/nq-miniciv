<?php

namespace App\Http\Controllers;

use App\Game\Actions;
use App\Game\Choices;
use App\Game\Rules;
use App\Game\TurnProcessor;
use App\Models\City;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GameActionController extends Controller
{
    public function __construct(private Actions $actions) {}

    public function endTurn(Request $request, Game $game, TurnProcessor $turns): RedirectResponse
    {
        $this->own($request, $game);
        if (! $game->isActive()) {
            return $this->back($game, 'This game is over.');
        }

        $turns->endTurn($game);

        return redirect()->route('games.show', ['game' => $game, 'tile' => $request->integer('tile') ?: null]);
    }

    public function found(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $tile = Tile::where('game_id', $game->id)->findOrFail($request->integer('tile'));
        $game->load(['cities', 'tiles']);
        $empire = $game->empires()->where('is_ai', false)->with('cities')->firstOrFail();

        try {
            $city = $this->actions->foundCity($game, $empire, $tile, $game->tiles);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $tile->id);
        }

        return $this->back($game, "{$city->name} has been founded.", $tile->id, 'status');
    }

    public function build(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $empire = $this->ownCity($game, $city);
        $key = (string) $request->input('build');

        try {
            $this->actions->setBuild($city, $empire, $key, $this->wondersBuilt($game), $request->boolean('queue'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        return $this->back($game, null, $city->tile_id);
    }

    public function buy(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $empire = $this->ownCity($game, $city);

        try {
            $price = $this->actions->buyBuild($city, $empire);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        return $this->back($game, "Paid {$price} gold. It will be finished at the end of the turn.", $city->tile_id, 'status');
    }

    public function attack(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $game->load(['empires.cities', 'cities', 'tiles']);
        $empire = $game->playerEmpire();
        abort_unless($city->game_id === $game->id, 404);
        $target = $game->cities->firstWhere('id', $city->id);

        try {
            $result = $this->actions->attack($game, $empire, $target, $game->tiles);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        $text = $result['text'];
        if ($result['eliminated']) {
            $text .= " {$result['eliminated']->name} has been wiped from the map.";
        }

        return $this->back($game, $text, $city->tile_id, $result['captured'] ? 'status' : 'error');
    }

    public function dequeue(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $this->ownCity($game, $city);

        try {
            $this->actions->removeFromQueue($city, $request->integer('position'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        return $this->back($game, null, $city->tile_id);
    }

    public function focus(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $this->ownCity($game, $city);

        try {
            $this->actions->setFocus($city, (string) $request->input('focus'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        return $this->back($game, null, $city->tile_id);
    }

    public function war(Request $request, Game $game, Empire $empire): RedirectResponse
    {
        $this->own($request, $game);
        abort_unless($empire->game_id === $game->id, 404);
        $game->load('empires');
        $player = $game->playerEmpire();

        $betrayal = $game->allied($player->id, $empire->id);
        try {
            $this->actions->declareWar($game, $player, $empire);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }
        $joined = collect($this->actions->lastAlliesJoined)->pluck('name');
        $msg = ($betrayal ? "You have betrayed your ally {$empire->name}. The world will remember. " : '')
            ."You are now at war with {$empire->name}. War weariness costs ".Rules::WAR_WEARINESS.' happiness.'
            .($joined->isNotEmpty() ? ' Their allies join them: '.$joined->join(', ').'.' : '');

        return $this->back($game, $msg, $request->integer('tile') ?: null, 'status');
    }

    public function alliance(Request $request, Game $game, Empire $empire): RedirectResponse
    {
        $this->own($request, $game);
        abort_unless($empire->game_id === $game->id, 404);
        $game->load('empires');
        $player = $game->playerEmpire();

        try {
            if ($request->boolean('dissolve')) {
                $this->actions->dissolveAlliance($game, $player, $empire);

                return $this->back($game, "You have ended the alliance with {$empire->name}.", $request->integer('tile') ?: null, 'status');
            }
            $this->actions->formAlliance($game, $player, $empire);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, "🤝 {$empire->name} is now your ally. Each of you joins any war declared on the other, and you share maps.", $request->integer('tile') ?: null, 'status');
    }

    public function peace(Request $request, Game $game, Empire $empire): RedirectResponse
    {
        $this->own($request, $game);
        abort_unless($empire->game_id === $game->id, 404);
        $game->load('empires');
        $player = $game->playerEmpire();

        try {
            $accepted = $this->actions->proposePeace($game, $player, $empire);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $accepted
            ? $this->back($game, "{$empire->name} accepts peace.", $request->integer('tile') ?: null, 'status')
            : $this->back($game, "{$empire->name} refuses: they think they are winning. Try again when the war has dragged on or their army is smaller.", $request->integer('tile') ?: null);
    }

    public function government(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $game->load('empires.cities');
        $player = $game->playerEmpire();

        try {
            $this->actions->setGovernment($game, $player, (string) $request->input('government'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, 'The old order falls. '.Rules::ANARCHY_TURNS.' turns of anarchy, then '.Rules::GOVERNMENTS[$player->government]['name'].'.', $request->integer('tile') ?: null, 'status');
    }

    public function gift(Request $request, Game $game, Empire $empire): RedirectResponse
    {
        $this->own($request, $game);
        abort_unless($empire->game_id === $game->id, 404);
        $game->load('empires');
        $player = $game->playerEmpire();

        try {
            $delta = $this->actions->gift($game, $player, $empire, $request->integer('gold'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, "{$empire->name} accepts your gift. Relations improve by {$delta}.", $request->integer('tile') ?: null, 'status');
    }

    public function tribute(Request $request, Game $game, Empire $empire): RedirectResponse
    {
        $this->own($request, $game);
        abort_unless($empire->game_id === $game->id, 404);
        $game->load('empires.cities');
        $player = $game->playerEmpire();

        try {
            $r = $this->actions->demandTribute($game, $player, $empire);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $r['paid']
            ? $this->back($game, "{$empire->name} pays {$r['gold']} gold in tribute, and resents it.", $request->integer('tile') ?: null, 'status')
            : $this->back($game, "{$empire->name} refuses to pay. Relations sour.", $request->integer('tile') ?: null);
    }

    public function raid(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $game->load(['empires.cities', 'cities', 'tiles']);
        $empire = $game->playerEmpire();
        abort_unless($city->game_id === $game->id, 404);
        $target = $game->cities->firstWhere('id', $city->id);

        try {
            $r = $this->actions->raid($game, $empire, $target, $game->tiles);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        return $this->back($game, $r['text'], $city->tile_id, 'status');
    }

    public function clearCamp(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $tile = Tile::where('game_id', $game->id)->findOrFail($request->integer('tile'));
        $game->load(['empires', 'tiles']);
        $player = $game->playerEmpire();

        try {
            $loot = $this->actions->clearCamp($player, $tile, $game->tiles);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $tile->id);
        }

        return $this->back($game, "The camp is destroyed. Your troops loot {$loot} gold.", $tile->id, 'status');
    }

    public function research(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $empire = $game->empires()->where('is_ai', false)->firstOrFail();

        try {
            $this->actions->setResearch($empire, (string) $request->input('tech'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, null, $request->integer('tile') ?: null);
    }

    public function scout(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $empire = $this->ownCity($game, $city);
        $game->load('tiles');

        try {
            $n = $this->actions->sendScouts($game, $empire, $city, $game->tiles);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        return $this->back($game, "Scouts from {$city->name} map {$n} new tiles.", $city->tile_id, 'status');
    }

    public function route(Request $request, Game $game, City $city): RedirectResponse
    {
        $this->own($request, $game);
        $empire = $this->ownCity($game, $city);
        $game->load(['empires.cities', 'tiles']);
        $empire = $game->empires->firstWhere('id', $empire->id);
        $to = City::where('game_id', $game->id)->find($request->integer('to'));
        if (! $to) {
            return $this->back($game, 'Pick a destination.', $city->tile_id);
        }

        try {
            $line = $this->actions->setTradeRoute($game, $empire, $city, $to, $game->tiles);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $city->tile_id);
        }

        return $this->back($game, $line, $city->tile_id, 'status');
    }

    public function upgrade(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $empire = $game->empires()->where('is_ai', false)->firstOrFail();

        try {
            $line = $this->actions->upgradeUnits($empire, (string) $request->input('from'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, $line, $request->integer('tile') ?: null, 'status');
    }

    public function trade(Request $request, Game $game, Empire $empire): RedirectResponse
    {
        $this->own($request, $game);
        abort_unless($empire->game_id === $game->id, 404);
        $game->load('empires');
        $player = $game->playerEmpire();
        $give = $request->filled('give') ? (string) $request->input('give') : null;

        try {
            $line = $this->actions->tradeTech($game, $player, $empire, (string) $request->input('get'), $give);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, $line, $request->integer('tile') ?: null, 'status');
    }

    public function choose(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $game->load(['empires.cities', 'cities']);
        $player = $game->playerEmpire();

        try {
            $line = Choices::resolve($game, $player, (string) $request->input('option'));
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, $line, $request->integer('tile') ?: null, 'status');
    }

    public function greatPerson(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $empire = $game->empires()->where('is_ai', false)->with('cities')->firstOrFail();
        $city = $request->filled('city') ? City::where('game_id', $game->id)->find($request->integer('city')) : null;

        try {
            $line = $this->actions->useGreatPerson($game, $empire, (string) $request->input('kind'), $city);
        } catch (InvalidArgumentException $e) {
            return $this->back($game, $e->getMessage(), $request->integer('tile') ?: null);
        }

        return $this->back($game, $line, $request->integer('tile') ?: null, 'status');
    }

    private function own(Request $request, Game $game): void
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isActive(), 409, 'This game is over.');
    }

    private function ownCity(Game $game, City $city)
    {
        $empire = $game->empires()->where('is_ai', false)->firstOrFail();
        abort_unless($city->game_id === $game->id && $city->empire_id === $empire->id, 403);

        return $empire;
    }

    private function wondersBuilt(Game $game): array
    {
        $built = [];
        foreach ($game->cities()->get() as $c) {
            foreach ($c->buildings ?? [] as $b) {
                if (Rules::isWonder($b)) {
                    $built[] = $b;
                }
            }
        }

        return $built;
    }

    private function back(Game $game, ?string $message, ?int $tile = null, string $key = 'error'): RedirectResponse
    {
        $r = redirect()->route('games.show', ['game' => $game, 'tile' => $tile]);

        return $message ? $r->with($key, $message) : $r;
    }
}
