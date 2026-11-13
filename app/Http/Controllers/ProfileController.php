<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Nickname changes, with a live availability check. */
class ProfileController extends Controller
{
    public const RESERVED = ['admin', 'administrator', 'moderator', 'system', 'guest', 'barbarians', 'support'];

    public function checkNickname(Request $request): JsonResponse
    {
        $nickname = $this->clean((string) $request->query('nickname'));
        $error = $this->problem($nickname, $request->user());

        return response()->json(['nickname' => $nickname, 'available' => $error === null, 'message' => $error ?? 'Available.']);
    }

    public function updateNickname(Request $request): RedirectResponse
    {
        $nickname = $this->clean((string) $request->input('nickname'));
        if ($error = $this->problem($nickname, $request->user())) {
            return back()->withInput()->withErrors(['nickname' => $error]);
        }
        $request->user()->forceFill(['name' => $nickname])->save();

        return back()->with('status', "You are now known as {$nickname}.");
    }

    /** Trim and collapse inner spaces. */
    private function clean(string $nickname): string
    {
        return Str::of($nickname)->squish()->toString();
    }

    /** Why this nickname can't be used, or null when it can. */
    public static function problem(string $nickname, User $user): ?string
    {
        $v = Validator::make(['nickname' => $nickname], [
            'nickname' => ['required', 'min:3', 'max:24', 'regex:/^[\pL\pN][\pL\pN _.\-]*$/u'],
        ], [
            'nickname.required' => 'Enter a nickname.',
            'nickname.min' => 'Use at least 3 characters.',
            'nickname.max' => 'Use at most 24 characters.',
            'nickname.regex' => 'Use letters, numbers, spaces, dots, dashes or underscores, starting with a letter or number.',
        ]);
        if ($v->fails()) {
            return $v->errors()->first('nickname');
        }
        if (in_array(Str::lower($nickname), self::RESERVED, true)) {
            return 'That nickname is reserved.';
        }
        if ($nickname === $user->name) {
            return 'That is already your nickname.';
        }
        $taken = User::where('id', '!=', $user->id)->whereRaw('LOWER(name) = ?', [Str::lower($nickname)])->exists();

        return $taken ? 'That nickname is taken.' : null;
    }
}
