@extends('layouts.app')
@section('title', 'Admin · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-5xl">
    @include('admin._nav')

    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($stats as $label => $n)
            <div class="rounded-lg border border-stone-800 bg-stone-900/50 p-3">
                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                <dd class="text-xl font-semibold">{{ number_format($n) }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($outcomes->isNotEmpty())
        <p class="mt-3 text-sm text-stone-400">Finished games:
            @foreach ($outcomes as $status => $n) <span class="ml-2">{{ ucfirst($status) }} <b class="text-stone-200">{{ $n }}</b></span> @endforeach
        </p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-2 font-semibold">Newest players <a href="{{ route('admin.users', ['filter' => 'players']) }}" class="ml-2 text-xs font-normal text-amber-400 hover:underline">all</a></h2>
            <ul class="divide-y divide-stone-800 rounded-lg border border-stone-800 text-sm">
                @forelse ($recentUsers as $u)
                    <li class="flex justify-between gap-3 px-3 py-2"><span class="truncate">{{ $u->name }} <span class="text-stone-500">{{ $u->email }}</span></span><span class="shrink-0 text-stone-500">{{ $u->created_at->diffForHumans() }}</span></li>
                @empty
                    <li class="px-3 py-2 text-stone-500">No players yet.</li>
                @endforelse
            </ul>
        </section>
        <section>
            <h2 class="mb-2 font-semibold">Recently played <a href="{{ route('admin.games') }}" class="ml-2 text-xs font-normal text-amber-400 hover:underline">all</a></h2>
            <ul class="divide-y divide-stone-800 rounded-lg border border-stone-800 text-sm">
                @forelse ($recentGames as $g)
                    <li class="flex justify-between gap-3 px-3 py-2"><span class="truncate">{{ $g->name }} <span class="text-stone-500">{{ $g->user?->name }} · turn {{ $g->turn }} · {{ $g->status }}</span></span><span class="shrink-0 text-stone-500">{{ $g->updated_at->diffForHumans() }}</span></li>
                @empty
                    <li class="px-3 py-2 text-stone-500">No games yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
