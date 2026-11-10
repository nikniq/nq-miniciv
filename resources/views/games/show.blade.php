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
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold">
                    @if ($game->status === 'won') 🏆 Victory! @elseif ($game->status === 'lost') Defeat. @else The age has ended. @endif
                    @if ($winner) {{ $winner->name }} {{ $winner->is_ai ? 'wins' : 'prevails' }}. @endif
                </div>
                <div class="mt-1 text-sm text-stone-300">{{ $game->events[0] ?? '' }}</div>
                <ol class="mt-3 space-y-0.5 text-sm">
                    @foreach ($rivals as $i => $r)
                        <li class="{{ $r['empire']->id === $player->id ? 'font-semibold text-amber-200' : 'text-stone-300' }}">
                            {{ $i + 1 }}. <span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $r['empire']->color }}"></span>{{ $r['empire']->name }}
                            <span class="text-stone-500">· {{ $r['cities'] }} cities · {{ $r['share'] }}% land · {{ $r['culture'] }} culture · score</span> {{ $r['empire']->eliminated ? 'eliminated' : $r['score'] }}
                        </li>
                    @endforeach
                </ol>
                <div class="mt-3 flex gap-3 text-sm">
                    <a href="{{ route('games.index') }}" class="rounded bg-amber-500 px-3 py-1.5 font-medium text-stone-950 hover:bg-amber-400">Play again</a>
                    <a href="{{ route('hall') }}" class="rounded border border-stone-700 px-3 py-1.5 hover:bg-stone-800">Hall of Fame</a>
                    @if (auth()->user()->isGuest())
                        <a href="{{ route('register') }}" class="rounded border border-stone-700 px-3 py-1.5 hover:bg-stone-800">Save under your name</a>
                    @endif
                </div>
            </div>
            @if ($chart)
                <div class="w-full max-w-sm">
                    <div class="mb-1 text-xs text-stone-500">Score over {{ $game->turn - 1 }} turns</div>
                    {!! $chart !!}
                </div>
            @endif
        </div>
    </div>
@endif

{{-- Turn toasts: what happened last turn, dismissible, newest first --}}
@if ($toasts->isNotEmpty() && $game->isActive())
    <div id="toasts" class="pointer-events-none fixed bottom-4 right-4 z-40 flex w-80 max-w-[90vw] flex-col gap-2">
        @foreach ($toasts->take(6) as $i => $t)
            @php
                $style = ['war' => 'border-red-700 bg-red-950/90 text-red-100', 'bad' => 'border-orange-700 bg-orange-950/90 text-orange-100', 'great' => 'border-amber-600 bg-amber-950/90 text-amber-100', 'attention' => 'border-sky-700 bg-sky-950/90 text-sky-100', 'info' => 'border-stone-700 bg-stone-900/90 text-stone-200'][$t['kind']];
                $icon = ['war' => '⚔', 'bad' => '⚠', 'great' => '✦', 'attention' => '➜', 'info' => '·'][$t['kind']];
            @endphp
            <div class="toast pointer-events-auto flex items-start gap-2 rounded-lg border px-3 py-2 text-sm shadow-lg backdrop-blur {{ $style }}" style="animation-delay: {{ $i * 80 }}ms">
                <span class="mt-0.5 shrink-0">{{ $icon }}</span>
                <span class="flex-1">{{ $t['text'] }}</span>
                <button type="button" class="shrink-0 opacity-60 hover:opacity-100" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
            </div>
        @endforeach
        @if ($toasts->count() > 6)
            <div class="pointer-events-auto text-right text-xs text-stone-500">+{{ $toasts->count() - 6 }} more in “Last turn” below</div>
        @endif
    </div>
@endif

{{-- Top bar --}}
<div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-stone-800 bg-stone-900/60 px-4 py-3 text-sm">
    <div class="flex items-center gap-2 font-semibold">
        <span class="flex h-6 w-6 items-center justify-center rounded-full text-sm ring-2" style="background: {{ $player->color }}22; --tw-ring-color: {{ $player->color }}">{{ Rules::crest($player->name) }}</span>{{ $player->name }}
    </div>
    <div>Turn <b>{{ min($game->turn, $game->max_turns) }}</b><span class="text-stone-500">/{{ $game->max_turns }}</span></div>
    <div title="Treasury: income {{ $totals['gold'] }} (incl. trade {{ $tradeGold }} from {{ $tradePartners->count() }} peaceful neighbours{{ $player->knows('currency') ? '' : ', needs Currency' }}) − upkeep {{ $upkeep['total'] }} per turn">💰 <b>{{ $player->gold }}</b> <span class="{{ $netGold < 0 ? 'text-red-400' : 'text-stone-500' }}">{{ $netGold >= 0 ? '+' : '' }}{{ $netGold }}</span></div>
    <div title="Science per turn">🔬 <b>+{{ $totals['science'] }}</b></div>
    <div title="Settlers ready">🏕️ <b>{{ $player->settlers }}</b></div>
    <div title="Army strength (upkeep {{ $upkeep['army'] }} gold/turn)">⚔ <b>{{ $player->army }}</b></div>
    <div title="Happiness: {{ $happiness['supply'] }} supplied, {{ $happiness['demand'] }} demanded">{{ $happiness['unhappy'] ? '😠' : '🙂' }} <b class="{{ $happiness['unhappy'] ? 'text-red-400' : '' }}">{{ $happiness['balance'] >= 0 ? '+' : '' }}{{ $happiness['balance'] }}</b></div>
    <div title="Share of land">🗺️ <b>{{ $landShare }}%</b> <span class="text-stone-500">of {{ $dominationShare }}%</span></div>
    <div title="Culture toward a cultural victory">🎭 <b>{{ $culture }}</b> <span class="text-stone-500">of {{ $cultureGoal }}</span></div>
    @if ($wars)<div class="text-red-300" title="At war">⚔ at war ({{ $wars }})</div>@endif
    <div title="Score">⭐ <b>{{ $rivals->firstWhere('empire.id', $player->id)['score'] ?? 0 }}</b></div>
    <div class="ml-auto flex items-center gap-3">
        <a href="{{ route('guide') }}" target="_blank" class="text-xs text-stone-400 hover:text-white" title="How to play">? help</a>
        @if ($game->isActive())
            <form method="POST" action="{{ route('games.end-turn', $game) }}" id="end-turn-form"
                  data-warn="{{ collect([$researchUnset ? 'no research is selected' : null, $settlersWaiting ? 'settlers are waiting to found a city' : null, $idleCities->isNotEmpty() ? 'cities are idling on Wealth' : null])->filter()->join('; ') }}">
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
    <div class="rounded-lg border border-stone-800 bg-stone-950 p-2 shadow-inner">
        <div class="relative">
            <div id="map" data-panel-url="{{ route('games.panel', $game) }}" data-game="{{ $game->id }}" class="overflow-hidden rounded cursor-grab touch-none" style="aspect-ratio: {{ $game->width }} / {{ $game->height }}; max-height: 78vh;">
                {!! $mapSvg !!}
            </div>
            <div class="absolute left-2 top-2 flex flex-col gap-1 text-sm">
                <button type="button" data-zoom="in" class="h-7 w-7 rounded bg-stone-900/90 text-stone-200 shadow hover:bg-stone-800" title="Zoom in (scroll)">+</button>
                <button type="button" data-zoom="out" class="h-7 w-7 rounded bg-stone-900/90 text-stone-200 shadow hover:bg-stone-800" title="Zoom out">−</button>
                <button type="button" data-zoom="reset" class="h-7 w-7 rounded bg-stone-900/90 text-xs text-stone-200 shadow hover:bg-stone-800" title="Fit whole map">⤢</button>
                <button type="button" data-zoom="home" class="h-7 w-7 rounded bg-stone-900/90 text-xs text-stone-200 shadow hover:bg-stone-800" title="Centre on capital" data-tile="{{ $player->cities->firstWhere('is_capital', true)?->tile_id ?? $player->cities->first()?->tile_id }}">⌂</button>
            </div>
            <div id="minimap" class="absolute bottom-2 right-2 w-24 cursor-crosshair opacity-70 transition hover:opacity-100 sm:w-32" title="Click to pan">
                {!! $minimap !!}
            </div>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-1 text-xs text-stone-400">
            @foreach ($rivals as $r)
                <span class="{{ $r['empire']->eliminated ? 'line-through opacity-50' : '' }}"><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $r['empire']->color }}"></span>{{ $r['empire']->name }}</span>
            @endforeach
            <span class="ml-auto hidden sm:inline">drag to pan · scroll to zoom · circle = city (population) · ★ capital · dashed ring = walls · gold ring = wonder</span>
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
            <details class="mt-3 text-xs">
                <summary class="cursor-pointer text-stone-400 hover:text-white">Tech tree ({{ count($player->technologies ?? []) }}/{{ count(Rules::TECHS) }} known)</summary>
                <div class="mt-2 space-y-2">
                    @foreach ($techTiers as $tier => $keys)
                        <div class="grid grid-cols-2 gap-1">
                            <div class="col-span-2 text-[10px] uppercase tracking-wide text-stone-600">Tier {{ $tier + 1 }}</div>
                            @foreach ($keys as $key)
                                @php
                                    $t = Rules::TECHS[$key];
                                    $state = $player->knows($key) ? 'known' : (isset($availableTechs[$key]) ? 'available' : 'locked');
                                    $cls = ['known' => 'border-emerald-800 bg-emerald-950/40 text-emerald-200', 'available' => 'border-sky-700 bg-sky-950/40 text-sky-100', 'locked' => 'border-stone-800 text-stone-500'][$state];
                                @endphp
                                @if ($state === 'available' && $game->isActive())
                                    <form method="POST" action="{{ route('games.research', $game) }}">
                                        @csrf
                                        <input type="hidden" name="tech" value="{{ $key }}">
                                        <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                        <button class="w-full rounded border px-2 py-1 text-left {{ $cls }} {{ $key === $player->current_tech ? 'ring-1 ring-sky-400' : 'hover:bg-sky-900/40' }}" title="{{ $t['unlocks'] }}">
                                            <div class="font-medium">{{ $t['name'] }}</div><div class="opacity-70">{{ $t['cost'] }} 🔬 · {{ $t['unlocks'] }}</div>
                                        </button>
                                    </form>
                                @else
                                    <div class="rounded border px-2 py-1 {{ $cls }}" title="{{ $t['unlocks'] }}@if ($t['requires']) · needs {{ collect($t['requires'])->map(fn ($r) => Rules::TECHS[$r]['name'])->join(', ') }}@endif">
                                        <div class="font-medium">{{ $t['name'] }}</div><div class="opacity-70 truncate">{{ $t['unlocks'] }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </details>
        </section>

        {{-- Empires --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <h2 class="font-semibold">Empires</h2>
            <table class="mt-2 w-full text-xs">
                <thead class="text-stone-500"><tr><th class="text-left font-normal">Empire</th><th class="font-normal">Cities</th><th class="font-normal">Land</th><th class="font-normal">⚔</th><th class="font-normal">🎭</th><th class="text-right font-normal">Score</th><th></th></tr></thead>
                <tbody>
                @foreach ($rivals as $r)
                    <tr class="{{ $r['empire']->id === $player->id ? 'text-amber-200' : ($r['empire']->eliminated ? 'text-stone-600 line-through' : '') }}">
                        <td><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $r['empire']->color }}"></span>{{ Rules::crest($r['empire']->name) }} {{ $r['empire']->name }}</td>
                        <td class="text-center">{{ $r['cities'] }}</td>
                        <td class="text-center">{{ $r['share'] }}%</td>
                        <td class="text-center">{{ $r['army'] }}</td>
                        <td class="text-center">{{ $r['culture'] }}</td>
                        <td class="text-right font-medium">{{ $r['empire']->eliminated ? '✝' : $r['score'] }}</td>
                        <td class="pl-2 text-right">
                            @if ($r['relation'] && ! $r['empire']->eliminated && $game->isActive())
                                @if ($r['relation']['state'] === 'war')
                                    <form method="POST" action="{{ route('games.peace', [$game, $r['empire']]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                        <button class="rounded border px-1.5 py-0.5 text-[10px] {{ $r['peaceLikely'] ? 'border-emerald-700 text-emerald-200 hover:bg-emerald-950' : 'border-stone-700 text-stone-400 hover:bg-stone-800' }}" title="{{ $r['peaceLikely'] ? 'They would accept' : 'They may refuse' }}">☮ peace</button>
                                    </form>
                                    <span class="text-[10px] text-red-400">war</span>
                                @elseif ($r['warProblem'] === null)
                                    <form method="POST" action="{{ route('games.war', [$game, $r['empire']]) }}" class="inline" onsubmit="return confirm('Declare war on {{ $r['empire']->name }}?')">
                                        @csrf
                                        <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                        <button class="rounded border border-stone-700 px-1.5 py-0.5 text-[10px] text-stone-400 hover:border-red-800 hover:text-red-200">⚔ war</button>
                                    </form>
                                @else
                                    <span class="text-[10px] text-stone-500" title="{{ $r['warProblem'] }}">treaty</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        @if ($chart && $game->isActive())
            <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
                <div class="flex items-baseline justify-between"><h2 class="font-semibold">Score over time</h2><span class="text-xs text-stone-500">{{ $difficulty['name'] }}</span></div>
                <div class="mt-2">{!! $chart !!}</div>
            </section>
        @endif

        {{-- Cities list --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <h2 class="font-semibold">Your cities</h2>
            <table class="mt-2 w-full text-xs">
                <thead class="text-stone-500"><tr><th class="text-left font-normal">City</th><th class="font-normal" title="Food surplus">🌾</th><th class="font-normal" title="Production">⚒</th><th class="font-normal" title="Gold / Science">💰/🔬</th><th class="text-right font-normal">Building</th></tr></thead>
                <tbody>
                @foreach ($player->cities as $c)
                    @php $cy = $cityYields[$c->id]; $cost = Economy::buildCost($c->current_build ?? ''); $left = ($cost !== null && $cy['prod'] > 0) ? (int) ceil(max(0, $cost - $c->production_stored) / $cy['prod']) : null; @endphp
                    <tr class="{{ $selTile && $selTile->id === $c->tile_id ? 'text-amber-200' : '' }}">
                        <td><a href="{{ route('games.show', ['game' => $game, 'tile' => $c->tile_id]) }}" class="map-tile-link hover:text-amber-300" data-tile="{{ $c->tile_id }}">{{ $c->name }} <span class="text-stone-500">{{ $c->population }}</span></a></td>
                        <td class="text-center {{ $cy['surplus'] < 0 ? 'text-red-400' : '' }}">{{ $cy['surplus'] >= 0 ? '+' : '' }}{{ $cy['surplus'] }}</td>
                        <td class="text-center">{{ $cy['prod'] }}</td>
                        <td class="text-center text-stone-400">{{ $cy['gold'] }}/{{ $cy['science'] }}</td>
                        <td class="truncate text-right text-stone-400">{{ Economy::buildName($c->current_build) }}@if ($left !== null) <span class="text-stone-600">{{ $left }}t</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
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
