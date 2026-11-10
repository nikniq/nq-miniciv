<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-950 text-stone-100 antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center px-4 text-center">
        <p class="text-sm uppercase tracking-widest text-amber-400">Game portal</p>
        <h1 class="mt-3 text-5xl font-semibold">{{ config('app.name') }}</h1>
        <p class="mt-4 max-w-xl text-lg text-stone-400">
            Build, trade and expand your civilisation. The portal is under construction.
        </p>
    </main>
</body>
</html>
