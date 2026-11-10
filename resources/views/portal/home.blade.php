@extends('layouts.app')

@section('content')
<section class="mx-auto flex max-w-3xl flex-col items-center py-16 text-center">
    <p class="text-sm uppercase tracking-widest text-amber-400">Turn-based empire building</p>
    <h1 class="mt-3 text-5xl font-semibold">{{ config('app.name') }}</h1>
    <p class="mt-4 max-w-xl text-lg text-stone-400">
        Found cities, claim land, research your way from Agriculture to Banking and raise wonders
        before your rivals do. Rule most of the known world, or finish with the greatest empire when time runs out.
    </p>
    <div class="mt-8 flex gap-3">
        @auth
            <a href="{{ route('games.index') }}" class="rounded bg-amber-500 px-5 py-2.5 font-medium text-stone-950 hover:bg-amber-400">Go to my games</a>
        @else
            <form method="POST" action="{{ route('play') }}">
                @csrf
                <button class="rounded bg-amber-500 px-6 py-3 text-lg font-semibold text-stone-950 hover:bg-amber-400">Play now</button>
            </form>
            <a href="{{ route('login') }}" class="rounded border border-stone-700 px-5 py-3 font-medium text-stone-200 hover:bg-stone-800">Log in</a>
        @endauth
    </div>
    @guest
        <p class="mt-3 text-xs text-stone-500">No sign-up needed. Register later if you want to keep your progress across devices.</p>
    @endguest
    <p class="mt-6 text-sm text-stone-400"><a href="{{ route('guide') }}" class="text-amber-400 hover:underline">How to play</a> · <a href="{{ route('hall') }}" class="text-amber-400 hover:underline">Hall of Fame</a></p>
</section>

<section class="mx-auto grid max-w-5xl gap-4 pb-16 sm:grid-cols-3">
    <div class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
        <h2 class="font-semibold text-amber-300">Expand</h2>
        <p class="mt-2 text-sm text-stone-400">Train settlers and found cities on the best land. Culture pushes your borders outward and locks in resources before anyone else.</p>
    </div>
    <div class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
        <h2 class="font-semibold text-amber-300">Build</h2>
        <p class="mt-2 text-sm text-stone-400">Granaries, libraries, markets and more. Rush-buy with gold when a rival is about to finish the wonder you wanted.</p>
    </div>
    <div class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
        <h2 class="font-semibold text-amber-300">Conquer</h2>
        <p class="mt-2 text-sm text-stone-400">Raise warriors, swordsmen and catapults. When a neighbour's city borders your land and your army outmatches its defences, take it, land and all.</p>
    </div>
</section>
@endsection
