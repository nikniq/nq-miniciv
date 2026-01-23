@extends('layouts.app')

@section('title', 'Admin · License API Tester')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin · Tools</p>
        <h1>License validation tester</h1>
        <p class="lead">Send sample payloads to <code style="font-family:monospace;">POST /api/licenses/validate</code> without leaving the browser.</p>
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
        <a class="{{ request()->routeIs('admin.event-logs.index') ? 'active' : '' }}" href="{{ route('admin.event-logs.index') }}">Logs</a>
        <a class="active" href="{{ route('admin.tools.license-validation') }}">License Validation</a>
    </div>
</header>

<section class="card">
    <form id="license-test-form" style="display:grid;gap:1rem;">
        <label>
            <span>License code</span>
            <input type="text" name="license_code" placeholder="ABCD-EFGH-IJKL" required>
        </label>
        <label>
            <span>Seats requested (optional)</span>
            <input type="number" name="seats_requested" min="1" placeholder="1">
        </label>
        <button type="submit">Validate license</button>
    </form>
</section>

<section class="card" id="result-card" style="display:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
        <h2 style="margin:0;">Response</h2>
        <span id="status-pill" style="border-radius:999px;padding:0.25rem 0.75rem;font-weight:600;"></span>
    </div>
    <pre id="result-json" style="margin-top:1rem;background:var(--bg);padding:1rem;border-radius:0.75rem;overflow:auto;"></pre>
</section>

<section class="card" id="error-card" style="display:none;">
    <h2 style="margin-top:0;">Error</h2>
    <p id="error-message" style="color:var(--error);"></p>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('license-test-form');
    const resultCard = document.getElementById('result-card');
    const errorCard = document.getElementById('error-card');
    const statusPill = document.getElementById('status-pill');
    const resultJson = document.getElementById('result-json');
    const errorMessage = document.getElementById('error-message');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorCard.style.display = 'none';
        resultCard.style.display = 'none';
        const formData = new FormData(form);
        const payload = {
            license_code: formData.get('license_code'),
        };
        const seats = formData.get('seats_requested');
        if (seats) {
            payload.seats_requested = Number(seats);
        }

        try {
            const response = await fetch('{{ url('/api/licenses/validate') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const json = await response.json();
            statusPill.textContent = json.valid ? 'VALID' : 'INVALID';
            statusPill.style.background = json.valid ? 'rgba(22, 163, 74, 0.15)' : 'rgba(220, 38, 38, 0.15)';
            statusPill.style.color = json.valid ? 'var(--success)' : 'var(--error)';
            resultJson.textContent = JSON.stringify(json, null, 2);
            resultCard.style.display = 'block';
        } catch (error) {
            errorMessage.textContent = error.message || 'Unable to reach the API.';
            errorCard.style.display = 'block';
        }
    });
})();
</script>
@endpush
