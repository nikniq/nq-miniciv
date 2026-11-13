@extends('layouts.app')
@section('title', 'Your record · '.config('app.name'))

@php use App\Game\Rules; @endphp

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center gap-4">
        @if ($user->avatar)
            <img src="{{ $user->avatar }}" alt="" referrerpolicy="no-referrer" class="h-12 w-12 rounded-full">
        @else
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-stone-800 text-xl">👤</div>
        @endif
        <div>
            <h1 class="text-2xl font-semibold">{{ $user->name }}</h1>
            <p class="text-sm text-stone-400">
                @if ($user->isGuest()) Playing as a guest. <a href="{{ route('register') }}" class="text-amber-400 hover:underline">Register</a> to keep this record on any device. @else Member since {{ $user->created_at->toFormattedDateString() }}. @endif
            </p>
        </div>
    </div>

    <section class="mt-6 rounded-lg border border-stone-800 bg-stone-900/50 p-4">
        <h2 class="font-semibold">Account</h2>
        <dl class="mt-2 grid gap-1 text-sm sm:grid-cols-[8rem_1fr]">
            <dt class="text-stone-500">Email</dt><dd>{{ $user->isGuest() ? 'Not saved yet' : $user->email }}</dd>
            <dt class="text-stone-500">Sign-in</dt><dd>{{ $user->google_id ? 'Google and email link' : ($user->isGuest() ? 'Guest, this browser only' : ($user->password ? 'Password or email link' : 'Email link')) }}</dd>
        </dl>
        <form method="POST" action="{{ route('profile.nickname') }}" id="nickname-form" data-check="{{ route('profile.nickname.check') }}" class="mt-4">
            @csrf
            @method('PUT')
            <label for="nickname" class="text-sm text-stone-400">Nickname</label>
            <div class="mt-1 flex gap-2">
                <input id="nickname" name="nickname" value="{{ old('nickname', $user->name) }}" maxlength="24" autocomplete="nickname" required
                    class="min-w-0 flex-1 rounded border border-stone-700 bg-stone-950 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
                <button class="rounded bg-amber-500 px-4 py-2 font-medium text-stone-950 hover:bg-amber-400 disabled:opacity-50">Save</button>
            </div>
            <p id="nickname-status" class="mt-1 min-h-5 text-xs {{ $errors->has('nickname') ? 'text-red-400' : 'text-stone-500' }}" aria-live="polite">{{ $errors->first('nickname') ?: '3 to 24 characters. Letters, numbers, spaces, dots, dashes and underscores.' }}</p>
        </form>
    </section>

    <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach (['games' => 'Games', 'finished' => 'Finished', 'wins' => 'Wins', 'best' => 'Best score', 'turns' => 'Turns played'] as $k => $label)
            <div class="rounded-lg border border-stone-800 bg-stone-900/50 p-3 text-center">
                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                <dd class="text-xl font-semibold">{{ $stats[$k] }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($winsByKind->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2 text-sm">
            <span class="text-stone-500">Victories:</span>
            @foreach ($winsByKind as $label => $w)
                <span class="rounded border border-amber-800/60 bg-amber-950/30 px-2 py-0.5">{{ $w['icon'] }} {{ $label }} × {{ $w['n'] }}</span>
            @endforeach
        </div>
    @endif

    <h2 class="mt-8 text-lg font-semibold">Achievements <span class="text-sm font-normal text-stone-500">{{ count($earned) }}/{{ count(Rules::ACHIEVEMENTS) }}</span></h2>
    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (Rules::ACHIEVEMENTS as $id => $a)
            @php $got = $earned[$id] ?? null; @endphp
            <div class="flex items-start gap-3 rounded-lg border p-3 {{ $got ? 'border-amber-700/60 bg-amber-950/30' : 'border-stone-800 bg-stone-900/40 opacity-60' }}">
                <span class="text-2xl {{ $got ? '' : 'grayscale' }}">{{ $a['icon'] }}</span>
                <div>
                    <div class="font-medium">{{ $a['name'] }}</div>
                    <div class="text-xs text-stone-400">{{ $a['blurb'] }}</div>
                    @if ($got)<div class="mt-0.5 text-[10px] text-amber-300/80">Earned {{ $got['at'] }}, turn {{ $got['turn'] }}</div>@endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
