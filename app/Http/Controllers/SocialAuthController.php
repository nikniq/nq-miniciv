<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Sign in with Google or with a nikniq.com account. Both are hidden until
 * their client id is configured. A guest who signs in this way keeps their games.
 */
class SocialAuthController extends Controller
{
    /** provider => [label, users column] */
    public const PROVIDERS = [
        'google' => ['Google', 'google_id'],
        'nikniq' => ['nikniq', 'nikniq_id'],
    ];

    public static function enabled(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]) && (bool) config("services.{$provider}.client_id");
    }

    /** Providers with a configured client, for the login and register pages. */
    public static function available(): array
    {
        return array_values(array_filter(array_keys(self::PROVIDERS), fn ($p) => self::enabled($p)));
    }

    public function redirect(string $provider): RedirectResponse
    {
        if (! self::enabled($provider)) {
            return redirect()->route('login')->withErrors(['email' => self::PROVIDERS[$provider][0].' sign-in is not configured.']);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        [$label, $column] = self::PROVIDERS[$provider];
        if (! self::enabled($provider)) {
            return redirect()->route('login')->withErrors(['email' => "{$label} sign-in is not configured."]);
        }
        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => "{$label} sign-in was cancelled or failed. Try again."]);
        }
        $email = Str::lower((string) $social->getEmail());
        if (! $social->getId() || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')->withErrors(['email' => "{$label} did not share an email address, which we need for your account."]);
        }

        $user = User::where($column, $social->getId())->first();
        if (! $user && ($byEmail = User::where('email', $email)->first())) {
            // Linking to an existing account by email is safe only when the
            // provider vouches for the address; otherwise anyone could claim it.
            if (! $this->emailVerified($provider, $social)) {
                return redirect()->route('login')->withErrors(['email' => "An account already uses {$email}. Sign in with an email link first, then use {$label} next time."]);
            }
            $user = $byEmail;
        }

        $guest = $request->user();
        if (! $user && $guest && $guest->isGuest()) {
            $guest->forceFill([
                'name' => $social->getName() ?: $guest->name,
                'email' => $email,
                $column => $social->getId(),
                'avatar' => $social->getAvatar() ?: $guest->avatar,
                'is_guest' => false,
                'email_verified_at' => $this->emailVerified($provider, $social) ? now() : null,
            ])->save();
            $user = $guest;
        } elseif ($user) {
            $user->forceFill([
                $column => $social->getId(),
                'avatar' => $social->getAvatar() ?: $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? ($this->emailVerified($provider, $social) ? now() : null),
            ])->save();
        } else {
            $user = User::create([
                'name' => Str::limit($social->getName() ?: ($social->getNickname() ?: Str::before($email, '@')), 60, ''),
                'email' => $email,
                'password' => null,
                $column => $social->getId(),
                'avatar' => $social->getAvatar(),
                'email_verified_at' => $this->emailVerified($provider, $social) ? now() : null,
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('games.index'))->with('status', "Signed in with {$label}.");
    }

    private function emailVerified(string $provider, SocialUser $social): bool
    {
        if ($provider === 'google') {
            return true; // Google only issues verified addresses to OAuth clients.
        }
        $raw = method_exists($social, 'getRaw') ? $social->getRaw() : [];

        return ! empty($raw['email_verified_at']) || ($raw['email_verified'] ?? false) === true;
    }
}
