@extends('layouts.app')
@section('title', 'Hall of Fame · '.config('app.name'))

@php use App\Game\Rules; @endphp

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-3xl font-semibold">Hall of Fame</h1>
    <p class="mt-2 text-stone-400">The greatest finished empires. Register to see your name here instead of "Guest".</p>

    @if ($rows->isEmpty())
        <p class="mt-6 text-stone-500">No finished games yet. Be the first.</p>
    @else
        <table class="mt-6 w-full text-sm">
            <thead class="text-left text-xs text-stone-500">
                <tr><th class="py-1">#</th><th>Empire</th><th>Player</th><th>Result</th><th>Difficulty</th><th class="text-right">Score</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-800">
                @foreach ($rows as $i => $r)
                    <tr class="{{ $i < 3 ? 'text-amber-200' : '' }}">
                        <td class="py-2 text-stone-500">{{ $i + 1 }}</td>
                        <td><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $r['empire']?->color }}"></span>{{ $r['game']->name }}</td>
                        <td class="text-stone-400">{{ $r['player'] }}</td>
                        <td>
                            @if ($r['game']->status === 'won') <span class="text-emerald-300">Victory</span>
                            @elseif ($r['game']->status === 'lost') <span class="text-red-300">Defeat</span>
                            @else <span class="text-stone-400">Finished</span> @endif
                            <span class="text-xs text-stone-500">· turn {{ min($r['game']->turn, $r['game']->max_turns) }}</span>
                        </td>
                        <td class="text-stone-400">{{ Rules::DIFFICULTIES[$r['game']->difficulty ?? 'prince']['name'] }}</td>
                        <td class="text-right font-medium">{{ $r['score'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
