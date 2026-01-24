@extends('layouts.app')

@section('title', 'Create account · GD Login')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">New here?</p>
        <h1>Create your GD Login account</h1>
        <p class="lead">Fill out the details below to unlock your dashboard instantly.</p>
    </div>
</header>

<div class="grid">
    <section class="card alt" style="color:var(--text);">
        <style>
            /* Override .card.alt default white text for this form so inputs are readable */
            .card.alt input,
            .card.alt textarea,
            .card.alt label span,
            .card.alt h2 { color: var(--text) !important; }
        </style>
        <h2>Create account</h2>
        <form method="POST" action="{{ route('register') }}">
            @csrf
            @if ($errors->any())
                <div class="banner error">
                    <ul style="margin:0;padding-left:1.25rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <label>
                <span>Name</span>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </label>
            <label>
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>
            <label>
                <span>Admin contact email (optional)</span>
                <input type="email" name="admin_email" value="{{ old('admin_email') }}" placeholder="ops@example.com">
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required>
            </label>
            <label>
                <span>Confirm password</span>
                <input type="password" name="password_confirmation" required>
            </label>
            <label>
                <span>Solve this to prove you're human: {{ $captchaQuestion ?? session('captcha.challenge') ?? '1 + 1' }}</span>
                <input type="number" name="captcha" inputmode="numeric" min="0" required>
            </label>
            <button type="submit">Create account</button>
            @if (config('social.providers.google.enabled'))
            <button type="button" onclick="window.location='{{ route('oauth.redirect', ['provider' => 'google']) }}'" style="width:100%;display:flex;align-items:center;justify-content:center;gap:0.5rem;background:#fff;color:#0f172a;border:1px solid rgba(15,23,42,0.15);box-shadow:0 8px 18px rgba(15,23,42,0.08);">
                <span style="font-weight:700;">Continue with Google</span>
            </button>
            @endif
            @if (config('social.providers.meta.enabled'))
            <button type="button" onclick="window.location='{{ route('oauth.redirect', ['provider' => 'meta']) }}'" style="width:100%;display:flex;align-items:center;justify-content:center;gap:0.5rem;background:#fff;color:#0f172a;border:1px solid rgba(15,23,42,0.15);box-shadow:0 8px 18px rgba(15,23,42,0.08);">
                <span style="font-weight:700;">Continue with Meta</span>
            </button>
            @endif
            <p class="hint">Already have access? <a class="link" href="{{ route('login') }}">Sign in</a>.</p>
        </form>
    </section>
</div>
@endsection
