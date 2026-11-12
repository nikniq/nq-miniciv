@extends('layouts.app')
@section('title', 'How to play · '.config('app.name'))

@php use App\Game\Rules; @endphp

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-3xl font-semibold">How to play</h1>
    <p class="mt-2 text-stone-400">A game lasts {{ Rules::MAX_TURNS }} turns. Each turn you manage your cities, pick research, and press <b>End turn</b> (or Enter). Your rivals act at the same time.</p>

    <section class="mt-6 rounded-lg border border-emerald-800/60 bg-emerald-950/30 p-5">
        <h2 class="font-semibold text-emerald-300">🏕️ Founding a city, step by step</h2>
        <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-stone-300">
            <li>Click one of your cities on the map. In the panel on the right press <b>Train settlers</b> (the city must be size 2 or more; it loses 1 population when the settlers are ready).</li>
            <li>End turns until the panel says the settlers are ready. The top bar shows 🏕️ with the number of bands you have.</li>
            <li>Legal sites light up <span class="rounded bg-emerald-500/30 px-1">green</span> on the map, and ★ marks the best one. Sites must be land, unclaimed, and at least 3 tiles from any city.</li>
            <li>Click a green tile and press <b>Found city here</b>, or press <b>Found at best site</b> in the banner.</li>
        </ol>
        <p class="mt-2 text-xs text-stone-500">Every new game also starts you with one band of settlers so you can found a second city on turn 1.</p>
    </section>

    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🏆 Ways to win</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li><b>Domination</b>: own {{ (int) (Rules::DOMINATION_SHARE * 100) }}% of all land.</li>
                <li><b>Culture</b>: reach {{ Rules::CULTURE_VICTORY }} total culture across your cities on a normal map (more on larger maps).</li>
                <li><b>Science</b>: research Exploration (the last technology) and be the first to build the <b>Grand Expedition</b> ({{ Rules::BUILDINGS['grand_expedition']['cost'] }} ⚒). It cannot be bought or hurried, and rivals racing for it show in Wonder races.</li>
                <li><b>Score</b>: highest score when turn {{ Rules::MAX_TURNS }} ends.</li>
            </ul>
            <p class="mt-2 text-xs text-stone-500">Score counts population, cities, techs, buildings, wonders, land, army and culture.</p>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🏕️ Cities and land</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li>Build <b>Settlers</b> (city must be size 2+), then click a tile at least {{ Rules::MIN_CITY_DISTANCE }} tiles from any city and <b>Found city</b>.</li>
                <li>The world starts hidden in <b>fog</b>. Your borders reveal {{ Rules::FOG_BORDER_RADIUS }} tiles around them every turn, a new city reveals {{ Rules::FOG_CITY_RADIUS }}, and a city can <b>send scouts</b> ({{ Rules::SCOUT_COST }} gold) to map {{ Rules::SCOUT_RADIUS }} tiles around it. You can only found cities on land you have seen.</li>
                <li>Citizens automatically work the best tiles inside your borders. Change a city's <b>Focus</b> to favour food, production, gold or science.</li>
                <li>Culture pushes borders outward. Monuments and temples speed it up.</li>
                <li>Food beyond 2 per citizen grows the city; a deficit shrinks it. Cities stop at size {{ Rules::SIZE_CAP }} without an Aqueduct ({{ Rules::SIZE_CAP_AQUEDUCT }} with one).</li>
            </ul>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">👑 Government</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                @foreach (Rules::GOVERNMENTS as $g)
                    <li><b>{{ $g['icon'] }} {{ $g['name'] }}</b>@if ($g['tech']) <span class="text-stone-500">({{ Rules::TECHS[$g['tech']]['name'] }})</span>@endif: {{ $g['blurb'] }}</li>
                @endforeach
                <li class="text-xs text-stone-500">Changing government means {{ Rules::ANARCHY_TURNS }} turns of anarchy with halved production and science.</li>
            </ul>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🏛️ National traits</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                @foreach (Rules::TRAITS as $t)
                    <li><b>{{ $t['icon'] }} {{ $t['name'] }}</b>: {{ $t['blurb'] }}</li>
                @endforeach
                <li class="text-xs text-stone-500">Pick one when you found an empire. Rivals take the trait that fits their personality.</li>
            </ul>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🔭 Great people</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li>Libraries, workshops, markets and temples (and their upgrades) earn great-person points every turn; wonders earn the most.</li>
                <li>At {{ Rules::GREAT_POINTS_BASE }} points (+{{ Rules::GREAT_POINTS_STEP }} each time) the kind you lean toward arrives at court and waits until you spend them:</li>
                @foreach (Rules::GREAT_PEOPLE as $gp)
                    <li class="ml-4"><b>{{ $gp['icon'] }} {{ $gp['name'] }}</b>: {{ $gp['blurb'] }}</li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">🙂 Happiness and gold</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li>Each city keeps {{ Rules::FREE_CITIZENS }} citizens content for free and adds {{ Rules::CITY_HAPPINESS }} happiness. Monuments, temples, colosseums and courthouses add more.</li>
                <li>If demand exceeds supply, growth stops and production drops by a quarter.</li>
                <li>Every {{ Rules::BUILDINGS_PER_GOLD }} buildings cost 1 gold a turn; every {{ Rules::ARMY_PER_GOLD }} army strength costs 1 gold.</li>
                <li>With Currency, each city can run one <b>trade route</b> ({{ Rules::TRADE_ROUTE_COST }} gold for the caravan) to a city within {{ Rules::TRADE_ROUTE_RANGE }} tiles. Longer routes to bigger cities pay more. Foreign routes add science, share gold with the partner and warm relations; domestic routes add food. War cuts them.</li>
                <li>Spend gold to <b>Buy now</b>: {{ Rules::BUY_RATE }} gold per missing hammer.</li>
            </ul>
        </section>

        <section class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="font-semibold text-amber-300">⚔ War and barbarians</h2>
            <ul class="mt-2 space-y-1 text-sm text-stone-300">
                <li>Units are not placed on the map. Each one joins a single empire-wide <b>army</b> whose strength is shown in the top bar and the Army panel. The army garrisons every city equally (the shield on each city marker) and attacks as a whole from any border.</li>
                <li>To attack a city its borders must touch yours and you must <b>declare war</b> first. War costs {{ Rules::WAR_WEARINESS }} happiness per enemy.</li>
                <li>Attack when your army beats the city's defence (size, walls, capital, garrison). Winning takes the city and its land. Losing costs half your army.</li>
                <li>Rivals accept peace once a war has dragged on, they are losing, or they were never that angry.</li>
                <li>The army gains <b>experience</b> from battles, raids and cleared camps: Seasoned, Veteran, then Elite, each rank adding {{ (int) (Rules::VETERAN_BONUS * 100) }}% to its fighting strength. Old units can be <b>retrained</b> as the best unit you know for gold in the Army panel.</li>
                <li><b>Raid</b> a bordering enemy city to loot gold and burn its food without taking it. Needs an army of {{ Rules::RAID_MIN_ARMY }}.</li>
                <li>Each rival has a personality and an <b>attitude</b> toward you. Gifts, trade and peace warm it; wars, conquests and tribute demands cool it. Friendly rivals do not attack you.</li>
                <li>A rival whose attitude reaches {{ Rules::ALLIANCE_ATTITUDE }} (Devoted) will agree to an <b>alliance</b> (🤝 in the Empires list). Allies join any war declared on the other and share maps. An alliance lapses if attitude falls below {{ Rules::ALLIANCE_LAPSE }}; declaring war on an ally is a betrayal everyone remembers.</li>
                <li>If your army dwarfs a neighbour's you can <b>demand tribute</b>; they pay, and resent it.</li>
                <li>Rivals at peace who are at least neutral will <b>trade technologies</b> (🔁 techs in the Empires list), for one of yours or for gold. Friendly rivals ask less; Scholars ask more. One deal per {{ Rules::TECH_TRADE_COOLDOWN }} turns each.</li>
                <li>Now and then a <b>dilemma</b> arrives: envoys, caravans, prophets, mercenaries, sickness, refugees. Pick one of two answers before the next turn ends, or the cautious option is taken for you.</li>
                <li>☠ From turn {{ Rules::RAID_FROM_TURN }} camps send <b>war bands</b> at cities within {{ Rules::RAID_REACH }} tiles. You get a turn's warning (a red banner and arrow). If the city's defence holds, your army gains experience; if not, the city is sacked: stores burned, gold taken, one population lost. Clearing the camp first calls the raid off.</li>
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
                    <div class="flex justify-between"><b>{{ $b['icon'] ?? '' }} {{ $b['name'] }}</b><span class="text-stone-500">{{ $b['cost'] }} ⚒</span></div>
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
        <p class="mt-3 text-xs text-stone-500">Natural wonders are unique tiles worth working: @foreach (Rules::NATURAL_WONDERS as $nw){{ $nw['glyph'] }} {{ $nw['name'] }} (🌾{{ $nw['food'] }} ⚒{{ $nw['prod'] }} 💰{{ $nw['gold'] }} 🎭{{ $nw['culture'] }})@if (! $loop->last), @endif @endforeach.</p>
        <p class="mt-3 text-xs text-stone-500">Resources add to a tile's yield: @foreach (Rules::RESOURCES as $r){{ $r['icon'] }} {{ $r['name'] }}@if (! $loop->last), @endif @endforeach.</p>
    </section>
</div>
@endsection
