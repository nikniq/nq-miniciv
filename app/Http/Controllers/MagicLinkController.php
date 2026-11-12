<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Notifications\MagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Passwordless sign-up and sign-in: a single email address, confirmed by a
 * signed link. New addresses become accounts when the link is opened; a
 * guest who asks for a link keeps every game on the claimed account.
 */
class MagicLinkController extends Controller
{
    public function send(Request $request): RedirectResponse|View
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = Str::lower($data['email']);
        if (Str::endsWith($email, '@guest.local')) {
            return back()->withErrors(['email' => 'Please use a real email address.']);
        }

        $existing = User::where('email', $email)->first();
        $guest = $request->user();
        $url = URL::temporarySignedRoute('magic.verify', now()->addMinutes((int) config('auth.magic_link_minutes', 30)), [
            'email' => $email,
            'guest' => $guest && $guest->isGuest() ? $guest->id : null,
        ]);

        Notification::route('mail', $email)->notify(new MagicLink($url, ! $existing));

        return view('auth.sent', ['email' => $email, 'isNew' => ! $existing]);
    }

    public function verify(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors(['email' => 'That link has expired or was already used. Request a new one.']);
        }
        $email = Str::lower((string) $request->query('email'));
        // Only the guest who asked for the link, opening it in the same browser,
        // may attach their games to the address. Anyone else just signs in.
        $guestId = $request->filled('guest') ? $request->integer('guest') : null;
        $current = $request->user();
        $guest = ($guestId && $current && $current->isGuest() && $current->id === $guestId) ? $current : null;
        $user = User::where('email', $email)->first();

        if ($user && $guest && $guest->isNot($user)) {
            // The address already has an account: move the guest's games onto it.
            Game::where('user_id', $guest->id)->update(['user_id' => $user->id]);
            $user->achievements = ($user->achievements ?? []) + ($guest->achievements ?? []);
            $guest->delete();
        } elseif (! $user && $guest) {
            $guest->forceFill(['email' => $email, 'name' => $this->nameFor($email), 'is_guest' => false, 'remember_token' => Str::random(60)])->save();
            $user = $guest;
        } elseif (! $user) {
            $user = User::create(['name' => $this->nameFor($email), 'email' => $email, 'password' => null]);
        }
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        // Links are single use: signing in rotates the session, and a second
        // visit is refused by the cache marker below.
        $key = 'magic:'.sha1($request->fullUrl());
        if (cache()->has($key)) {
            return redirect()->route('login')->withErrors(['email' => 'That link was already used. Request a new one.']);
        }
        cache()->put($key, true, now()->addMinutes((int) config('auth.magic_link_minutes', 30) + 1));

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('games.index'))->with('status', 'Email confirmed. Your empire is saved to '.$user->email.'.');
    }

    private function nameFor(string $email): string
    {
        return Str::of(Str::before($email, '@'))->replace(['.', '_', '-', '+'], ' ')->title()->limit(60, '')->trim()->toString() ?: 'Ruler';
    }
}
