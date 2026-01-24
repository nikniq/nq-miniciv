@extends('layouts.app')

@section('title', 'Admin · Media')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Media library</h1>
        <p class="lead">Uploaded images and media records.</p>
    </div>
    <div class="admin-nav">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        @if(config('license.enabled') && config('license.admin_enabled'))
            <a class="{{ request()->routeIs('admin.licenses.*') ? 'active' : '' }}" href="{{ route('admin.licenses.index') }}">Licenses</a>
        @endif
        @if(config('products.enabled'))
            <a class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
        @endif
        <a class="{{ request()->routeIs('admin.media.*') ? 'active' : '' }}" href="{{ route('admin.media.index') }}">Media</a>
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
        <a href="{{ route('admin.media.create') }}" class="{{ request()->routeIs('admin.media.create') ? 'active' : '' }}">+ Upload</a>
    </div>
</header>

@if (session('success'))
    <div class="banner success">{{ session('success') }}</div>
@endif

<div class="card">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;">
        @forelse ($media as $m)
            <div style="width:160px;border:1px solid rgba(15,23,42,0.06);padding:0.5rem;border-radius:0.6rem;text-align:center;background:var(--bg);">
                <div style="height:100px;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    @php($exists = Storage::disk($m->disk)->exists($m->path))
                    @if($exists)
                        <img src="{{ Storage::disk($m->disk)->url($m->path) }}" alt="{{ $m->filename }}" style="max-width:100%;max-height:100%;object-fit:contain;" />
                    @else
                        <div style="color:var(--error);font-size:0.85rem;">Missing file</div>
                    @endif
                </div>
                <div style="margin-top:0.5rem;font-size:0.85rem;color:var(--muted);">{{ $m->filename }}</div>
                @php($url = Storage::disk($m->disk)->url($m->path))
                @if(! Storage::disk($m->disk)->exists($m->path))
                    <div style="margin-top:0.25rem;font-size:0.75rem;color:var(--error);word-break:break-all;">{{ $url }}</div>
                @endif
                <div style="margin-top:0.5rem;display:flex;justify-content:center;gap:0.5rem;">
                    <form method="POST" action="{{ route('admin.media.destroy', $m) }}" onsubmit="return confirm('Delete this media?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" title="Delete" aria-label="Delete {{ $m->filename }}" style="background:var(--error);color:#fff;padding:0.45rem 0.8rem;border-radius:0.6rem;border:none;cursor:pointer;font-weight:600;">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div style="padding:2rem;color:var(--muted);">No media uploaded yet.</div>
        @endforelse
    </div>

    @if($media->lastPage() > 1)
        <div style="margin-top:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
            <div style="font-size:0.9rem;color:var(--muted);">
                Showing {{ $media->firstItem() }}–{{ $media->lastItem() }} of {{ $media->total() }}
            </div>

            <div style="display:flex;gap:0.5rem;align-items:center;">
                @if($media->onFirstPage())
                    <span style="opacity:0.5;padding:0.4rem 0.75rem;border-radius:0.5rem;border:1px solid rgba(15,23,42,0.06);">Prev</span>
                @else
                    <a href="{{ $media->previousPageUrl() }}" style="padding:0.4rem 0.75rem;border-radius:0.5rem;border:1px solid rgba(15,23,42,0.06);">Prev</a>
                @endif

                <span style="font-size:0.9rem;color:var(--muted);">Page {{ $media->currentPage() }} of {{ $media->lastPage() }}</span>

                @if($media->hasMorePages())
                    <a href="{{ $media->nextPageUrl() }}" style="padding:0.4rem 0.75rem;border-radius:0.5rem;border:1px solid rgba(15,23,42,0.06);">Next</a>
                @else
                    <span style="opacity:0.5;padding:0.4rem 0.75rem;border-radius:0.5rem;border:1px solid rgba(15,23,42,0.06);">Next</span>
                @endif
            </div>
        </div>
    @else
        <div style="margin-top:1rem;color:var(--muted);">Showing all {{ $media->total() }} media items.</div>
    @endif
</div>
@endsection
