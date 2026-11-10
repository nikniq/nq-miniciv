@php
    use App\Game\Economy;
    use App\Game\Rules;
@endphp
<section id="tile-panel" class="rounded-lg border border-stone-800 bg-stone-900/60 p-4 text-sm" data-tile="{{ $selected['tile']->id ?? '' }}">
@if (! $selected)
    <h2 class="font-semibold">Select a tile</h2>
    <p class="mt-1 text-stone-400">Click any tile on the map to inspect it, manage a city, found a new one, or attack a neighbour.</p>
@else
    @php $tile = $selected['tile']; $city = $selected['city']; @endphp
    <div class="flex items-start justify-between gap-2">
        <div>
            <h2 class="font-semibold">
                @if ($city)
                    {{ $city->name }} <span class="text-stone-400">· size {{ $city->population }}</span> @if ($city->is_capital) <span title="Capital">★</span> @endif
                @else
                    @if ($selected['feature']) {{ $selected['feature']['glyph'] }} {{ $selected['feature']['name'] }} <span class="text-stone-400">· natural wonder on {{ strtolower($selected['terrain']['name']) }}</span> @else {{ $selected['terrain']['name'] }} @endif @if ($selected['river']) · 🏞️ River @endif @if ($selected['resource']) · {{ $selected['resource']['icon'] }} {{ $selected['resource']['name'] }}@endif
                @endif
            </h2>
            <div class="text-xs text-stone-500">
                ({{ $tile->x }}, {{ $tile->y }})
                @if ($city) · {{ $selected['terrain']['name'] }}@if ($selected['resource']), {{ $selected['resource']['name'] }}@endif @endif
                @if ($selected['owner']) · <span style="color: {{ $selected['owner']->color }}">{{ $selected['owner']->name }}</span>@if ($selected['ownerCity'] && ! $city) ({{ $selected['ownerCity']->name }})@endif
                @else · unclaimed @endif
                @if ($selected['worked']) · worked @endif
            </div>
        </div>
        <div class="shrink-0 text-right text-xs text-stone-400" title="Tile yield for your empire">
            🌾{{ $selected['yield']['food'] }} ⚒{{ $selected['yield']['prod'] }} 💰{{ $selected['yield']['gold'] }}@if ($selected['yield']['science']) 🔬{{ $selected['yield']['science'] }}@endif @if ($selected['yield']['culture'] ?? 0) 🎭{{ $selected['yield']['culture'] }}@endif
        </div>
    </div>

    @if ($city && $city->empire_id === $player->id)
        @php $y = $selected['cityYields']; @endphp
        <div class="mt-3">{!! $selected['skyline'] !!}</div>
        @if ($city->buildings)
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($city->buildings as $b)
                    @php $bd = Rules::BUILDINGS[$b]; @endphp
                    <span class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[11px] {{ $bd['wonder'] ? 'border-amber-700/70 bg-amber-950/40 text-amber-200' : 'border-stone-700 bg-stone-800/60 text-stone-200' }}" title="{{ $bd['name'] }}: {{ $bd['blurb'] }}">{{ $bd['icon'] ?? '🏛️' }} {{ $bd['name'] }}</span>
                @endforeach
            </div>
        @endif
        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <dt class="text-stone-400">Food</dt><dd>{{ $y['food'] }} <span class="text-stone-500">({{ $y['surplus'] >= 0 ? '+' : '' }}{{ $y['surplus'] }} surplus)</span></dd>
            <dt class="text-stone-400">Production</dt><dd>{{ $y['prod'] }}@if ($happiness['unhappy']) <span class="text-red-400">(unrest)</span>@endif</dd>
            <dt class="text-stone-400">Gold / Science</dt><dd>{{ $y['gold'] }} / {{ $y['science'] }}</dd>
            <dt class="text-stone-400">Culture</dt><dd>{{ $city->culture }} <span class="text-stone-500">(+{{ $y['culture'] }})</span></dd>
            <dt class="text-stone-400">Defence</dt><dd>{{ Rules::cityDefense($city, $player->garrison(), \App\Game\Economy::empireEffects($player)['defense'] ?? 0) }} <span class="text-stone-500" title="Your army spread over your cities">(garrison {{ $player->garrison() }})</span></dd>
            <dt class="text-stone-400">Size cap</dt><dd>{{ $selected['sizeCap'] }}@if (! $city->has('aqueduct')) <span class="text-stone-500">(Aqueduct → {{ Rules::SIZE_CAP_AQUEDUCT }})</span>@endif</dd>
        </dl>

        <div class="mt-3">
            <div class="flex justify-between text-xs text-stone-400"><span>Growth</span><span>{{ $city->food_stored }}/{{ $city->foodToGrow() }}@if ($selected['turnsToGrow']) · {{ $selected['turnsToGrow'] }}t @elseif ($y['surplus'] < 0) · <span class="text-red-400">starving</span> @elseif ($happiness['unhappy']) · <span class="text-red-400">halted by unrest</span> @endif</span></div>
            <div class="mt-1 h-1.5 rounded bg-stone-800"><div class="h-1.5 rounded bg-emerald-500" style="width: {{ min(100, 100 * $city->food_stored / $city->foodToGrow()) }}%"></div></div>
        </div>

        <div class="mt-3">
            <div class="flex justify-between text-xs text-stone-400">
                <span>Building: <b class="text-stone-200">{{ Economy::buildName($city->current_build) }}</b></span>
                <span>
                    @if ($selected['buildCost'])
                        {{ $city->production_stored }}/{{ $selected['buildCost'] }}@if ($selected['turnsToBuild']) · {{ $selected['turnsToBuild'] }}t @endif
                    @elseif ($city->current_build === 'wealth') +{{ $y['prod'] }} gold/turn @endif
                </span>
            </div>
            @if ($selected['buildCost'])
                <div class="mt-1 h-1.5 rounded bg-stone-800"><div class="h-1.5 rounded bg-amber-500" style="width: {{ min(100, 100 * $city->production_stored / $selected['buildCost']) }}%"></div></div>
            @endif
            @if ($city->current_build === 'settlers' && $city->population < 2)
                <div class="mt-1 text-xs text-amber-300">Settlers wait until the city reaches size 2.</div>
            @endif
        </div>

        @if ($game->isActive())
            <form method="POST" action="{{ route('games.focus', [$game, $city]) }}" class="mt-3 flex items-center gap-2 text-xs">
                @csrf
                <span class="text-stone-400">Focus</span>
                <select name="focus" onchange="this.form.requestSubmit()" class="min-w-0 flex-1 rounded border border-stone-700 bg-stone-900 px-2 py-1 focus:border-amber-500 focus:outline-none">
                    @foreach (Rules::FOCUS as $key => $f)
                        <option value="{{ $key }}" @selected($key === ($city->focus ?? 'balanced'))>{{ $f['name'] }}</option>
                    @endforeach
                </select>
                <noscript><button class="rounded border border-stone-600 px-2 py-1">Set</button></noscript>
            </form>
            @if ($y['pillaged'] ?? 0)
                <div class="mt-2 rounded border border-red-900 bg-red-950/40 px-2 py-1 text-xs text-red-200">☠ Barbarians nearby pillage −{{ 2 * $y['pillaged'] }} food. Clear the camp.</div>
            @endif
            <form method="POST" action="{{ route('games.build', [$game, $city]) }}" class="mt-3 flex gap-2">
                @csrf
                <select name="build" class="min-w-0 flex-1 rounded border border-stone-700 bg-stone-900 px-2 py-1.5 text-xs focus:border-amber-500 focus:outline-none">
                    @foreach (['building' => 'Buildings', 'wonder' => 'Wonders', 'unit' => 'Units', 'project' => 'Projects'] as $kind => $label)
                        @php $opts = array_filter($selected['buildOptions'], fn ($o) => $o['kind'] === $kind); @endphp
                        @if ($opts)
                            <optgroup label="{{ $label }}">
                                @foreach ($opts as $key => $o)
                                    <option value="{{ $key }}" @selected($key === $city->current_build) title="{{ $o['blurb'] }}">{{ Rules::BUILDINGS[$key]['icon'] ?? ($kind === 'unit' ? '⚔' : ($key === 'settlers' ? '🏕️' : '💰')) }} {{ $o['name'] }}@if ($o['cost']) ({{ $o['cost'] }})@endif — {{ $o['blurb'] }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
                <button class="rounded border border-stone-600 px-3 py-1.5 text-xs hover:bg-stone-800" title="Build this now">Set</button>
                <button name="queue" value="1" class="rounded border border-stone-700 px-2 py-1.5 text-xs text-stone-300 hover:bg-stone-800" title="Add to the queue">+Q</button>
            </form>
            @if ($city->queue)
                <ol class="mt-2 space-y-0.5 text-xs text-stone-400">
                    @foreach ($city->queue as $i => $q)
                        <li class="flex items-center justify-between gap-2">
                            <span>then {{ Economy::buildName($q) }} <span class="text-stone-600">({{ Economy::buildCost($q) ?? '—' }})</span></span>
                            <form method="POST" action="{{ route('games.dequeue', [$game, $city]) }}">
                                @csrf
                                <input type="hidden" name="position" value="{{ $i }}">
                                <button class="rounded px-1 text-stone-500 hover:bg-stone-800 hover:text-red-300" title="Remove">✕</button>
                            </form>
                        </li>
                    @endforeach
                </ol>
            @endif
            @if ($city->current_build !== 'settlers' && isset($selected['buildOptions']['settlers']))
                <form method="POST" action="{{ route('games.build', [$game, $city]) }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="build" value="settlers">
                    <button class="w-full rounded border border-emerald-800 px-3 py-1.5 text-xs text-emerald-200 hover:bg-emerald-950" title="Settlers found a new city. Costs {{ Rules::SETTLER_COST }} production and 1 population when complete (city must be size 2+).">🏕️ Train settlers to found a new city ({{ Rules::SETTLER_COST }} ⚒{{ $city->population < 2 ? ', needs size 2' : '' }})</button>
                </form>
            @endif
            @if ($selected['buyPrice'])
                <form method="POST" action="{{ route('games.buy', [$game, $city]) }}" class="mt-2">
                    @csrf
                    <button class="w-full rounded border px-3 py-1.5 text-xs {{ $player->gold >= $selected['buyPrice'] ? 'border-amber-700 text-amber-200 hover:bg-amber-950' : 'cursor-not-allowed border-stone-800 text-stone-500' }}" @disabled($player->gold < $selected['buyPrice'])>
                        Buy now for {{ $selected['buyPrice'] }} gold
                    </button>
                </form>
            @endif
        @endif

    @elseif ($city)
        <div class="mt-3">{!! $selected['skyline'] !!}</div>
        <p class="mt-2 text-stone-400">A city of <span style="color: {{ $selected['owner']->color }}">{{ $selected['owner']->name }}</span>.</p>
        @if ($city->buildings)
            <div class="mt-1 flex flex-wrap gap-1">
                @foreach ($city->buildings as $b)
                    @php $bd = Rules::BUILDINGS[$b]; @endphp
                    <span class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[11px] {{ $bd['wonder'] ? 'border-amber-700/70 bg-amber-950/40 text-amber-200' : 'border-stone-700 bg-stone-800/60 text-stone-300' }}" title="{{ $bd['name'] }}: {{ $bd['blurb'] }}">{{ $bd['icon'] ?? '🏛️' }} {{ $bd['name'] }}</span>
                @endforeach
            </div>
        @endif
        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <dt class="text-stone-400">Its defence</dt><dd>{{ $selected['defense'] }}</dd>
            <dt class="text-stone-400">Your army</dt><dd>{{ $player->army }}</dd>
        </dl>
        @if ($game->isActive())
            <div class="mt-3">
                @if (! $selected['atWar'])
                    @if ($selected['warProblem'] === null)
                        <form method="POST" action="{{ route('games.war', [$game, $selected['owner']]) }}" onsubmit="return confirm('Declare war on {{ $selected['owner']->name }}? War costs {{ Rules::WAR_WEARINESS }} happiness per turn and they may strike back.')">
                            @csrf
                            <input type="hidden" name="tile" value="{{ $tile->id }}">
                            <button class="w-full rounded border border-red-800 px-3 py-1.5 text-sm text-red-200 hover:bg-red-950">Declare war on {{ $selected['owner']->name }}</button>
                        </form>
                        <p class="mt-1 text-xs text-stone-500">At peace. You must declare war before attacking.</p>
                    @else
                        <p class="text-xs text-stone-500">{{ $selected['warProblem'] }}</p>
                    @endif
                @elseif ($selected['attackProblem'] === null)
                    @php
                        $oddsText = ['win' => 'Likely to fall.', 'close' => 'Too close to call: an attack that fails costs half your army.', 'lose' => 'You would be repelled and lose half your army.'][$selected['attackOdds']];
                        $oddsClass = ['win' => 'bg-red-600 hover:bg-red-500', 'close' => 'bg-amber-700 hover:bg-amber-600', 'lose' => 'bg-stone-700 hover:bg-stone-600'][$selected['attackOdds']];
                    @endphp
                    <form method="POST" action="{{ route('games.attack', [$game, $city]) }}" onsubmit="return confirm('Attack {{ $city->name }}? {{ $oddsText }}')">
                        @csrf
                        <button class="w-full rounded px-3 py-1.5 text-sm font-medium text-white {{ $oddsClass }}">⚔ Attack {{ $city->name }}</button>
                    </form>
                    <p class="mt-1 text-xs text-stone-500">{{ $oddsText }} Capturing takes the city and its land.</p>
                @else
                    <p class="text-xs text-stone-500">{{ $selected['attackProblem'] }}</p>
                @endif
                @if ($selected['atWar'] && $selected['raidProblem'] === null)
                    <form method="POST" action="{{ route('games.raid', [$game, $city]) }}" class="mt-2">
                        @csrf
                        <button class="w-full rounded border border-orange-700 px-3 py-1.5 text-xs text-orange-200 hover:bg-orange-950">🔥 Raid {{ $city->name }} — loot ~{{ $selected['raidGold'] }} gold, burn its food (costs 1 army)</button>
                    </form>
                @endif
            </div>
        @endif
    @elseif ($tile->camp)
        <p class="mt-2 text-stone-300">☠ A barbarian camp. It pillages 2 food from any city within 2 tiles and blocks borders from growing over it.</p>
        @if ($game->isActive())
            <div class="mt-3">
                @if ($selected['campProblem'] === null)
                    <form method="POST" action="{{ route('games.clear-camp', $game) }}">
                        @csrf
                        <input type="hidden" name="tile" value="{{ $tile->id }}">
                        <button class="w-full rounded bg-red-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-600">Clear the camp (costs {{ Rules::CAMP_CLEAR_LOSS }} army, loots gold)</button>
                    </form>
                @else
                    <p class="text-xs text-stone-500">{{ $selected['campProblem'] }}</p>
                @endif
            </div>
        @endif
    @else
        @if ($game->isActive())
            <div class="mt-3">
                @if ($selected['foundingProblem'] === null)
                    <form method="POST" action="{{ route('games.found', $game) }}">
                        @csrf
                        <input type="hidden" name="tile" value="{{ $tile->id }}">
                        <button class="w-full rounded bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-500">🏕️ Found city here</button>
                    </form>
                    <p class="mt-1 text-xs text-stone-500">Uses 1 band of settlers ({{ $player->settlers }} ready). The new city claims the land around it.</p>
                @elseif ($player->settlers > 0)
                    <p class="text-xs text-amber-300/90">Cannot found here: {{ $selected['foundingProblem'] }} Green tiles on the map are valid.</p>
                @else
                    <p class="text-xs text-stone-500">To found a city here you need settlers: open one of your cities (size 2+) and choose <b>Train settlers</b>.</p>
                @endif
            </div>
        @endif
    @endif
@endif
</section>
