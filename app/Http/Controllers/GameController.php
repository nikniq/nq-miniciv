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
            'sizes' => Rules::MAP_SIZES,
            'difficulties' => Rules::DIFFICULTIES,
        ]);
    }

    public function store(Request $request, GameSetup $setup): RedirectResponse
    {
        $data = $request->validate([
            'empire' => ['required', 'string', 'max:30', 'regex:/^[\pL\pN \'\-]+$/u'],
            'rivals' => ['required', 'integer', 'min:1', 'max:7'],
            'size' => ['required', 'in:'.implode(',', array_keys(Rules::MAP_SIZES))],
            'difficulty' => ['required', 'in:'.implode(',', array_keys(Rules::DIFFICULTIES))],
            'trait' => ['nullable', 'in:random,none,'.implode(',', array_keys(Rules::TRAITS))],
        ]);

        $trait = match ($data['trait'] ?? 'random') { 'random' => array_rand(Rules::TRAITS), 'none' => null, default => $data['trait'] };
        $game = $setup->create($request->user(), trim($data['empire']), (int) $data['rivals'], null, $data['size'], $data['difficulty'], $trait);

        return redirect()->route('games.show', $game)
            ->with('status', 'Your capital has been founded. You also have a band of settlers ready: pick a tile to found a second city.');
    }

    public function show(Request $request, Game $game, Presenter $presenter): View
    {
        $this->own($request, $game);

        $tileId = $request->integer('tile') ?: null;

        return view('games.show', $presenter->present($game, $tileId));
    }

    /** The side panel for one tile, for the in-page map selection. */
    public function panel(Request $request, Game $game, Presenter $presenter): View
    {
        $this->own($request, $game);

        return view('games._panel', $presenter->present($game, $request->integer('tile') ?: null));
    }

    public function destroy(Request $request, Game $game): RedirectResponse
    {
        $this->own($request, $game);
        $game->delete();

        return redirect()->route('games.index')->with('status', 'Game abandoned.');
    }

    /** The player's record: stats and achievements. */
    public function profile(Request $request): View
    {
        $user = $request->user();
        $games = Game::where('user_id', $user->id)->with('empires')->get();
        $finished = $games->whereIn('status', ['won', 'lost', 'ended']);

        return view('games.profile', [
            'user' => $user,
            'stats' => [
                'games' => $games->count(),
                'finished' => $finished->count(),
                'wins' => $finished->where('status', 'won')->count(),
                'best' => $finished->map(fn ($g) => $g->playerEmpire()?->score ?? 0)->max() ?? 0,
                'turns' => $games->sum(fn ($g) => min($g->turn, $g->max_turns) - 1),
            ],
            'earned' => $user->achievements ?? [],
            'winsByKind' => $finished->where('status', 'won')->map(fn ($g) => $g->victoryKind())->filter()
                ->groupBy(fn ($k) => $k[1])->map(fn ($group) => ['icon' => $group->first()[0], 'n' => $group->count()])->sortByDesc('n'),
        ]);
    }

    /** Public: best finished games. */
    public function hallOfFame(): View
    {
        $games = Game::whereIn('status', ['won', 'lost', 'ended'])
            ->with(['empires', 'user'])
            ->latest('updated_at')
            ->limit(200)
            ->get()
            ->map(function (Game $g) {
                $me = $g->playerEmpire();

                return [
                    'game' => $g,
                    'empire' => $me,
                    'score' => $me?->score ?? 0,
                    'player' => $g->user && ! $g->user->isGuest() ? $g->user->name : 'Guest',
                ];
            })
            ->sortByDesc('score')
            ->take(50)
            ->values();

        return view('games.hall', ['rows' => $games]);
    }

    private function own(Request $request, Game $game): void
    {
        abort_unless($game->user_id === $request->user()->id, 403);
    }
}
