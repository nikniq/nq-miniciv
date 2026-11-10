<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->user() && ! $request->user()->isGuest()) {
            return redirect()->route('games.index');
        }

        return view('auth.login', ['google' => $this->googleEnabled()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Those details do not match our records.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('games.index'));
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if ($request->user() && ! $request->user()->isGuest()) {
            return redirect()->route('games.index');
        }

        return view('auth.register', ['google' => $this->googleEnabled(), 'guest' => $request->user()]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // A guest who registers keeps their account and every game on it.
        $guest = $request->user();
        if ($guest && $guest->isGuest()) {
            $guest->forceFill($data + ['is_guest' => false])->save();
            $request->session()->regenerate();

            return redirect()->route('games.index')->with('status', 'Your progress is saved. Welcome, '.$guest->name.'.');
        }

        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('games.index')->with('status', 'Welcome, '.$user->name.'. Found your first empire.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public static function googleEnabled(): bool
    {
        return (bool) config('services.google.client_id');
    }
}
