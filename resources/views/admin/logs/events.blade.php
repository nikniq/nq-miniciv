@extends('layouts.app')

@section('title', 'Admin · Event Logs')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Event logs</h1>
        <p class="lead">Recent login, purchase, and change events.</p>
    </div>
    <div class="admin-nav" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        @if(config('license.enabled'))
            <a class="{{ request()->routeIs('admin.licenses.*') ? 'active' : '' }}" href="{{ route('admin.licenses.index') }}">Licenses</a>
        @endif
        @if(config('products.enabled'))
            <a class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
        @endif
        <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>
        @if(config('logs.enabled') && Route::has('admin.logs.index'))
            <a class="{{ request()->routeIs('admin.logs.index') ? 'active' : '' }}" href="{{ route('admin.logs.index') }}">App Log</a>
        @endif
        @if(config('logs.enabled') && Route::has('admin.event-logs.index'))
            <a class="{{ request()->routeIs('admin.event-logs.index') ? 'active' : '' }}" href="{{ route('admin.event-logs.index') }}">Event Logs</a>
        @endif
        @if (config('admin.external_logs_enabled'))
            <a class="{{ request()->routeIs('admin.external-logs.index') ? 'active' : '' }}" href="{{ route('admin.external-logs.index') }}">External Logs</a>
        @endif
        @if (config('admin.servers_enabled'))
            <a class="{{ request()->routeIs('admin.servers.*') ? 'active' : '' }}" href="{{ route('admin.servers.index') }}">Servers</a>
        @endif
        @if(config('license.enabled') && config('license.public_validation') && Route::has('admin.tools.license-validation'))
            <a class="{{ request()->routeIs('admin.tools.license-validation') ? 'active' : '' }}" href="{{ route('admin.tools.license-validation') }}">License Validation</a>
        @endif
    </div>
</header>

@if ($logs->isEmpty())
    <div class="banner">No event logs yet.</div>
@else
    <div class="card" style="overflow:auto;">
        <table class="table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:0.5rem;">When</th>
                    <th style="text-align:left;padding:0.5rem;">Type</th>
                    <th style="text-align:left;padding:0.5rem;">User</th>
                    <th style="text-align:left;padding:0.5rem;">Context</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td style="padding:0.5rem;white-space:nowrap;">{{ $log->created_at->toDateTimeString() }}</td>
                        <td style="padding:0.5rem;">{{ $log->type }}</td>
                        <td style="padding:0.5rem;">{{ $log->user_id ?? '—' }}</td>
                        <td style="padding:0.5rem;"> 
                            <details>
                                <summary style="cursor:pointer;user-select:none;font-weight:600;">Drill down</summary>
                                <pre style="white-space:pre-wrap;word-break:break-word;margin:0;font-family:monospace;">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem;">
        {{ $logs->links() }}
    </div>
@endif
@endsection
