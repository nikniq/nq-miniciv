@extends('layouts.app')
@section('title', 'Sign up · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-sm">
    @if ($guest)
        <h1 class="text-2xl font-semibold">Save your progress</h1>
        <p class="mt-1 text-sm text-stone-400">You are playing as a guest. Enter your email (or sign in with Google or nikniq) and every game you have started stays with you.</p>
    @else
        <h1 class="text-2xl font-semibold">Create your account</h1>
        <p class="mt-1 text-sm text-stone-400">Free to play. Just an email: we send you a link, no password needed.</p>
    @endif

    @include('auth._social')

    <form method="POST" action="{{ route('magic.send') }}" class="mt-4 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-stone-300">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="mt-1 w-full rounded border border-stone-700 bg-stone-900 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </label>
        @error('email')<p class="text-sm text-red-400">{{ $message }}</p>@enderror
        <button class="w-full rounded bg-amber-500 px-4 py-2 font-medium text-stone-950 hover:bg-amber-400">{{ $guest ? 'Email me a link to save my games' : 'Email me a sign-up link' }}</button>
    </form>

    <p class="mt-6 text-sm text-stone-400">Already playing? <a href="{{ route('login') }}" class="text-amber-400 hover:underline">Log in</a>.</p>
</div>
@endsection
