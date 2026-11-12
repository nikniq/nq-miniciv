<?php

namespace App\Http\Controllers;

use App\Game\GameSetup;
use App\Game\Rules;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * "Play now": no sign-up needed. A guest account is created behind the
 * scenes and a game started straight away. Registering later converts the
 * guest account, so nothing is lost.
 */
class PlayController extends Controller
{
    public function start(Request $request, GameSetup $setup): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            $user = User::create([
                'name' => 'Guest '.Str::upper(Str::random(4)),
                'email' => 'guest-'.Str::lower(Str::random(20)).'@guest.local',
                'password' => null,
                'is_guest' => true,
            ]);
            Auth::login($user, remember: true);
            $request->session()->regenerate();
        }

        $game = $setup->create($user, 'Rome', 2, null, 'normal', 'prince', array_rand(Rules::TRAITS));

        return redirect()->route('games.show', $game)
            ->with('status', 'Your capital has been founded. You also have settlers ready: pick a tile at least 3 away and found a second city.');
    }
}
