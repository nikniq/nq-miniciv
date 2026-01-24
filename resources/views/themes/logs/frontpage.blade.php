@extends('layouts.app')

@section('title', 'Logs · Management')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Operations</p>
        <h1>Log management & observability</h1>
        <p class="lead">Centralized access to event, external and application logs for troubleshooting and audits.</p>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
            @if(config('logs.enabled') && Route::has('admin.event-logs.index'))
                <a class="link button-reset" style="font-weight:600;" href="{{ route('admin.event-logs.index') }}">Event Logs</a>
            @endif
            @if(config('admin.external_logs_enabled'))
                <a class="link button-reset" style="font-weight:600;" href="{{ route('admin.external-logs.index') }}">External Logs</a>
            @endif
            @if(config('logs.enabled') && Route::has('admin.logs.index'))
                <a class="link button-reset" style="font-weight:600;" href="{{ route('admin.logs.index') }}">App Log</a>
            @endif
            @if(config('admin.servers_enabled'))
                <a class="link button-reset" style="font-weight:600;" href="{{ route('admin.servers.index') }}">Servers</a>
            @endif
            @if(config('license.enabled') && config('license.public_validation') && Route::has('admin.tools.license-validation'))
                <a class="link button-reset" style="font-weight:600;" href="{{ route('admin.tools.license-validation') }}">License Validation</a>
            @endif
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.5rem;align-items:center;margin-top:1rem;">
        @if(config('logs.enabled') && Route::has('admin.event-logs.index'))
            <a class="link" href="{{ route('admin.event-logs.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Browse Event Logs</a>
        @endif
        @if(config('admin.external_logs_enabled'))
            <a class="link" href="{{ route('admin.external-logs.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">External Logs</a>
        @endif
        @if(config('logs.enabled') && Route::has('admin.logs.index'))
            <a class="link" href="{{ route('admin.logs.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Application Log</a>
        @endif
    </div>
</header>

<section class="card">
    <div class="grid">
        <article>
            <p class="eyebrow" style="margin-bottom:0.35rem;">Event Audits</p>
            <h2 style="margin-top:0;">Investigate events</h2>
            <p>Search and drill into user events, purchases, and system changes. Use event details to trace actions and timelines.</p>
            @if(config('logs.enabled') && Route::has('admin.event-logs.index'))
                <a class="link" href="{{ route('admin.event-logs.index') }}">Open Event Logs</a>
            @endif
        </article>
        <article>
            <p class="eyebrow" style="margin-bottom:0.35rem;">Ingested Logs</p>
            <h2 style="margin-top:0;">External sources</h2>
            <p>Review logs from external systems and integrations. Filter by source, IP, and occurrence time to prioritize incidents.</p>
            @if(config('admin.external_logs_enabled'))
            <a class="link" href="{{ route('admin.external-logs.index') }}">Open External Logs</a>
            @endif
        </article>
        <article>
            <p class="eyebrow" style="margin-bottom:0.35rem;">Infrastructure</p>
            <h2 style="margin-top:0;">Servers & health</h2>
            <p>Inspect registered servers, review recent heartbeats and logs to spot availability or configuration issues.</p>
            @if(config('admin.servers_enabled'))
            <a class="link" href="{{ route('admin.servers.index') }}">View Servers</a>
            @endif
        </article>
    </div>
</section>

<section class="card alt">
    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div>
            <p class="eyebrow" style="color:rgba(255,255,255,0.7);">Quick actions</p>
            <h2 style="margin:0;">Search, export, and investigate</h2>
            <p style="margin:0;color:rgba(255,255,255,0.8);">Use the quick links above to jump straight into log lists and expand individual entries for full context and JSON payloads.</p>
        </div>
        <div>
            @if(config('logs.enabled') && Route::has('admin.event-logs.index'))
                <a class="link" style="color:#fff;font-weight:700;" href="{{ route('admin.event-logs.index') }}">Open log manager →</a>
            @endif
        </div>
    </div>
</section>
@endsection
