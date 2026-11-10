@extends('layouts.app')
@section('title', 'Log in · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="text-2xl font-semibold">Log in</h1>
    <p class="mt-1 text-sm text-stone-400">Return to your empire.</p>

    @if ($google)
        <a href="{{ route('google.redirect') }}" class="mt-6 flex w-full items-center justify-center gap-2 rounded border border-stone-700 bg-white px-4 py-2 font-medium text-stone-900 hover:bg-stone-100">
            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.5l6.7-6.7C35.6 2.5 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.3l7.8 6C12.3 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4 7.1-10 7.1-17.5z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.8-6C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.7l7.8-6z"/><path fill="#34A853" d="M24 48c6.2 0 11.6-2 15.4-5.6l-7.5-5.8c-2.1 1.4-4.8 2.3-7.9 2.3-6.3 0-11.7-4.1-13.6-9.8l-7.8 6C6.5 42.6 14.6 48 24 48z"/></svg>
            Continue with Google
        </a>
        <div class="my-5 flex items-center gap-3 text-xs text-stone-500"><span class="h-px flex-1 bg-stone-800"></span>or<span class="h-px flex-1 bg-stone-800"></span></div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-4 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-stone-300">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </label>
        <label class="block text-sm">
            <span class="text-stone-300">Password</span>
            <input type="password" name="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </label>
        <label class="flex items-center gap-2 text-sm text-stone-400">
            <input type="checkbox" name="remember" value="1" class="rounded border-stone-700 bg-stone-900"> Stay logged in
        </label>
        <button class="w-full rounded bg-amber-500 px-4 py-2 font-medium text-stone-950 hover:bg-amber-400">Log in</button>
    </form>

    <p class="mt-6 text-sm text-stone-400">New here? <a href="{{ route('register') }}" class="text-amber-400 hover:underline">Create an account</a>.</p>
</div>
@endsection
