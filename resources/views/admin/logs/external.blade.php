@extends('layouts.app')

@section('title', 'Admin · External Logs')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>External logs</h1>
        <p class="lead">Latest ingested external logs.</p>
    </div>
    <div class="admin-nav" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        <a class="{{ request()->routeIs('admin.licenses.*') ? 'active' : '' }}" href="{{ route('admin.licenses.index') }}">Licenses</a>
        @if(config('products.enabled'))
            <a class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
        @endif
        <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>
        <a class="{{ request()->routeIs('admin.logs.index') ? 'active' : '' }}" href="{{ route('admin.logs.index') }}">App Log</a>
        <a class="{{ request()->routeIs('admin.event-logs.index') ? 'active' : '' }}" href="{{ route('admin.event-logs.index') }}">Event Logs</a>
        @if (config('admin.external_logs_enabled'))
            <a class="{{ request()->routeIs('admin.external-logs.index') ? 'active' : '' }}" href="{{ route('admin.external-logs.index') }}">External Logs</a>
        @endif
        @if (config('admin.servers_enabled'))
            <a class="{{ request()->routeIs('admin.servers.*') ? 'active' : '' }}" href="{{ route('admin.servers.index') }}">Servers</a>
        @endif
        <a class="{{ request()->routeIs('admin.tools.license-validation') ? 'active' : '' }}" href="{{ route('admin.tools.license-validation') }}">License Validation</a>
    </div>
</header>

@if ($logs->isEmpty())
    <div class="banner">No external logs yet.</div>
@else
    <div class="card" style="overflow:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>User</th>
                    <th>Source</th>
                    <th>IP</th>
                    <th>Context</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ optional($log->occurred_at)->toDateTimeString() ?? optional($log->created_at)->toDateTimeString() ?? '—' }}</td>
                        <td>{{ $log->type }}</td>
                        <td>{{ $log->user_id ?? '—' }}</td>
                        <td>{{ $log->source ?? '—' }}</td>
                        <td>{{ $log->ip ?? '—' }}</td>
                        <td>
                            <details>
                                <summary style="cursor:pointer;user-select:none;">Drill down</summary>
                                <pre style="white-space:pre-wrap;word-break:break-word;margin:0;">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endif
@endsection
