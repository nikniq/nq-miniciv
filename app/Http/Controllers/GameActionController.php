<?php

namespace App\Http\Controllers;

use App\Game\Actions;
use App\Game\Rules;
use App\Game\TurnProcessor;
use App\Models\City;
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
            $this->actions->setBuild($city, $empire, $key, $this->wondersBuilt($game));
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
