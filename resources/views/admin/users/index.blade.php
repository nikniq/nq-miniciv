@extends('layouts.app')

@section('title', 'Admin · Users')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>User management</h1>
        <p class="lead">Invite teammates, promote admins, and retire unused accounts.</p>
    </div>
    <div class="admin-nav">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        @if(config('license.enabled'))
            <a class="{{ request()->routeIs('admin.licenses.*') ? 'active' : '' }}" href="{{ route('admin.licenses.index') }}">Licenses</a>
        @endif
        @if(config('products.enabled'))
            <a class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
        @endif
        <a class="active" href="{{ route('admin.users.index') }}">Users</a>
        <a class="{{ request()->routeIs('admin.event-logs.index') ? 'active' : '' }}" href="{{ route('admin.event-logs.index') }}">Logs</a>
        @if (config('admin.servers_enabled'))
            <a class="{{ request()->routeIs('admin.servers.*') ? 'active' : '' }}" href="{{ route('admin.servers.index') }}">Servers</a>
        @endif
        @if(config('license.enabled') && config('license.public_validation') && Route::has('admin.tools.license-validation'))
            <a class="{{ request()->routeIs('admin.tools.license-validation') ? 'active' : '' }}" href="{{ route('admin.tools.license-validation') }}">License Validation</a>
        @endif
        <a href="{{ route('admin.users.create') }}" class="{{ request()->routeIs('admin.users.create') ? 'active' : '' }}">+ New user</a>
    </div>
</header>

@if (session('status'))
    <div class="banner success">
        {{ session('status') }}
    </div>
@endif

<div class="card">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:separate;border-spacing:0 0.5rem;">
            <thead>
                <tr style="text-align:left;color:var(--muted);font-size:0.85rem;text-transform:uppercase;letter-spacing:0.1em;">
                    <th style="padding:0 0.75rem;">Name</th>
                    <th style="padding:0 0.75rem;">Email</th>
                    <th style="padding:0 0.75rem;">Admin Email</th>
                    <th style="padding:0 0.75rem;">Role</th>
                    <th style="padding:0 0.75rem;">Created</th>
                    <th style="padding:0 0.75rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr style="background:var(--bg);">
                        <td style="padding:0.9rem 0.75rem;font-weight:600;">{{ $user->name }}</td>
                        <td style="padding:0.9rem 0.75rem;">{{ $user->email }}</td>
                        <td style="padding:0.9rem 0.75rem;">{{ $user->admin_email ?? '—' }}</td>
                        <td style="padding:0.9rem 0.75rem;">{{ $user->is_admin ? 'Admin' : 'Member' }}</td>
                        <td style="padding:0.9rem 0.75rem;">{{ $user->created_at->format('M j, Y') }}</td>
                        <td style="padding:0.9rem 0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
                            <a class="link" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:var(--error);cursor:pointer;padding:0;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:1rem 0.75rem;text-align:center;color:var(--muted);">
                            No users have been created yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem;">
        {{ $users->links() }}
    </div>
</div>
@endsection
