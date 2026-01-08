<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use App\Models\EventLog;
use App\Models\User;
use App\Services\EventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\MiniCivState;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        if (! $this->twoFactorEnabled()) {
            Auth::login($user, $remember);
            $request->session()->regenerate();

            // If the user has a saved MiniCiv state, make it available to the next request
            try {
                $saved = MiniCivState::where('user_id', $user->id)->first();
                if ($saved && $saved->state) {
                    $request->session()->flash('miniciv_restore', $saved->state);
                }
            } catch (\Throwable $e) {
                // ignore restore failures
            }

            EventLogger::log(EventLog::TYPE_LOGIN, $user->id, [
                'email' => $user->email,
                'remember' => $remember,
                'two_factor' => false,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return redirect()->intended('/dashboard')
                ->with('status', 'Welcome back!');
        }

        $this->sendTwoFactorCode($user);

        $request->session()->put('login.2fa.user_id', $user->id);
        $request->session()->put('login.2fa.remember', $remember);

        return redirect()->route('login.two-factor.show')
            ->with('status', 'We emailed a 6-digit security code to '.$this->maskEmail($user->email).'.');
    }

    /**
     * Log the user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            try {
                MiniCivState::where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {
                // Don't prevent logout for cleanup failures
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logged-out');
    }

    public function showTwoFactorForm(Request $request): View|RedirectResponse
    {
        if (! $this->twoFactorEnabled()) {
            return redirect()->route('login');
        }

        if (! $request->session()->has('login.2fa.user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        if (! $this->twoFactorEnabled()) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $userId = $request->session()->get('login.2fa.user_id');

        if (! $userId) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your login session expired. Please sign in again.',
            ]);
        }

        $cached = Cache::get($this->cacheKey($userId));

        if (! $cached) {
            return back()->withErrors([
                'code' => 'Security code expired. Request a new code to continue.',
            ]);
        }

        if ((string) $cached['code'] !== trim($request->input('code'))) {
            return back()->withErrors([
                'code' => 'Security code is incorrect.',
            ]);
        }

        Cache::forget($this->cacheKey($userId));

        $remember = (bool) $request->session()->pull('login.2fa.remember', false);
        $request->session()->forget('login.2fa.user_id');

        Auth::loginUsingId($userId, $remember);
        $request->session()->regenerate();

        // If the user has a saved MiniCiv state, make it available to the next request
        try {
            $saved = MiniCivState::where('user_id', $userId)->first();
            if ($saved && $saved->state) {
                $request->session()->flash('miniciv_restore', $saved->state);
            }
        } catch (\Throwable $e) {
            // ignore restore failures
        }

        EventLogger::log(EventLog::TYPE_LOGIN, $userId, [
            'two_factor' => true,
            'remember' => $remember,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return redirect()->intended('/dashboard')
            ->with('status', 'Welcome back!');
    }

    public function resendTwoFactor(Request $request): RedirectResponse
    {
        if (! $this->twoFactorEnabled()) {
            return redirect()->route('login');
        }

        $userId = $request->session()->get('login.2fa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget(['login.2fa.user_id', 'login.2fa.remember']);

            return redirect()->route('login')->withErrors([
                'email' => 'Unable to locate your account. Please sign in again.',
            ]);
        }

        $this->sendTwoFactorCode($user);

        return back()->with('status', 'A new security code has been sent to '.$this->maskEmail($user->email).'.');
    }

    private function sendTwoFactorCode(User $user): void
    {
        $code = random_int(100000, 999999);

        Cache::put($this->cacheKey($user->id), [
            'code' => (string) $code,
            'expires_at' => now()->addMinutes(10),
        ], now()->addMinutes(10));

        Mail::to($user->email)->send(new TwoFactorCodeMail((string) $code));
    }

    private function cacheKey(int $userId): string
    {
        return 'login:2fa:'.$userId;
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $localMask = substr($local, 0, 1).str_repeat('*', max(strlen($local) - 1, 3));

        return $localMask.'@'.$domain;
    }

    private function twoFactorEnabled(): bool
    {
        return (bool) config('auth.two_factor.enabled', true);
    }
}
