@extends('layouts.app')

@section('title', 'Admin · Servers')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Server management</h1>
        <p class="lead">Track fleet status, environment, and last check-ins.</p>
    </div>
    <div class="admin-nav">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        @if(config('license.enabled'))
            <a class="{{ request()->routeIs('admin.licenses.*') ? 'active' : '' }}" href="{{ route('admin.licenses.index') }}">Licenses</a>
        @endif
        @if(config('products.enabled'))
            <a class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
        @endif
        <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>
        <a class="{{ request()->routeIs('admin.logs.index') ? 'active' : '' }}" href="{{ route('admin.logs.index') }}">App Log</a>
        @if(config('logs.enabled') && Route::has('admin.event-logs.index'))
            <a class="{{ request()->routeIs('admin.event-logs.index') ? 'active' : '' }}" href="{{ route('admin.event-logs.index') }}">Event Logs</a>
        @endif
           @if (config('admin.external_logs_enabled'))
               <a class="{{ request()->routeIs('admin.external-logs.index') ? 'active' : '' }}" href="{{ route('admin.external-logs.index') }}">External Logs</a>
           @endif
        <a class="active" href="{{ route('admin.servers.index') }}">Servers</a>
        @if(config('license.enabled') && config('license.public_validation') && Route::has('admin.tools.license-validation'))
            <a class="{{ request()->routeIs('admin.tools.license-validation') ? 'active' : '' }}" href="{{ route('admin.tools.license-validation') }}">License Validation</a>
        @endif
        <a class="{{ request()->routeIs('admin.servers.create') ? 'active' : '' }}" href="{{ route('admin.servers.create') }}">+ Add server</a>
    </div>
</header>

@if (session('status'))
    <div class="banner success">{{ session('status') }}</div>
@endif

<div class="card">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:separate;border-spacing:0 0.5rem;">
            <thead>
                <tr style="text-align:left;color:var(--muted);font-size:0.85rem;text-transform:uppercase;letter-spacing:0.1em;">
                    <th style="padding:0 0.75rem;">Name</th>
                    <th style="padding:0 0.75rem;">Hostname</th>
                    <th style="padding:0 0.75rem;">Status</th>
                    <th style="padding:0 0.75rem;">Environment</th>
                    <th style="padding:0 0.75rem;">Last seen</th>
                    <th style="padding:0 0.75rem;">Notes</th>
                    <th style="padding:0 0.75rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($servers as $server)
                    <tr style="background:var(--bg);">
                        <td style="padding:0.9rem 0.75rem;font-weight:600;">{{ $server->name }}</td>
                        <td style="padding:0.9rem 0.75rem;font-family:monospace;">{{ $server->hostname }}</td>
                        <td style="padding:0.9rem 0.75rem;">
                            <span style="display:inline-flex;align-items:center;gap:0.35rem;background:rgba(15,23,42,0.08);border-radius:999px;padding:0.15rem 0.8rem;font-size:0.9rem;">
                                <span style="width:0.6rem;height:0.6rem;border-radius:999px;background: {{ $server->status === 'online' ? '#10b981' : ($server->status === 'maintenance' ? '#f59e0b' : '#ef4444') }};"></span>
                                {{ ucfirst($server->status) }}
                            </span>
                        </td>
                        <td style="padding:0.9rem 0.75rem;">{{ $server->environment ?? '—' }}</td>
                        <td style="padding:0.9rem 0.75rem;">{{ $server->last_seen_at ? $server->last_seen_at->format('M j, Y H:i') : '—' }}</td>
                        <td style="padding:0.9rem 0.75rem;max-width:240px;white-space:pre-wrap;">{{ $server->notes ?? '—' }}</td>
                        <td style="padding:0.9rem 0.75rem; display:flex; gap:0.5rem;">
                            <a class="link" href="{{ route('admin.servers.edit', $server) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.servers.destroy', $server) }}" onsubmit="return confirm('Delete this server?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:var(--error);cursor:pointer;padding:0;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1rem 0.75rem;text-align:center;color:var(--muted);">
                            No servers tracked yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem;">
        {{ $servers->links() }}
    </div>
</div>
@endsection
