<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-950 text-stone-100 antialiased" data-mood="@yield('mood', 'peace')" data-cue="@yield('cue')">
    <nav class="border-b border-stone-800 bg-stone-900/60">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="https://nikniq.com" class="hidden text-xs text-stone-500 hover:text-stone-300 sm:inline" title="Back to nikniq.com">← nikniq.com</a>
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                    <span class="text-amber-400">⬢</span> {{ config('app.name') }}
                </a>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <button type="button" id="music-toggle" class="rounded px-2 py-1 text-stone-400 hover:bg-stone-800 hover:text-white" title="Music" aria-label="Toggle music">🔇</button>
                <a href="{{ route('guide') }}" class="hidden rounded px-2 py-1 text-stone-400 hover:bg-stone-800 hover:text-white sm:inline">How to play</a>
                <a href="{{ route('hall') }}" class="hidden rounded px-2 py-1 text-stone-400 hover:bg-stone-800 hover:text-white sm:inline">Hall of Fame</a>
                @auth
                    <a href="{{ route('games.index') }}" class="rounded px-2 py-1 text-stone-300 hover:bg-stone-800 hover:text-white">My games</a>
                    @php $me = auth()->user(); @endphp
                    @if ($me->isGuest())
                        <a href="{{ route('register') }}" class="rounded bg-amber-500 px-3 py-1 font-medium text-stone-950 hover:bg-amber-400" title="You are playing as a guest. Register to keep your games on any device.">Save progress</a>
                    @endif
                    <details class="profile-menu relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full py-0.5 pl-0.5 pr-2 text-stone-300 hover:bg-stone-800 hover:text-white [&::-webkit-details-marker]:hidden" aria-label="Profile menu">
                            @if ($me->avatar)
                                <img src="{{ $me->avatar }}" alt="" referrerpolicy="no-referrer" class="h-7 w-7 rounded-full object-cover">
                            @else
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-stone-700 text-xs font-semibold text-stone-100">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                            @endif
                            <span class="hidden max-w-32 truncate sm:inline">{{ $me->name }}</span>
                            <span class="text-xs text-stone-500" aria-hidden="true">▾</span>
                        </summary>
                        <div class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-lg border border-stone-700 bg-stone-900 shadow-xl shadow-black/50">
                            <div class="border-b border-stone-800 px-4 py-3">
                                <p class="truncate font-medium text-stone-100">{{ $me->name }}</p>
                                <p class="truncate text-xs text-stone-500">{{ $me->isGuest() ? 'Guest player' : $me->email }}</p>
                            </div>
                            <a href="{{ route('profile') }}" class="block px-4 py-2 text-stone-300 hover:bg-stone-800 hover:text-white">🏅 Profile and achievements</a>
                            <a href="{{ route('profile') }}#nickname" class="block px-4 py-2 text-stone-300 hover:bg-stone-800 hover:text-white">✏️ Change nickname</a>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-800">
                                @csrf
                                <button class="block w-full px-4 py-2 text-left text-stone-300 hover:bg-stone-800 hover:text-white">↩ Log out</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}" class="rounded px-2 py-1 text-stone-300 hover:bg-stone-800 hover:text-white">Log in</a>
                    <form method="POST" action="{{ route('play') }}">
                        @csrf
                        <button class="rounded bg-amber-500 px-3 py-1 font-medium text-stone-950 hover:bg-amber-400">Play now</button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-emerald-800 bg-emerald-950/60 px-4 py-2 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded border border-red-800 bg-red-950/60 px-4 py-2 text-sm text-red-200">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded border border-red-800 bg-red-950/60 px-4 py-2 text-sm text-red-200">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-7xl px-4 py-6 text-xs text-stone-500">
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-800 pt-4">
            <span>{{ config('app.name') }} · a <a href="https://nikniq.com" class="text-stone-400 hover:text-white">nikniq.com</a> game</span>
            <span><a href="{{ route('guide') }}" class="hover:text-white">How to play</a> · <a href="{{ route('hall') }}" class="hover:text-white">Hall of Fame</a> · music is generated in your browser, toggle with 🔇</span>
        </div>
    </footer>
</body>
</html>
