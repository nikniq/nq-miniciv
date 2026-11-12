@extends('layouts.app')

@php use App\Game\Rules; @endphp

@section('content')
<section class="mx-auto grid max-w-6xl items-center gap-10 py-12 lg:grid-cols-[1fr_1.1fr]">
    <div class="text-center lg:text-left">
        <p class="text-sm uppercase tracking-widest text-amber-400">Turn-based empire building</p>
        <h1 class="mt-3 text-5xl font-semibold">{{ config('app.name') }}</h1>
        <p class="mt-4 text-lg text-stone-400">
            Found cities in a world hidden by fog, research {{ count(Rules::TECHS) }} technologies from Agriculture to Exploration,
            raise wonders, trade, ally and fight. Five ways to win: domination, culture, science, diplomacy or score.
        </p>
        <div class="mt-8 flex justify-center gap-3 lg:justify-start">
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
            <p class="mt-3 text-xs text-stone-500">No sign-up needed. Save your progress later with just an email or Google.</p>
        @endguest
        <p class="mt-6 text-sm text-stone-400"><a href="{{ route('guide') }}" class="text-amber-400 hover:underline">How to play</a> · <a href="{{ route('hall') }}" class="text-amber-400 hover:underline">Hall of Fame</a> · <a href="https://nikniq.com" class="text-stone-300 hover:underline">← back to nikniq.com</a></p>
    </div>
    <figure class="overflow-hidden rounded-xl border border-stone-800 shadow-2xl shadow-black/50">
        {!! \App\Game\HeroMap::svg() !!}
        <figcaption class="bg-stone-900/80 px-3 py-1.5 text-xs text-stone-500">Every game generates a new world: continents, rivers and natural wonders.</figcaption>
    </figure>
</section>

<section class="mx-auto grid max-w-6xl gap-4 pb-16 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
        ['🏕️', 'Expand', 'Scout into the fog, found cities on the best land and let culture push your borders. Pick a national trait and a pantheon that suit your plan.'],
        ['🏛️', 'Build', 'Granaries to cathedrals, Pyramids to the Grand Expedition. Great scientists, engineers, merchants and artists arrive as your cities flourish.'],
        ['🐪', 'Trade', 'Send caravans along trade routes, swap technologies with friendly rivals and keep the treasury full enough to rush what matters.'],
        ['🤝', 'Ally', 'Rivals have personalities and long memories. Keep their favours, earn their devotion, and an alliance may carry you to a diplomatic victory.'],
        ['⚔️', 'Fight', 'Barbarian war bands march on your cities. Veteran armies grow stronger with every battle; old units retrain into new ones.'],
        ['🎞️', 'Relive it', 'Every game ends with a replay of the world being won, charts of land and culture, and achievements that follow your account.'],
    ] as [$icon, $title, $text])
        <div class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">{{ $icon }} {{ $title }}</h2>
            <p class="mt-2 text-sm text-stone-400">{{ $text }}</p>
        </div>
    @endforeach
</section>
@endsection
