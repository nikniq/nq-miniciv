@extends('layouts.app')
@section('title', 'Check your inbox · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-sm text-center">
    <div class="text-5xl">📜</div>
    <h1 class="mt-3 text-2xl font-semibold">Check your inbox</h1>
    <p class="mt-2 text-sm text-stone-400">We sent a {{ $isNew ? 'confirmation' : 'sign-in' }} link to <b class="text-stone-200">{{ $email }}</b>. Open it on any device and you are in. No password to remember.</p>
    <p class="mt-4 text-xs text-stone-500">Nothing there after a minute? Check spam, or <a href="{{ url()->previous() }}" class="text-amber-400 hover:underline">try again</a>.</p>
</div>
@endsection
