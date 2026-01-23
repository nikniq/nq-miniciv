@extends('layouts.app')

@section('title', 'Admin · Logs')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Application logs</h1>
        <p class="lead">Showing the latest {{ $maxLines }} lines from {{ $path }}.</p>
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
        <a class="{{ request()->routeIs('admin.event-logs.index') ? 'active' : '' }}" href="{{ route('admin.event-logs.index') }}">Event Logs</a>
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

@if ($missing)
    <div class="banner error">Log file not found at {{ $path }}.</div>
@elseif (empty($lines))
    <div class="banner">No log entries found.</div>
@else
    <div class="card" style="overflow:auto;max-height:70vh;">
        <pre style="margin:0;white-space:pre-wrap;word-break:break-word;">{{ implode("\n", $lines) }}</pre>
    </div>
@endif
@endsection
