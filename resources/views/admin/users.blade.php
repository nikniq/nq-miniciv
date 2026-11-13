@extends('layouts.app')
@section('title', 'Users · Admin · '.config('app.name'))

@section('content')
<div class="mx-auto max-w-5xl">
    @include('admin._nav')

    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <input name="q" value="{{ $q }}" placeholder="Search nickname or email" class="min-w-0 flex-1 rounded border border-stone-700 bg-stone-950 px-3 py-2">
        <select name="filter" class="rounded border border-stone-700 bg-stone-950 px-3 py-2">
            @foreach (['' => 'Everyone', 'players' => 'Players', 'guests' => 'Guests', 'admins' => 'Admins'] as $v => $label)
                <option value="{{ $v }}" @selected($filter === $v || ($v === '' && ! $filter))>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded bg-stone-700 px-4 py-2 hover:bg-stone-600">Search</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-stone-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-stone-900 text-xs text-stone-500">
                <tr><th class="px-3 py-2">Nickname</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Games</th><th class="px-3 py-2">Joined</th><th class="px-3 py-2 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-800">
                @forelse ($users as $u)
                    <tr>
                        <td class="px-3 py-2">
                            {{ $u->name }}
                            @if ($u->is_admin) <span class="ml-1 rounded bg-amber-900/60 px-1.5 text-xs text-amber-200">admin</span> @endif
                            @if ($u->is_guest) <span class="ml-1 rounded bg-stone-800 px-1.5 text-xs text-stone-400">guest</span> @endif
                            @if ($u->google_id) <span class="ml-1 text-xs text-stone-500" title="Signs in with Google">G</span> @endif
                        </td>
                        <td class="px-3 py-2 text-stone-400">{{ $u->is_guest ? '' : $u->email }}</td>
                        <td class="px-3 py-2">{{ $u->games_count }}</td>
                        <td class="px-3 py-2 text-stone-500" title="{{ $u->created_at }}">{{ $u->created_at->diffForHumans() }}</td>
                        <td class="px-3 py-2">
                            <div class="flex justify-end gap-1">
                                @unless ($u->is(auth()->user()) || $u->is_guest)
                                    <form method="POST" action="{{ route('admin.users.admin', $u) }}" onsubmit="return confirm(@js(($u->is_admin ? 'Remove admin rights from ' : 'Make administrator: ').$u->name.'?'))">@csrf<button class="rounded px-2 py-1 text-xs text-stone-300 hover:bg-stone-800">{{ $u->is_admin ? 'Revoke admin' : 'Make admin' }}</button></form>
                                @endunless
                                <form method="POST" action="{{ route('admin.users.reset-name', $u) }}" onsubmit="return confirm(@js('Reset the nickname of '.$u->name.'?'))">@csrf<button class="rounded px-2 py-1 text-xs text-stone-300 hover:bg-stone-800">Reset name</button></form>
                                @unless ($u->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm(@js('Delete '.$u->name.' and all '.$u->games_count.' of their games? This cannot be undone.'))">@csrf @method('DELETE')<button class="rounded px-2 py-1 text-xs text-red-400 hover:bg-red-950">Delete</button></form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-stone-500">No users match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection
