@extends('layouts.app')

@section('title', 'Admin · Licenses')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>License management</h1>
        <p class="lead">Review, edit, and retire license allocations across the organization.</p>
    </div>
    <div class="admin-nav">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        <a class="active" href="{{ route('admin.licenses.index') }}">Licenses</a>
        @if(config('products.enabled'))
            <a class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
        @endif
        <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>
        @if(config('logs.enabled') && Route::has('admin.event-logs.index'))
            <a class="{{ request()->routeIs('admin.event-logs.index') ? 'active' : '' }}" href="{{ route('admin.event-logs.index') }}">Logs</a>
        @endif
        @if (config('admin.servers_enabled'))
            <a class="{{ request()->routeIs('admin.servers.*') ? 'active' : '' }}" href="{{ route('admin.servers.index') }}">Servers</a>
        @endif
        @if(config('license.enabled') && config('license.public_validation') && Route::has('admin.tools.license-validation'))
            <a class="{{ request()->routeIs('admin.tools.license-validation') ? 'active' : '' }}" href="{{ route('admin.tools.license-validation') }}">License Validation</a>
        @endif
        <a href="{{ route('admin.licenses.create') }}" class="{{ request()->routeIs('admin.licenses.create') ? 'active' : '' }}">+ New license</a>
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
                    <th style="padding:0 0.75rem;">Product</th>
                    <th style="padding:0 0.75rem;">Assigned</th>
                    <th style="padding:0 0.75rem;">Identifier</th>
                    <th style="padding:0 0.75rem;">Domains</th>
                    <th style="padding:0 0.75rem;">Expires</th>
                    <th style="padding:0 0.75rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($licenses as $license)
                    <tr style="background:var(--bg);">
                        <td style="padding:0.9rem 0.75rem;font-weight:600;">{{ $license->product->name ?? '—' }}</td>
                        <td style="padding:0.9rem 0.75rem;">{{ $license->user->name ?? 'Unassigned' }}</td>
                        <td style="padding:0.9rem 0.75rem;font-family:monospace;">{{ $license->identifier }}</td>
                        <td style="padding:0.9rem 0.75rem;">
                            @if ($license->domains->isEmpty())
                                <span style="color:var(--muted);">—</span>
                            @else
                                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                                    @foreach ($license->domains as $domain)
                                        <span style="background:rgba(15,23,42,0.08);border-radius:999px;padding:0.15rem 0.6rem;font-size:0.8rem;">{{ $domain->domain }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td style="padding:0.9rem 0.75rem;">{{ $license->expires_at ? $license->expires_at->format('M j, Y') : 'No expiry' }}</td>
                        <td style="padding:0.9rem 0.75rem; display:flex; gap:0.5rem;">
                            <a class="link" href="{{ route('admin.licenses.edit', $license) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.licenses.destroy', $license) }}" onsubmit="return confirm('Delete this license?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:var(--error);cursor:pointer;padding:0;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:1rem 0.75rem;text-align:center;color:var(--muted);">
                            No licenses available yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem;">
        {{ $licenses->links() }}
    </div>
</div>
@endsection
