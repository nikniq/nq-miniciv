@extends('layouts.app')
@section('title', $game->name.' · Turn '.$game->turn)
@section('mood', $game->status === 'won' ? 'victory' : ($wars > 0 ? 'war' : 'peace'))
@section('cue', $game->status === 'won' ? 'win' : (collect($game->events ?? [])->contains(fn ($e) => str_contains($e, 'Achievement')) ? 'achievement' : (collect($game->events ?? [])->contains(fn ($e) => str_contains($e, 'declared war on you')) ? 'war' : (session('status') && str_contains(session('status'), 'founded') ? 'found' : ''))))

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
            @if ($replay)
                <div class="w-full max-w-md">
                    <div class="mb-1 flex items-center justify-between text-xs text-stone-500"><span>🎞️ Replay: how the world was won</span><span id="replay-turn">turn 0</span></div>
                    <canvas id="replay-canvas" class="w-full rounded border border-stone-700 bg-stone-950" style="image-rendering: pixelated; aspect-ratio: {{ $replay['w'] }} / {{ $replay['h'] }}"></canvas>
                    <div class="mt-1 flex items-center gap-2">
                        <button type="button" id="replay-play" class="rounded bg-amber-500 px-2 py-0.5 text-xs font-medium text-stone-950 hover:bg-amber-400">▶ Play</button>
                        <input type="range" id="replay-slider" min="0" max="{{ count($replay['frames']) - 1 }}" value="{{ count($replay['frames']) - 1 }}" class="flex-1 accent-amber-500">
                    </div>
                    <script type="application/json" id="replay-data">@json($replay)</script>
                </div>
            @endif
            @if ($chart)
                <div class="w-full max-w-sm">
                    <div class="mb-1 text-xs text-stone-500">Score over {{ $game->turn - 1 }} turns</div>
                    {!! $chart !!}
                </div>
            @endif
        </div>
    </div>
@endif

{{-- A dilemma waiting for an answer --}}
@if ($choice && $game->isActive())
    <div class="mb-4 rounded-lg border border-fuchsia-700/70 bg-fuchsia-950/40 px-5 py-4">
        <div class="flex flex-wrap items-start gap-4">
            <span class="text-4xl">{{ $choice['icon'] }}</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold">{{ $choice['title'] }}</div>
                <p class="mt-0.5 text-sm text-stone-300">{{ $choice['text'] }}</p>
                <form method="POST" action="{{ route('games.choose', $game) }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                    <button name="option" value="a" class="rounded bg-fuchsia-600 px-3 py-1.5 font-medium hover:bg-fuchsia-500">{{ $choice['a'] }}</button>
                    <button name="option" value="b" class="rounded border border-stone-600 px-3 py-1.5 hover:bg-stone-800">{{ $choice['b'] }}</button>
                </form>
                <p class="mt-1 text-[11px] text-stone-500">Undecided by the end of next turn, the second answer is taken.</p>
            </div>
        </div>
    </div>
@endif

{{-- Barbarians on the march toward the player's cities --}}
@if ($raidsIncoming->isNotEmpty() && $game->isActive())
    @foreach ($raidsIncoming as $r)
        <div class="mb-3 flex flex-wrap items-center gap-3 rounded-lg border {{ $r['holds'] ? 'border-stone-700 bg-stone-900/70' : 'border-red-700 bg-red-950/50' }} px-4 py-2 text-sm">
            <span class="text-2xl">☠</span>
            <div class="min-w-0 flex-1">
                <b>Barbarians march on <a href="?tile={{ $r['cityModel']->tile_id }}" data-tile="{{ $r['cityModel']->tile_id }}" class="map-tile-link underline">{{ $r['cityModel']->name }}</a></b>: strength {{ $r['strength'] }} against defence {{ $r['defense'] }}. They strike at the end of this turn.
                <span class="{{ $r['holds'] ? 'text-emerald-300' : 'text-red-300' }}">{{ $r['holds'] ? 'The city should hold.' : 'The city will be sacked unless you add '.($r['strength'] - $r['defense']).' defence: train or buy units, buy walls, or clear the camp.' }}</span>
            </div>
            <a href="?tile={{ $r['camp'] }}" data-tile="{{ $r['camp'] }}" class="map-tile-link rounded border border-red-800 px-2 py-1 text-xs text-red-200 hover:bg-red-950">Show camp</a>
        </div>
    @endforeach
@endif

{{-- Turn toasts: what happened last turn, dismissible, newest first --}}
@if ($toasts->isNotEmpty() && $game->isActive())
    <div id="toasts" class="pointer-events-none fixed bottom-4 right-4 z-40 flex w-80 max-w-[90vw] flex-col gap-2">
        @foreach ($toasts->take(6) as $i => $t)
            @php
                $style = ['war' => 'border-red-700 bg-red-950/90 text-red-100', 'bad' => 'border-orange-700 bg-orange-950/90 text-orange-100', 'great' => 'border-amber-600 bg-amber-950/90 text-amber-100', 'attention' => 'border-sky-700 bg-sky-950/90 text-sky-100', 'info' => 'border-stone-700 bg-stone-900/90 text-stone-200'][$t['kind']];
                $icon = ['war' => '⚔', 'bad' => '⚠', 'great' => '✦', 'attention' => '➜', 'info' => '·'][$t['kind']];
            @endphp
            <div class="toast pointer-events-auto flex items-start gap-2 rounded-lg border px-3 py-2 text-sm shadow-lg backdrop-blur {{ $style }} {{ $i >= 2 ? 'max-sm:hidden' : '' }}" style="animation-delay: {{ $i * 80 }}ms">
                <span class="mt-0.5 shrink-0">{{ $icon }}</span>
                <span class="flex-1">{{ $t['text'] }}</span>
                <button type="button" class="shrink-0 opacity-60 hover:opacity-100" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
            </div>
        @endforeach
        @if ($toasts->count() > 2)
            <div class="pointer-events-auto text-right text-xs text-stone-400 sm:hidden">+{{ $toasts->count() - 2 }} more in the Chronicle below</div>
        @endif
        @if ($toasts->count() > 6)
            <div class="pointer-events-auto text-right text-xs text-stone-500 max-sm:hidden">+{{ $toasts->count() - 6 }} more in the Chronicle below</div>
        @endif
    </div>
@endif

{{-- Top bar --}}
<div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-stone-800 bg-stone-900/60 px-4 py-3 text-sm">
    <div class="flex items-center gap-2 font-semibold">
        <span class="flex h-6 w-6 items-center justify-center rounded-full text-sm ring-2" style="background: {{ $player->color }}22; --tw-ring-color: {{ $player->color }}">{{ Rules::crest($player->name) }}</span>{{ $player->name }}
    </div>
    <div>Turn <b>{{ min($game->turn, $game->max_turns) }}</b><span class="text-stone-500">/{{ $game->max_turns }}</span> <span class="hidden text-stone-500 md:inline">· {{ $era }}</span></div>
    @if ($goldenAge)
        <div class="rounded bg-amber-500/20 px-2 py-0.5 text-amber-200" title="+{{ (int) (Rules::GOLDEN_AGE_BONUS * 100) }}% production and gold">✦ Golden Age · {{ $goldenAge }}t</div>
    @else
        <div class="hidden items-center gap-1 text-stone-500 lg:flex" title="Happiness surplus builds toward a Golden Age ({{ $goldenProgress }}%)"><span>✦</span><span class="h-1.5 w-12 rounded bg-stone-800"><span class="block h-1.5 rounded bg-amber-500" style="width: {{ $goldenProgress }}%"></span></span></div>
    @endif
    <div title="Treasury: income {{ $totals['gold'] }} (incl. trade {{ $tradeGold }} from {{ $tradePartners->count() }} peaceful neighbours{{ $player->knows('currency') ? '' : ', needs Currency' }}) − upkeep {{ $upkeep['total'] }} per turn">💰 <b>{{ $player->gold }}</b> <span class="{{ $netGold < 0 ? 'text-red-400' : 'text-stone-500' }}">{{ $netGold >= 0 ? '+' : '' }}{{ $netGold }}</span></div>
    <div title="Science per turn">🔬 <b>+{{ $totals['science'] }}</b></div>
    <div title="Settlers ready">🏕️ <b>{{ $player->settlers }}</b></div>
    <a href="#army" title="Army strength (upkeep {{ $upkeep['army'] }} gold/turn). Click for the army panel.">⚔ <b>{{ $player->army }}</b></a>
    <div title="{{ $government['blurb'] }}" class="hidden xl:block">{{ $government['icon'] }} {{ $government['name'] }}@if ($anarchy) <span class="text-red-300">(anarchy {{ $anarchy }}t)</span>@endif</div>
    <div title="Happiness: {{ $happiness['supply'] }} supplied, {{ $happiness['demand'] }} demanded">{{ $happiness['unhappy'] ? '😠' : '🙂' }} <b class="{{ $happiness['unhappy'] ? 'text-red-400' : '' }}">{{ $happiness['balance'] >= 0 ? '+' : '' }}{{ $happiness['balance'] }}</b></div>
    @if ($explored < 100)<div title="Share of the world your empire has explored">🧭 <b>{{ $explored }}%</b></div>@endif
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
@if ($tips && $game->isActive())
    <div id="advisor" class="mb-4 space-y-2">
        @foreach ($tips as $tip)
            <div class="advisor-tip flex items-start gap-3 rounded border border-violet-800/70 bg-violet-950/40 px-4 py-2 text-sm text-violet-100" data-tip="{{ $tip['id'] }}">
                <span class="mt-0.5">🧙</span>
                <span class="flex-1">{{ $tip['text'] }}</span>
                <button type="button" class="dismiss-tip text-violet-300 hover:text-white" aria-label="Dismiss">✕</button>
            </div>
        @endforeach
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
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded border border-emerald-700 bg-emerald-950/50 px-4 py-2 text-sm text-emerald-100">
        <span class="text-lg">🏕️</span>
        <span class="flex-1">
            <b>Settlers ready.</b> The <span class="rounded bg-emerald-500/30 px-1">green tiles</span> on the map are places you can found a city. Click one, then press <b>Found city here</b>.
            @if ($bestSite) The ★ marks the best site nearby. @endif
            @if ($foundableCount === 0) No legal site is in reach right now: cities need {{ Rules::MIN_CITY_DISTANCE }} tiles between them and unclaimed land. @endif
        </span>
        @if ($bestSite)
            <form method="POST" action="{{ route('games.found', $game) }}">
                @csrf
                <input type="hidden" name="tile" value="{{ $bestSite->id }}">
                <button class="rounded bg-emerald-500 px-3 py-1.5 font-medium text-stone-950 hover:bg-emerald-400">Found at best site ★</button>
            </form>
            <a href="{{ route('games.show', ['game' => $game, 'tile' => $bestSite->id]) }}" class="map-tile-link text-xs text-emerald-300 underline" data-tile="{{ $bestSite->id }}">show me</a>
        @endif
    </div>
@endif

<div id="game-grid" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
    {{-- Map --}}
    <div class="rounded-lg border border-stone-800 bg-stone-950 p-2 shadow-inner">
        <div class="relative">
            <div id="map" data-panel-url="{{ route('games.panel', $game) }}" data-game="{{ $game->id }}" @if ($known) data-known="{{ $known }}" @endif class="overflow-hidden rounded cursor-grab touch-none" style="aspect-ratio: {{ $game->width }} / {{ $game->height }}; max-height: 78vh;">
                {!! $mapSvg !!}
            </div>
            <div class="absolute left-2 top-2 flex flex-col gap-1 text-sm">
                <button type="button" data-zoom="in" class="h-7 w-7 rounded bg-stone-900/90 text-stone-200 shadow hover:bg-stone-800" title="Zoom in (scroll)">+</button>
                <button type="button" data-zoom="out" class="h-7 w-7 rounded bg-stone-900/90 text-stone-200 shadow hover:bg-stone-800" title="Zoom out">−</button>
                <button type="button" data-zoom="reset" class="h-7 w-7 rounded bg-stone-900/90 text-xs text-stone-200 shadow hover:bg-stone-800" title="Fit whole map">⤢</button>
                <button type="button" data-zoom="home" class="h-7 w-7 rounded bg-stone-900/90 text-xs text-stone-200 shadow hover:bg-stone-800" title="Centre on capital" data-tile="{{ $player->cities->firstWhere('is_capital', true)?->tile_id ?? $player->cities->first()?->tile_id }}">⌂</button>
                <button type="button" data-zoom="wide" class="h-7 w-7 rounded bg-stone-900/90 text-xs text-stone-200 shadow hover:bg-stone-800" title="Big map: full width, panels below (B)">⛶</button>
            </div>
            <div id="minimap" class="absolute bottom-2 right-2 w-24 cursor-crosshair opacity-70 transition hover:opacity-100 sm:w-32" title="Click to pan">
                {!! $minimap !!}
            </div>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-1 text-xs text-stone-400">
            @foreach ($rivals as $r)
                <span class="{{ $r['empire']->eliminated ? 'line-through opacity-50' : '' }}"><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $r['empire']->color }}"></span>{{ $r['empire']->name }}</span>
            @endforeach
            <span class="ml-auto hidden sm:inline">drag or scroll to pan · ⌘/Ctrl+scroll or buttons to zoom · WASD pans, arrows move the selection · circle = city (population) · ★ capital · dashed ring = walls · gold ring = wonder</span>
        </div>
    </div>

    {{-- Side panel --}}
    <aside class="space-y-4">
        {{-- Selected tile / city (also served alone by games.panel) --}}
        @include('games._panel')

        {{-- Army --}}
        <section id="army" class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <div class="flex items-baseline justify-between"><h2 class="font-semibold">Army · strength {{ $army['strength'] }}@if ($army['fighting'] > $army['strength']) <span class="text-amber-300" title="Veterancy lifts fighting strength">→ {{ $army['fighting'] }}</span>@endif</h2><span class="text-xs text-stone-500">upkeep {{ $army['upkeep'] }} gold/turn</span></div>
            <p class="mt-1 text-xs text-stone-400">Your units form one field army. It garrisons every city equally (<b class="text-stone-200">{{ $army['garrison'] }}</b> per city, the shield on each city marker) and can strike any enemy city that borders your land.</p>
            @if ($army['units']->isNotEmpty() || $army['untracked'])
                <ul class="mt-2 grid grid-cols-2 gap-1 text-xs">
                    @foreach ($army['units'] as $u)
                        <li class="flex items-center justify-between rounded border border-stone-800 bg-stone-900 px-2 py-1"><span>⚔ {{ $u['name'] }} × {{ $u['n'] }}</span><span class="text-stone-500">{{ $u['n'] * $u['strength'] }}</span></li>
                    @endforeach
                    @if ($army['untracked'])<li class="flex items-center justify-between rounded border border-stone-800 bg-stone-900 px-2 py-1"><span>⚔ Levies</span><span class="text-stone-500">{{ $army['untracked'] }}</span></li>@endif
                </ul>
            @else
                <p class="mt-2 text-xs text-amber-300/90">No army yet. Choose a unit in a city's build menu; it joins the army when finished.</p>
            @endif
            <div class="mt-2 flex items-center gap-2 text-xs" title="Battles, raids and cleared camps give experience. Each rank adds {{ (int) (Rules::VETERAN_BONUS * 100) }}% fighting strength.">
                <span class="text-amber-200">🎖️ {{ $army['rank'] }}</span>
                <span class="flex gap-0.5">@for ($i = 1; $i <= 3; $i++)<span class="inline-block h-2 w-3 rounded-sm {{ $army['rankNum'] >= $i ? 'bg-amber-400' : 'bg-stone-700' }}"></span>@endfor</span>
                <span class="text-stone-500">{{ $army['nextAt'] !== null ? $army['xp'].'/'.$army['nextAt'].' xp to next rank' : 'highest rank' }}</span>
            </div>
            @foreach ($army['upgrades'] as $from => $up)
                @if ($game->isActive())
                    <form method="POST" action="{{ route('games.upgrade', $game) }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="from" value="{{ $from }}">
                        <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                        <button class="w-full rounded border px-2 py-1 text-xs {{ $player->gold >= $up['gold'] ? 'border-amber-800 text-amber-200 hover:bg-amber-950' : 'cursor-not-allowed border-stone-800 text-stone-500' }}" @disabled($player->gold < $up['gold'])>
                            ⬆ Retrain {{ $up['n'] }} {{ Rules::UNITS[$from]['name'] }} as {{ Rules::UNITS[$up['to']]['name'] }} (+{{ $up['n'] * (Rules::UNITS[$up['to']]['strength'] - Rules::UNITS[$from]['strength']) }} strength) for {{ $up['gold'] }} gold
                        </button>
                    </form>
                @endif
            @endforeach
            @if ($army['inProduction']->isNotEmpty())
                <div class="mt-2 text-xs text-stone-500">Training: {{ $army['inProduction']->map(fn ($p) => $p['unit']['name'].' in '.$p['city']->name)->join(', ') }}</div>
            @endif
        </section>

        {{-- Government --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <div class="flex items-baseline justify-between"><h2 class="font-semibold">{{ $government['icon'] }} {{ $government['name'] }}</h2>@if ($anarchy)<span class="text-xs text-red-300">anarchy, {{ $anarchy }}t left</span>@endif</div>
            <p class="mt-1 text-xs text-stone-400">{{ $government['blurb'] }}</p>
            @if ($governments && $game->isActive() && ! $anarchy)
                <form method="POST" action="{{ route('games.government', $game) }}" class="mt-2 flex gap-2" onsubmit="return confirm('Change government? Production and science are halved for {{ Rules::ANARCHY_TURNS }} turns of anarchy.')">
                    @csrf
                    <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                    <select name="government" class="min-w-0 flex-1 rounded border border-stone-700 bg-stone-900 px-2 py-1.5 text-xs focus:border-amber-500 focus:outline-none">
                        @foreach ($governments as $key => $g)
                            <option value="{{ $key }}">{{ $g['icon'] }} {{ $g['name'] }} — {{ $g['blurb'] }}</option>
                        @endforeach
                    </select>
                    <button class="rounded border border-stone-600 px-3 py-1.5 text-xs hover:bg-stone-800">Adopt</button>
                </form>
            @elseif (! $governments)
                <p class="mt-1 text-xs text-stone-500">New governments unlock with Code of Laws, Philosophy, Theology and Economics.</p>
            @endif
        </section>

        {{-- Great people --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <div class="flex items-baseline justify-between"><h2 class="font-semibold">🔭 Great people</h2><span class="text-xs text-stone-500">{{ $greatProgress['have'] }}/{{ $greatProgress['need'] }} points</span></div>
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded bg-stone-800"><div class="h-full bg-violet-500" style="width: {{ min(100, (int) ($greatProgress['have'] / max(1, $greatProgress['need']) * 100)) }}%"></div></div>
            @if ($greatProgress['kinds'])
                <p class="mt-1 text-[11px] text-stone-500">Leaning: @foreach (collect($greatProgress['kinds'])->sortDesc() as $k => $n){{ Rules::GREAT_PEOPLE[$k]['icon'] }} {{ $n }}@if (! $loop->last) · @endif @endforeach</p>
            @else
                <p class="mt-1 text-[11px] text-stone-500">Libraries, workshops, markets and temples earn points each turn; wonders earn more.</p>
            @endif
            @foreach ($greatPeople as $kind => $n)
                @php $gp = Rules::GREAT_PEOPLE[$kind]; @endphp
                <form method="POST" action="{{ route('games.great-person', $game) }}" class="mt-2 rounded border border-violet-700/60 bg-violet-950/30 p-2">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                    <div class="flex items-center gap-2"><span class="text-xl">{{ $gp['icon'] }}</span><div class="min-w-0 flex-1"><div class="font-medium">{{ $gp['name'] }}@if ($n > 1) <span class="text-stone-400">×{{ $n }}</span>@endif</div><div class="text-[11px] text-stone-400">{{ $gp['blurb'] }}</div></div></div>
                    @if ($game->isActive())
                        <div class="mt-2 flex gap-2">
                            @if (in_array($kind, ['engineer', 'artist'], true))
                                <select name="city" class="min-w-0 flex-1 rounded border border-stone-700 bg-stone-900 px-2 py-1 text-xs">
                                    @foreach ($player->cities as $c)
                                        <option value="{{ $c->id }}" @selected($selTile && $c->tile_id === $selTile->id)>{{ $c->name }}@if ($kind === 'engineer') — {{ $c->current_build ? \App\Game\Economy::buildName($c->current_build) : 'idle' }}@endif</option>
                                    @endforeach
                                </select>
                            @endif
                            <button class="rounded bg-violet-700 px-3 py-1 text-xs font-medium hover:bg-violet-600">{{ $gp['verb'] }}</button>
                        </div>
                    @endif
                </form>
            @endforeach
        </section>

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
            <details class="mt-3 text-xs" @if ($researchUnset) open @endif>
                <summary class="cursor-pointer text-stone-400 hover:text-white">Tech tree ({{ count($player->technologies ?? []) }}/{{ count(Rules::TECHS) }} known)</summary>
                <p class="mt-1 text-[10px] text-stone-500"><span class="text-emerald-300">✓ known</span> · <span class="text-sky-300">○ available, click to research</span> · <span class="text-amber-300">gold frame = current</span> · 🔒 locked. Arrows show what each discovery needs. Scroll sideways.</p>
                <div class="mt-2 overflow-x-auto rounded border border-stone-800 bg-stone-950/60 p-2">{!! $techTreeSvg !!}</div>
                @if ($game->isActive())
                    <form method="POST" action="{{ route('games.research', $game) }}" id="research-pick" class="hidden">
                        @csrf
                        <input type="hidden" name="tech" value="">
                        <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                    </form>
                @endif
            </details>
        </section>

        {{-- Empires --}}
        <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
            <h2 class="font-semibold">Empires</h2>
            <details class="mt-2 text-xs" open>
                <summary class="cursor-pointer text-stone-400 hover:text-white">Relations web</summary>
                <div class="mx-auto mt-1 max-w-xs">{!! $relationsSvg !!}</div>
                <p class="text-[10px] text-stone-500"><span class="text-red-400">red dashes = war</span> · <span class="text-emerald-300">green = friendly</span>, thicker the warmer · <span class="text-orange-300">orange dots = wary</span> · grey = neutral. Numbers are attitudes toward you. Hover a line for details.</p>
            </details>
            <ul class="mt-2 divide-y divide-stone-800/80">
                @foreach ($rivals as $r)
                    @php $e = $r['empire']; $me = $e->id === $player->id; @endphp
                    <li class="py-2 {{ $e->eliminated ? 'opacity-50' : '' }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="inline-block h-3 w-3 shrink-0 rounded-sm" style="background: {{ $e->color }}"></span>
                                <span class="truncate font-medium {{ $me ? 'text-amber-200' : '' }}">{{ Rules::crest($e->name) }} {{ $e->name }}</span>
                                @if ($e->eliminated)<span class="text-xs text-stone-500">eliminated</span>
                                @elseif ($r['relation'] && $r['relation']['state'] === 'war')<span class="rounded bg-red-900/60 px-1.5 text-[10px] text-red-200">at war</span>
                                @elseif ($r['personality'])<span class="rounded bg-stone-800 px-1.5 text-[10px] {{ $r['attitude'] < -10 ? 'text-red-300' : ($r['attitude'] > 15 ? 'text-emerald-300' : 'text-stone-400') }}" title="{{ $r['personality']['trait'] }}: {{ $r['personality']['blurb'] }} Attitude {{ $r['attitude'] }}.">{{ $r['personality']['trait'] }} · {{ $r['attitudeLabel'] }}</span>@endif
                            </div>
                            <span class="shrink-0 font-semibold">{{ $e->eliminated ? '✝' : $r['score'] }}</span>
                        </div>
                        @if ($r['trades'])
                            <ul class="trades mt-1 hidden space-y-1 rounded border border-sky-900/60 bg-sky-950/20 p-2 text-xs">
                                @foreach ($r['trades'] as $t)
                                    <li class="flex flex-wrap items-center justify-between gap-1">
                                        <span><b>{{ Rules::TECHS[$t['get']]['name'] }}</b> <span class="text-stone-500">({{ Rules::TECHS[$t['get']]['cost'] }} 🔬)</span></span>
                                        <span class="flex gap-1">
                                            @if ($t['give'])
                                                <form method="POST" action="{{ route('games.trade', [$game, $e]) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="get" value="{{ $t['get'] }}"><input type="hidden" name="give" value="{{ $t['give'] }}">
                                                    <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                                    <button class="rounded border border-sky-800 px-1.5 py-0.5 text-[10px] text-sky-200 hover:bg-sky-900">for {{ Rules::TECHS[$t['give']]['name'] }}</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('games.trade', [$game, $e]) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="get" value="{{ $t['get'] }}">
                                                <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                                <button class="rounded border border-amber-800 px-1.5 py-0.5 text-[10px] text-amber-200 hover:bg-amber-950 disabled:opacity-40" @disabled($player->gold < $t['gold'])>for {{ $t['gold'] }} 💰</button>
                                            </form>
                                        </span>
                                    </li>
                                @endforeach
                                <li class="text-[10px] text-stone-500">One deal per {{ Rules::TECH_TRADE_COOLDOWN }} turns with each rival. Friendlier rivals ask less.</li>
                            </ul>
                        @endif
                        <div class="mt-1 flex items-center justify-between gap-2 text-xs text-stone-400">
                            <span>{{ $r['cities'] }} {{ Str::plural('city', $r['cities']) }} · {{ $r['share'] }}% land · ⚔ {{ $r['army'] }} · 🎭 {{ $r['culture'] }} · 🔬 {{ $r['techs'] }}</span>
                            @if ($r['relation'] && ! $e->eliminated && $game->isActive())
                                <span class="flex shrink-0 items-center gap-1">
                                    @if ($r['relation']['state'] === 'war')
                                        <form method="POST" action="{{ route('games.peace', [$game, $e]) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                            <button class="rounded border px-1.5 py-0.5 text-[10px] {{ $r['peaceLikely'] ? 'border-emerald-700 text-emerald-200 hover:bg-emerald-950' : 'border-stone-700 text-stone-400 hover:bg-stone-800' }}" title="{{ $r['peaceLikely'] ? 'They would accept' : 'They may refuse' }}">☮ peace</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('games.gift', [$game, $e]) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="gold" value="50">
                                            <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                            <button class="rounded border border-stone-700 px-1.5 py-0.5 text-[10px] text-stone-400 hover:border-emerald-700 hover:text-emerald-200 disabled:opacity-40" title="Gift 50 gold (+5 attitude)" @disabled($player->gold < 50)>🎁 50</button>
                                        </form>
                                        @if ($r['trades'])
                                            <button type="button" class="rounded border border-sky-800 px-1.5 py-0.5 text-[10px] text-sky-200 hover:bg-sky-950" onclick="this.closest('li').querySelector('.trades').classList.toggle('hidden')" title="They will trade technologies">🔁 techs</button>
                                        @endif
                                        @if ($r['canTribute'])
                                            <form method="POST" action="{{ route('games.tribute', [$game, $e]) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                                <button class="rounded border border-amber-800 px-1.5 py-0.5 text-[10px] text-amber-200 hover:bg-amber-950" title="Your army dwarfs theirs: demand gold">💰 tribute</button>
                                            </form>
                                        @endif
                                        @if ($r['warProblem'] === null)
                                            <form method="POST" action="{{ route('games.war', [$game, $e]) }}" class="inline" onsubmit="return confirm('Declare war on {{ $e->name }}?')">
                                                @csrf
                                                <input type="hidden" name="tile" value="{{ $selTile?->id }}" class="selected-tile-input">
                                                <button class="rounded border border-stone-700 px-1.5 py-0.5 text-[10px] text-stone-400 hover:border-red-800 hover:text-red-200">⚔ war</button>
                                            </form>
                                        @else
                                            <span class="text-[10px] text-stone-500" title="{{ $r['warProblem'] }}">treaty</span>
                                        @endif
                                    @endif
                                </span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($chart && $game->isActive())
            <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
                <div class="flex items-baseline justify-between"><h2 class="font-semibold">Score over time</h2><span class="text-xs text-stone-500">{{ $difficulty['name'] }}</span></div>
                <div class="mt-2">{!! $chart !!}</div>
            </section>
        @endif

        @if ($game->isActive())
            <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
                <div class="flex items-baseline justify-between"><h2 class="font-semibold">Road to victory</h2><span class="text-xs text-stone-500">{{ $victory['turnsLeft'] }} turns left</span></div>
                <div class="mt-2 grid gap-3 text-xs sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <div class="text-stone-400" title="Half for knowing every technology, half for building the Grand Expedition">⛵ Science (all techs, then the Grand Expedition)</div>
                        @foreach ($victory['science'] as $v)
                            <div class="mt-1 flex items-center gap-2"><span class="inline-block h-2 w-2 rounded-sm" style="background: {{ $v['empire']->color }}"></span><span class="w-16 truncate {{ $v['empire']->id === $player->id ? 'text-amber-200' : '' }}">{{ $v['empire']->name }}</span><span class="h-1.5 flex-1 rounded bg-stone-800"><span class="block h-1.5 rounded" style="width: {{ $v['pct'] }}%; background: {{ $v['empire']->color }}"></span></span><span class="w-20 text-right {{ $v['launch'] !== null ? 'text-red-300' : 'text-stone-400' }}">{{ $v['launch'] !== null ? '⛵ '.$v['launch'].'% built' : $v['pct'].'%' }}</span></div>
                        @endforeach
                    </div>
                    <div>
                        <div class="text-stone-400">🗺️ Domination ({{ $dominationShare }}% of land)</div>
                        @foreach ($victory['domination'] as $v)
                            <div class="mt-1 flex items-center gap-2"><span class="inline-block h-2 w-2 rounded-sm" style="background: {{ $v['empire']->color }}"></span><span class="w-16 truncate {{ $v['empire']->id === $player->id ? 'text-amber-200' : '' }}">{{ $v['empire']->name }}</span><span class="h-1.5 flex-1 rounded bg-stone-800"><span class="block h-1.5 rounded" style="width: {{ $v['pct'] }}%; background: {{ $v['empire']->color }}"></span></span><span class="w-8 text-right text-stone-400">{{ $v['pct'] }}%</span></div>
                        @endforeach
                    </div>
                    <div>
                        <div class="text-stone-400">🎭 Culture ({{ $cultureGoal }})</div>
                        @foreach ($victory['culture'] as $v)
                            <div class="mt-1 flex items-center gap-2"><span class="inline-block h-2 w-2 rounded-sm" style="background: {{ $v['empire']->color }}"></span><span class="w-16 truncate {{ $v['empire']->id === $player->id ? 'text-amber-200' : '' }}">{{ $v['empire']->name }}</span><span class="h-1.5 flex-1 rounded bg-stone-800"><span class="block h-1.5 rounded" style="width: {{ $v['pct'] }}%; background: {{ $v['empire']->color }}"></span></span><span class="w-8 text-right text-stone-400">{{ $v['pct'] }}%</span></div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($wonderRaces->isNotEmpty() && $game->isActive())
            <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
                <h2 class="font-semibold">Wonder races</h2>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach ($wonderRaces as $w)
                        <li class="flex items-center gap-2">
                            <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $w['empire']?->color }}"></span>
                            <span class="flex-1 truncate">{{ $w['empire']?->name }} · {{ $w['wonder'] }}</span>
                            <span class="h-1.5 w-16 rounded bg-stone-800"><span class="block h-1.5 rounded bg-amber-500" style="width: {{ $w['pct'] }}%"></span></span>
                            <span class="w-8 text-right text-stone-400">{{ $w['pct'] }}%</span>
                        </li>
                    @endforeach
                </ul>
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

        {{-- Chronicle --}}
        @if ($chronicle)
            <section class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm">
                <h2 class="font-semibold">Chronicle</h2>
                @foreach ($chronicle as $i => $entry)
                    <details class="mt-2 text-xs" @if ($i === 0) open @endif>
                        <summary class="cursor-pointer text-stone-400 hover:text-white">Turn {{ $entry['turn'] }} <span class="text-stone-600">· {{ count($entry['events']) }} {{ Str::plural('event', count($entry['events'])) }}</span></summary>
                        <ul class="mt-1 space-y-0.5 text-stone-300">
                            @foreach ($entry['events'] as $e)
                                <li>· {{ $e }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </section>
        @endif
    </aside>
</div>
@endsection
