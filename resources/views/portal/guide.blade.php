@extends('layouts.app')
@section('title', 'How to play · '.config('app.name'))

@php use App\Game\Rules; @endphp

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-3xl font-semibold">How to play</h1>
    <p class="mt-2 text-stone-400">A game lasts {{ Rules::MAX_TURNS }} turns. Each turn you manage your cities, pick research, and press <b>End turn</b> (or Enter). Your rivals act at the same time.</p>

    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🏆 Ways to win</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li><b>Domination</b>: own {{ (int) (Rules::DOMINATION_SHARE * 100) }}% of all land.</li>
                <li><b>Culture</b>: reach {{ Rules::CULTURE_VICTORY }} total culture across your cities.</li>
                <li><b>Score</b>: highest score when turn {{ Rules::MAX_TURNS }} ends.</li>
            </ul>
            <p class="mt-2 text-xs text-stone-500">Score counts population, cities, techs, buildings, wonders, land, army and culture.</p>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🏕️ Cities and land</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li>Build <b>Settlers</b> (city must be size 2+), then click a tile at least {{ Rules::MIN_CITY_DISTANCE }} tiles from any city and <b>Found city</b>.</li>
                <li>Citizens automatically work the best tiles inside your borders. Change a city's <b>Focus</b> to favour food, production, gold or science.</li>
                <li>Culture pushes borders outward. Monuments and temples speed it up.</li>
                <li>Food beyond 2 per citizen grows the city; a deficit shrinks it.</li>
            </ul>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🙂 Happiness and gold</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li>Each city keeps {{ Rules::FREE_CITIZENS }} citizens content for free and adds {{ Rules::CITY_HAPPINESS }} happiness. Monuments, temples, colosseums and courthouses add more.</li>
                <li>If demand exceeds supply, growth stops and production drops by a quarter.</li>
                <li>Every {{ Rules::BUILDINGS_PER_GOLD }} buildings cost 1 gold a turn; every {{ Rules::ARMY_PER_GOLD }} army strength costs 1 gold.</li>
                <li>Spend gold to <b>Buy now</b>: {{ Rules::BUY_RATE }} gold per missing hammer.</li>
            </ul>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">⚔ War and barbarians</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li>Units add to one empire-wide <b>army</b>. Your army defends every city a little and attacks as a whole.</li>
                <li>To attack a city its borders must touch yours and you must <b>declare war</b> first. War costs {{ Rules::WAR_WEARINESS }} happiness per enemy.</li>
                <li>Attack when your army beats the city's defence (size, walls, capital, garrison). Winning takes the city and its land. Losing costs half your army.</li>
                <li>Rivals accept peace once a war has dragged on or they are losing.</li>
                <li>☠ Barbarian camps pillage food from cities within 2 tiles. Clear one with an army of {{ Rules::CAMP_CLEAR_ARMY }}+ to loot gold.</li>
            </ul>
        </section>
    </div>

    <section class="mt-8">
        <h2 class="text-xl font-semibold">Technologies</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (Rules::TECHS as $key => $t)
                <div class="rounded border border-stone-800 bg-stone-900/40 px-3 py-2 text-sm">
                    <div class="flex justify-between"><b>{{ $t['name'] }}</b><span class="text-stone-500">{{ $t['cost'] }} 🔬</span></div>
                    <div class="text-xs text-stone-400">{{ $t['unlocks'] }}</div>
                    @if ($t['requires'])
                        <div class="text-xs text-stone-600">needs {{ collect($t['requires'])->map(fn ($r) => Rules::TECHS[$r]['name'])->join(', ') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-semibold">Buildings and wonders</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (Rules::BUILDINGS as $key => $b)
                <div class="rounded border {{ $b['wonder'] ? 'border-amber-900/60 bg-amber-950/20' : 'border-stone-800 bg-stone-900/40' }} px-3 py-2 text-sm">
                    <div class="flex justify-between"><b>{{ $b['name'] }}</b><span class="text-stone-500">{{ $b['cost'] }} ⚒</span></div>
                    <div class="text-xs text-stone-400">{{ $b['blurb'] }}</div>
                    <div class="text-xs text-stone-600">
                        @if ($b['tech']) {{ Rules::TECHS[$b['tech']]['name'] }} @else no tech @endif
                        @if ($b['requires']) · needs {{ Rules::BUILDINGS[$b['requires']]['name'] }} @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-8 mb-12">
        <h2 class="text-xl font-semibold">Terrain</h2>
        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            @foreach (Rules::TERRAIN as $key => $t)
                <span class="rounded border border-stone-800 px-3 py-1"><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background: {{ $t['color'] }}"></span>{{ $t['name'] }} <span class="text-stone-500">🌾{{ $t['food'] }} ⚒{{ $t['prod'] }} 💰{{ $t['gold'] }}</span></span>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-stone-500">Resources add to a tile's yield: @foreach (Rules::RESOURCES as $r){{ $r['icon'] }} {{ $r['name'] }}@if (! $loop->last), @endif @endforeach.</p>
    </section>
</div>
@endsection
