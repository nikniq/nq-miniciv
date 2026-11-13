<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">🛡️ Admin</h1>
    <nav class="flex gap-1 text-sm">
        @foreach (['admin.dashboard' => 'Overview', 'admin.users' => 'Users', 'admin.games' => 'Games'] as $r => $label)
            <a href="{{ route($r) }}" @class(['rounded px-3 py-1.5', 'bg-amber-500 font-medium text-stone-950' => request()->routeIs($r), 'text-stone-300 hover:bg-stone-800' => ! request()->routeIs($r)])>{{ $label }}</a>
        @endforeach
    </nav>
</div>
@if ($errors->has('admin'))
    <div class="mb-4 rounded border border-red-800 bg-red-950/60 px-4 py-2 text-sm text-red-200">{{ $errors->first('admin') }}</div>
@endif
