@extends('layouts.app')
@section('title', 'My games · '.config('app.name'))

@section('content')
<div class="grid gap-8 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <h1 class="text-2xl font-semibold">My games</h1>

        @if ($games->isEmpty())
            <p class="mt-4 text-stone-400">No empires yet. Start one on the right.</p>
        @else
            <ul class="mt-4 divide-y divide-stone-800 rounded-lg border border-stone-800 bg-stone-900/40">
                @foreach ($games as $game)
                    @php $me = $game->playerEmpire(); @endphp
                    <li class="flex items-center justify-between gap-4 px-4 py-3">
                        <div>
                            <a href="{{ route('games.show', $game) }}" class="font-medium hover:text-amber-300">
                                <span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background: {{ $me?->color }}"></span>{{ $game->name }}
                            </a>
                            <div class="text-xs text-stone-500">
                                Turn {{ min($game->turn, $game->max_turns) }} / {{ $game->max_turns }}
                                · score {{ $me?->score ?? 0 }}
                                · {{ $game->empires->count() - 1 }} {{ Str::plural('rival', $game->empires->count() - 1) }}
                                · {{ \App\Game\Rules::DIFFICULTIES[$game->difficulty ?? 'prince']['name'] }}
                                · {{ $game->updated_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            @php
                                $badge = match ($game->status) {
                                    'active' => 'bg-emerald-900/60 text-emerald-200',
                                    'won' => 'bg-amber-900/60 text-amber-200',
                                    default => 'bg-stone-800 text-stone-300',
                                };
                            @endphp
                            <span class="rounded px-2 py-0.5 text-xs {{ $badge }}">{{ ucfirst($game->status) }}@if ($kind = $game->victoryKind()) · {{ $kind[0] }} {{ $kind[1] }}@endif</span>
                            <a href="{{ route('games.show', $game) }}" class="rounded border border-stone-700 px-3 py-1 text-sm hover:bg-stone-800">{{ $game->isActive() ? 'Play' : 'View' }}</a>
                            <form method="POST" action="{{ route('games.destroy', $game) }}" onsubmit="return confirm('Abandon this game? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="rounded px-2 py-1 text-sm text-stone-500 hover:bg-red-950 hover:text-red-300" title="Abandon">✕</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <aside>
        <div class="rounded-lg border border-stone-800 bg-stone-900/50 p-5">
            <h2 class="text-lg font-semibold">New game</h2>
            <form method="POST" action="{{ route('games.store') }}" class="mt-4 space-y-4">
                @csrf
                <label class="block text-sm">
                    <span class="text-stone-300">Your empire</span>
                    <input type="text" name="empire" list="empire-names" value="{{ old('empire', 'Rome') }}" required maxlength="30"
                           class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 focus:border-amber-500 focus:outline-none">
                    <datalist id="empire-names">
                        @foreach ($empireNames as $n)<option value="{{ $n }}">@endforeach
                    </datalist>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-sm">
                        <span class="text-stone-300">Map</span>
                        <select name="size" class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 focus:border-amber-500 focus:outline-none">
                            @foreach ($sizes as $key => $sz)
                                <option value="{{ $key }}" @selected(old('size', 'normal') === $key)>{{ $sz['name'] }} ({{ $sz['width'] }}×{{ $sz['height'] }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-stone-300">Rivals</span>
                        <select name="rivals" class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 focus:border-amber-500 focus:outline-none">
                            @foreach ([1, 2, 3, 4, 5, 6, 7] as $n)
                                <option value="{{ $n }}" @selected(old('rivals', 2) == $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="block text-sm">
                    <span class="text-stone-300">Difficulty</span>
                    <select name="difficulty" class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 focus:border-amber-500 focus:outline-none">
                        @foreach ($difficulties as $key => $d)
                            <option value="{{ $key }}" @selected(old('difficulty', 'prince') === $key)>{{ $d['name'] }} — {{ $d['blurb'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-stone-300">Game length</span>
                    <select name="length" class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 focus:border-amber-500 focus:outline-none">
                        @foreach (\App\Game\Rules::LENGTHS as $key => $l)
                            <option value="{{ $key }}" @selected(old('length', 'standard') === $key)>{{ $l['name'] }} — {{ $l['turns'] }} turns</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-stone-300">National trait</span>
                    <select name="trait" class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 focus:border-amber-500 focus:outline-none">
                        <option value="random" @selected(old('trait', 'random') === 'random')>🎲 Random</option>
                        @foreach (\App\Game\Rules::TRAITS as $key => $t)
                            <option value="{{ $key }}" @selected(old('trait') === $key)>{{ $t['icon'] }} {{ $t['name'] }} — {{ $t['blurb'] }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="text-xs text-stone-500">Rivals are capped by map size: small 2, normal 4, large 6, huge and giant 7. A giant world is {{ $sizes['giant']['width'] * $sizes['giant']['height'] }} tiles; scroll the map with the mouse wheel and zoom with ⌘/Ctrl + wheel or the buttons.</p>
                <button class="w-full rounded bg-amber-500 px-4 py-2 font-medium text-stone-950 hover:bg-amber-400">Found your empire</button>
            </form>
            <p class="mt-4 text-xs text-stone-500">
                Win by domination ({{ (int) (\App\Game\Rules::DOMINATION_SHARE * 100) }}% of the land), culture, science, diplomacy, or the highest score when time runs out.
            </p>
        </div>
    </aside>
</div>
@endsection
