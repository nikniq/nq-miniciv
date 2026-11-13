<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** The admin area: an overview, players and games. Behind the `admin` middleware. */
class AdminController extends Controller
{
    public function dashboard(): View
    {
        $weekAgo = now()->subDays(7);

        return view('admin.dashboard', [
            'stats' => [
                'Players' => User::where('is_guest', false)->count(),
                'Guests' => User::where('is_guest', true)->count(),
                'New players (7 days)' => User::where('is_guest', false)->where('created_at', '>=', $weekAgo)->count(),
                'Admins' => User::where('is_admin', true)->count(),
                'Games' => Game::count(),
                'Active games' => Game::where('status', 'active')->count(),
                'Games played (7 days)' => Game::where('updated_at', '>=', $weekAgo)->count(),
                'Turns played' => (int) Game::sum(DB::raw('turn - 1')),
            ],
            'outcomes' => Game::whereIn('status', ['won', 'lost', 'ended'])->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'recentUsers' => User::where('is_guest', false)->latest()->limit(8)->get(),
            'recentGames' => Game::with('user')->latest('updated_at')->limit(8)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('filter');
        $users = User::query()
            ->withCount('games')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->when($filter === 'admins', fn ($query) => $query->where('is_admin', true))
            ->when($filter === 'guests', fn ($query) => $query->where('is_guest', true))
            ->when($filter === 'players', fn ($query) => $query->where('is_guest', false))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users', ['users' => $users, 'q' => $q, 'filter' => $filter]);
    }

    public function games(Request $request): View
    {
        $status = $request->query('status');
        $games = Game::with('user')
            ->when(in_array($status, ['active', 'won', 'lost', 'ended'], true), fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.games', ['games' => $games, 'status' => $status]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['admin' => 'You cannot change your own admin rights here. Use php artisan admin:revoke.']);
        }
        if ($user->isGuest()) {
            return back()->withErrors(['admin' => 'Guest accounts cannot be administrators.']);
        }
        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin ? "{$user->name} is now an administrator." : "{$user->name} is no longer an administrator.");
    }

    public function resetName(User $user): RedirectResponse
    {
        $user->forceFill(['name' => 'Ruler '.$user->id])->save();

        return back()->with('status', "Nickname reset to {$user->name}.");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['admin' => 'You cannot delete your own account here.']);
        }
        $name = $user->name;
        $user->delete(); // games cascade

        return back()->with('status', "Deleted {$name} and their games.");
    }

    public function destroyGame(Game $game): RedirectResponse
    {
        $name = $game->name;
        $game->delete();

        return back()->with('status', "Deleted the game {$name}.");
    }
}
