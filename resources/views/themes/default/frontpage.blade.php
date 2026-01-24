@extends('layouts.app')

@section('title', 'Glitchdata · Identity & Licensing')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Glitchdata Platform</p>
        <h1>Identity, licensing, and validation in one simple portal.</h1>
        <p class="lead">Launch a secure dashboard for your team, grant software seats, and verify entitlements through a clean API toolkit.</p>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
            @if(config('shop.enabled'))
                <button type="button" class="link button-reset" style="font-weight:600;" onclick="window.location='{{ url('/shop') }}'">Explore the shop →</button>
            @endif
            @if(config('apilab.enabled'))
            <button type="button" class="link button-reset" onclick="window.location='{{ url('/api-lab') }}'">Test the API →</button>
            @endif
            <button type="button" class="link button-reset" onclick="window.location='{{ route('register') }}'">Create an account</button>
        </div>
    </div>
</header>

<section class="card">
    <div class="grid">
        @if(config('license.enabled'))
        <article>
            <p class="eyebrow" style="margin-bottom:0.35rem;">01 · Self-serve licenses</p>
            <h2 style="margin-top:0;">Purchase seats from the catalog</h2>
            <p>Browse curated products, preview pricing and duration, then assign licenses to yourself or your team members directly from the dashboard.</p>
            @if(config('shop.enabled'))
                <a class="link" href="{{ url('/shop') }}">Visit the shop</a>
            @endif
        </article>
        @endif
        @if(config('license.public_validation'))
        <article>
            <p class="eyebrow" style="margin-bottom:0.35rem;">02 · API validation</p>
            <h2 style="margin-top:0;">Verify entitlements programmatically</h2>
            <p>Use the hosted API Lab to post license codes and seat counts, mirroring how your backend can confirm availability in production.</p>
            @if(config('apilab.enabled'))
            <a class="link" href="{{ url('/api-lab') }}">Open the API Lab</a>
            @endif
        </article>
        @endif
        <article>
            <p class="eyebrow" style="margin-bottom:0.35rem;">03 · Admin tooling</p>
            <h2 style="margin-top:0;">Manage users, products, and licenses</h2>
            <p>Admins gain a polished console to edit products, onboard users, and audit allocations—no extra front-end build pipeline required.</p>
            <a class="link" href="{{ route('login') }}">Sign in as admin</a>
        </article>
    </div>
</section>

<section class="card alt">
    <div style="display:flex;flex-direction:column;gap:1rem;">
                <div>
                        <p class="eyebrow" style="color:#000;">API quickstart</p>
                        <h2 style="margin:0;">`POST /api/licenses/validate`</h2>
                        <p style="margin:0;color:inherit;">Send a license code plus requested seats to confirm availability, expiration, and seat counts—all responses structured for easy automation.</p>
                </div>
                <pre style="margin:0;background:rgba(0,0,0,0.3);padding:1rem;border-radius:0.9rem;color:#fff;font-family:monospace;overflow:auto;">{
    "license_code": "ACTV-ABCD-1234",
    "seats_requested": 3
}</pre>
        <div>
            @if(config('apilab.enabled'))
            <a class="link" style="color:#fff;font-weight:700;" href="{{ url('/api-lab') }}">Send a sample request →</a>
            @endif
        </div>
    </div>
</section>
@include('partials.footer')
@endsection
