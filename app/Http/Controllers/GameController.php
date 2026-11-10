<?php

namespace App\Http\Controllers;

use App\Game\GameSetup;
use App\Game\Presenter;
use App\Game\Rules;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(Request $request): View
    {
        $games = Game::where('user_id', $request->user()->id)
            ->with('empires')
            ->latest('updated_at')
            ->get();

        return view('games.index', [
            'games' => $games,
            'empireNames' => Rules::EMPIRE_NAMES,
        ]);
    }

    public function store(Request $request, GameSetup $setup): RedirectResponse
    {
        $data = $request->validate([
            'empire' => ['required', 'string', 'max:30', 'regex:/^[\pL\pN \'\-]+$/u'],
            'rivals' => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        $game = $setup->create($request->user(), trim($data['empire']), (int) $data['rivals']);

        return redirect()->route('games.show', $game)
            ->with('status', 'Your capital has been founded. You also have a band of settlers ready: pick a tile to found a second city.');
    }

    public function show(Request $request, Game $game, Presenter $presenter): View
    {
        $this->own($request, $game);

        $tileId = $request->integer('tile') ?: null;

        return view('games.show', $presenter->present($game, $tileId));
    }

    public function destroy(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $game->delete();

        return redirect()->route('games.index')->with('status', 'Game abandoned.');
    }

    private function own(Request $request, Game $game): void
    {
        abort_unless($game->user_id === $request->user()->id, 403);
    }
}
