@extends('layouts.app')
@section('title', 'Log in · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="text-2xl font-semibold">Log in</h1>
    <p class="mt-1 text-sm text-stone-400">Return to your empire.</p>

    @include('auth._social')

    <form method="POST" action="{{ route('magic.send') }}" class="mt-4 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-stone-300">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </label>
        @error('email')<p class="text-sm text-red-400">{{ $message }}</p>@enderror
        <button class="w-full rounded bg-amber-500 px-4 py-2 font-medium text-stone-950 hover:bg-amber-400">Email me a sign-in link</button>
        <p class="text-xs text-stone-500">New here? The same link creates your account.</p>
    </form>

    <details class="mt-6 text-sm" @if (old('password') !== null || $errors->has('password')) open @endif>
        <summary class="cursor-pointer text-stone-400 hover:text-stone-200">Have a password? Log in with it</summary>
        <form method="POST" action="{{ route('login') }}" class="mt-3 space-y-3">
            @csrf
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="Email"
                   class="w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
            <input type="password" name="password" required autocomplete="current-password" placeholder="Password"
                   class="w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
            <label class="flex items-center gap-2 text-sm text-stone-400">
                <input type="checkbox" name="remember" value="1" class="rounded border-stone-700 bg-stone-900"> Stay logged in
            </label>
            <button class="w-full rounded border border-stone-600 px-4 py-2 font-medium hover:bg-stone-800">Log in</button>
        </form>
    </details>

    <p class="mt-6 text-sm text-stone-400">New here? <a href="{{ route('register') }}" class="text-amber-400 hover:underline">Create an account</a>, or <a href="{{ route('home') }}" class="text-amber-400 hover:underline">play as a guest</a> first.</p>
</div>
@endsection
