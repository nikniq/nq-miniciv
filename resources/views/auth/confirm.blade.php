@extends('layouts.app')
@section('title', 'Sign in · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-sm text-center">
    <div class="text-5xl">📜</div>
    <h1 class="mt-3 text-2xl font-semibold">Sign in to {{ config('app.name') }}</h1>
    <p class="mt-2 text-sm text-stone-400">Continue as <b class="text-stone-200">{{ $email }}</b>.</p>
    <form method="POST" action="{{ $action }}" class="mt-6">
        @csrf
        <button autofocus class="w-full rounded bg-amber-500 px-5 py-2.5 font-medium text-stone-950 hover:bg-amber-400">Sign in</button>
    </form>
    <p class="mt-4 text-xs text-stone-500">Didn't ask for this? Close the page and nothing happens.</p>
</div>
@endsection
