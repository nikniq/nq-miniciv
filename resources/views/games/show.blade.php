@extends('layouts.app')
@section('title', $game->name.' · Turn '.$game->turn)

@php
    use App\Game\Economy;
    use App\Game\Rules;
    $selTile = $selected['tile'] ?? null;
    $tileUrl = fn ($t) => route('games.show', ['game' => $game, 'tile' => $t->id]);
@endphp

@section('content')
@if (! $game->isActive())
    <div class="mb-4 rounded-lg border {{ $game->status === 'won' ? 'border-amber-600 bg-amber-950/50' : 'border-stone-700 bg-stone-900' }} px-5 py-4">
        <div class="text-lg font-semibold">
            @if ($game->status === 'won') 🏆 Victory! @else Game over. @endif
            @if ($winner) {{ $winner->name }} {{ $winner->is_ai ? 'wins' : 'prevails' }}. @endif
        </div>
        <div class="text-sm text-stone-300">{{ $game->events[0] ?? '' }}</div>
        <a href="{{ route('games.index') }}" class="mt-2 inline-block text-sm text-amber-300 hover:underline">Back to my games</a>
    </div>
@endif

{{-- Top bar --}}
<div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-stone-800 bg-stone-900/60 px-4 py-3 text-sm">
    <div class="flex items-center gap-2 font-semibold">
        <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ $player->color }}"></span>{{ $player->name }}
    </div>
    <div>Turn <b>{{ min($game->turn, $game->max_turns) }}</b><span class="text-stone-500">/{{ $game->max_turns }}</span></div>
    <div title="Treasury (income {{ $totals['gold'] }} − upkeep {{ $upkeep['total'] }} per turn)">💰 <b>{{ $player->gold }}</b> <span class="{{ $netGold < 0 ? 'text-red-400' : 'text-stone-500' }}">{{ $netGold >= 0 ? '+' : '' }}{{ $netGold }}</span></div>
    <div title="Science per turn">🔬 <b>+{{ $totals['science'] }}</b></div>
    <div title="Settlers ready">🏕️ <b>{{ $player->settlers }}</b></div>
    <div title="Army strength (upkeep {{ $upkeep['army'] }} gold/turn)">⚔ <b>{{ $player->army }}</b></div>
    <div title="Happiness: {{ $happiness['supply'] }} supplied, {{ $happiness['demand'] }} demanded">{{ $happiness['unhappy'] ? '😠' : '🙂' }} <b class="{{ $happiness['unhappy'] ? 'text-red-400' : '' }}">{{ $happiness['balance'] >= 0 ? '+' : '' }}{{ $happiness['balance'] }}</b></div>
    <div title="Share of land">🗺️ <b>{{ $landShare }}%</b> <span class="text-stone-500">of {{ $dominationShare }}%</span></div>
    <div title="Score">⭐ <b>{{ $rivals->firstWhere('empire.id', $player->id)['score'] ?? 0 }}</b></div>
    <div class="ml-auto">
        @if ($game->isActive())
            <form method="POST" action="{{ route('games.end-turn', $game) }}" id="end-turn-form">
                @csrf
                <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                <button class="rounded bg-amber-500 px-5 py-1.5 font-semibold text-stone-950 shadow hover:bg-amber-400" title="Enter">End turn ▶</button>
            </form>
        @endif
    </div>
</div>

@if ($happiness['unhappy'] && $game->isActive())
    <div class="mb-4 rounded border border-red-800 bg-red-950/40 px-4 py-2 text-sm text-red-200">
        Unrest: your people want {{ $happiness['demand'] }} happiness and you provide {{ $happiness['supply'] }}. Cities will not grow and production is down a quarter until you build monuments or temples (or lose some population).
    </div>
@endif
@if ($idleCities->isNotEmpty() && $game->isActive())
    <div class="mb-4 rounded border border-sky-800 bg-sky-950/40 px-4 py-2 text-sm text-sky-200">
        {{ $idleCities->count() === 1 ? 'A city is' : $idleCities->count().' cities are' }} producing Wealth while new buildings are available:
        @foreach ($idleCities as $c)
            <a href="{{ route('games.show', ['game' => $game, 'tile' => $c->tile_id]) }}" class="font-medium underline decoration-sky-500/50 hover:text-white">{{ $c->name }}</a>@if (! $loop->last), @endif
        @endforeach
    </div>
@endif
@if ($player->settlers > 0 && $game->isActive())
    <div class="mb-4 rounded border border-amber-800 bg-amber-950/40 px-4 py-2 text-sm text-amber-200">
        You have {{ $player->settlers }} {{ Str::plural('band', $player->settlers) }} of settlers. Click a tile at least {{ Rules::MIN_CITY_DISTANCE }} tiles from any city and choose <b>Found city</b>.
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
    {{-- Map --}}
    <div class="overflow-x-auto rounded-lg border border-stone-800 bg-stone-950 p-2 shadow-inner">
        <div id="map" data-panel-url="{{ route('games.panel', $game) }}">
            {!! $mapSvg !!}
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-1 text-xs text-stone-400">
            @foreach ($rivals as $r)
                <span class="{{ $r['empire']->eliminated ? 'line-through opacity-50' : '' }}"><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $r['empire']->color }}"></span>{{ $r['empire']->name }}</span>
            @endforeach
            <span class="ml-auto hidden sm:inline">circle = city with population · ★ capital · white dot = worked by your citizens</span>
        </div>
    </div>

    {{-- Side panel --}}
    <aside class="space-y-4">
        {{-- Selected tile / city (also served alone by games.panel) --}}
        @include('games._panel')

        {{-- Research --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <h2 class="font-semibold">Research</h2>
            @if ($research)
                <div class="mt-2 flex justify-between text-xs text-stone-400"><span>{{ $research['name'] }}</span><span>{{ $research['progress'] }}/{{ $research['cost'] }}@if ($research['turns'] !== null) · {{ $research['turns'] }}t @endif</span></div>
                <div class="mt-1 h-1.5 rounded bg-stone-800"><div class="h-1.5 rounded bg-sky-500" style="width: {{ 100 * $research['progress'] / $research['cost'] }}%"></div></div>
            @elseif ($availableTechs)
                <p class="mt-1 text-xs text-amber-300">Choose what to research. Science is banked until you do.</p>
            @else
                <p class="mt-1 text-xs text-stone-400">Everything is known.</p>
            @endif
            @if ($availableTechs && $game->isActive())
                <form method="POST" action="{{ route('games.research', $game) }}" class="mt-2 flex gap-2">
                    @csrf
                    <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                    <select name="tech" class="min-w-0 flex-1 rounded border border-stone-700 bg-stone-900 px-2 py-1.5 text-xs focus:border-amber-500 focus:outline-none">
                        @foreach ($availableTechs as $key => $t)
                            <option value="{{ $key }}" @selected($key === $player->current_tech)>{{ $t['name'] }} ({{ $t['cost'] }}) — {{ $t['unlocks'] }}</option>
                        @endforeach
                    </select>
                    <button class="rounded border border-stone-600 px-3 py-1.5 text-xs hover:bg-stone-800">{{ $research ? 'Switch' : 'Research' }}</button>
                </form>
            @endif
            @if ($player->technologies)
                <div class="mt-2 text-xs text-stone-500">Known: {{ collect($player->technologies)->map(fn ($t) => Rules::TECHS[$t]['name'])->join(', ') }}</div>
            @endif
        </section>

        {{-- Empires --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <h2 class="font-semibold">Empires</h2>
            <table class="mt-2 w-full text-xs">
                <thead class="text-stone-500"><tr><th class="text-left font-normal">Empire</th><th class="font-normal">Cities</th><th class="font-normal">Pop</th><th class="font-normal">Land</th><th class="font-normal">Tech</th><th class="font-normal">⚔</th><th class="text-right font-normal">Score</th></tr></thead>
                <tbody>
                @foreach ($rivals as $r)
                    <tr class="{{ $r['empire']->id === $player->id ? 'text-amber-200' : ($r['empire']->eliminated ? 'text-stone-600 line-through' : '') }}">
                        <td><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $r['empire']->color }}"></span>{{ $r['empire']->name }}</td>
                        <td class="text-center">{{ $r['cities'] }}</td>
                        <td class="text-center">{{ $r['pop'] }}</td>
                        <td class="text-center">{{ $r['share'] }}%</td>
                        <td class="text-center">{{ $r['techs'] }}</td>
                        <td class="text-center">{{ $r['army'] }}</td>
                        <td class="text-right font-medium">{{ $r['empire']->eliminated ? '✝' : $r['score'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        {{-- Cities list --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <h2 class="font-semibold">Your cities</h2>
            <ul class="mt-2 space-y-1 text-xs">
                @foreach ($player->cities as $c)
                    <li class="flex justify-between gap-2">
                        <a href="{{ route('games.show', ['game' => $game, 'tile' => $c->tile_id]) }}" class="hover:text-amber-300">{{ $c->name }} <span class="text-stone-500">({{ $c->population }})</span></a>
                        <span class="truncate text-stone-400">{{ Economy::buildName($c->current_build) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Events --}}
        @if ($game->events)
            <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
                <h2 class="font-semibold">Last turn</h2>
                <ul class="mt-2 space-y-1 text-xs text-stone-300">
                    @foreach ($game->events as $e)
                        <li>· {{ $e }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </aside>
</div>
@endsection
