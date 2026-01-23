@extends('layouts.app')

@section('title', 'API Lab · GD Login')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">API Lab</p>
        <h1>Validate licenses without leaving the browser.</h1>
        <p class="lead">Experiment with the `POST /api/licenses/validate` endpoint, then wire the same payloads into your own services.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.5rem;align-items:center;">
        @if(config('shop.enabled') && config('products.enabled'))
            <a class="link" href="{{ url('/shop') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Browse products</a>
        @endif
        <a class="link" href="{{ route('register') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Create dashboard account</a>
    </div>
</header>

@if(config('license.public_validation'))
    <section class="card">
        <form id="license-test-form" style="display:grid;gap:1rem;">
            <label>
                <span>License code</span>
                <input type="text" name="license_code" placeholder="ABCD-EFGH-IJKL" required>
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

            try {
                const response = await fetch('{{ url('/api/licenses/validate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                let json;
                try {
                    json = await response.json();
                } catch (parseError) {
                    throw new Error('Received an unreadable response from the API.');
                }

                if (!response.ok) {
                    const message = json?.reason || json?.message || 'Request failed with status ' + response.status;
                    throw new Error(message);
                }

                statusPill.textContent = json.valid ? 'VALID' : 'INVALID';
                statusPill.style.background = json.valid ? 'rgba(22, 163, 74, 0.15)' : 'rgba(220, 38, 38, 0.15)';
                statusPill.style.color = json.valid ? 'var(--success)' : 'var(--error)';
                resultJson.textContent = JSON.stringify(json, null, 2);
                resultCard.style.display = 'block';

                if (!json.valid) {
                    const reason = json.reason ? 'License is not valid: ' + json.reason : 'License is not valid.';
                    errorMessage.textContent = reason;
                    errorCard.style.display = 'block';
                }
            } catch (error) {
                resultCard.style.display = 'none';
                errorMessage.textContent = error.message || 'Unable to reach the API.';
                errorCard.style.display = 'block';
            }
        });
    })();
    </script>
    @endpush
@else
    <section class="card">
        <p style="margin:0;color:var(--muted);">License validation is disabled.</p>
    </section>
@endif
@endsection


