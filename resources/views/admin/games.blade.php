@extends('layouts.app')
@section('title', 'Games · Admin · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-5xl">
    @include('admin._nav')

    <div class="mb-4 flex flex-wrap gap-1 text-sm">
        @foreach (['' => 'All', 'active' => 'Active', 'won' => 'Won', 'lost' => 'Lost', 'ended' => 'Ended'] as $v => $label)
            <a href="{{ route('admin.games', array_filter(['status' => $v])) }}" @class(['rounded px-3 py-1', 'bg-stone-700 text-white' => ($status ?? '') === $v, 'text-stone-400 hover:bg-stone-800' => ($status ?? '') !== $v])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-lg border border-stone-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-stone-900 text-xs text-stone-500">
                <tr><th class="px-3 py-2">Game</th><th class="px-3 py-2">Player</th><th class="px-3 py-2">Map</th><th class="px-3 py-2">Turn</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Last played</th><th class="px-3 py-2"></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-800">
                @forelse ($games as $g)
                    <tr>
                        <td class="px-3 py-2">{{ $g->name }}</td>
                        <td class="px-3 py-2 text-stone-400">{{ $g->user?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-stone-500">{{ $g->width }}×{{ $g->height }}</td>
                        <td class="px-3 py-2">{{ $g->turn }}/{{ $g->max_turns }}</td>
                        <td class="px-3 py-2">{{ $g->status }}</td>
                        <td class="px-3 py-2 text-stone-500">{{ $g->updated_at->diffForHumans() }}</td>
                        <td class="px-3 py-2 text-right">
                            <form method="POST" action="{{ route('admin.games.destroy', $g) }}" onsubmit="return confirm(@js('Delete the game '.$g->name.'? This cannot be undone.'))">@csrf @method('DELETE')<button class="rounded px-2 py-1 text-xs text-red-400 hover:bg-red-950">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-stone-500">No games.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $games->links() }}</div>
</div>
@endsection
