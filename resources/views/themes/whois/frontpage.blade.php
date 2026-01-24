@extends('layouts.app')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Whois</p>
        <h1>Domain WHOIS lookup</h1>
        <p class="lead">Enter a domain to view its WHOIS record. This performs a server-side lookup and returns the raw record.</p>
    </div>

    <div style="margin-top:1rem;">
        <form id="whois-form" style="display:grid;gap:0.5rem;max-width:720px;">
            @csrf
            <label>
                <span>Domain</span>
                <input type="text" name="domain" placeholder="example.com" required>
            </label>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit">Lookup</button>
                @if(config('shop.enabled') && config('products.enabled'))
                    <a class="link" href="{{ url('/shop') }}">Browse products</a>
                @endif
                @if(config('apilab.enabled'))
                    <a class="link" href="{{ url('/api-lab') }}">API Lab</a>
                @endif
            </div>
        </form>
    </div>
</header>

<section class="card">
    <h2>Result</h2>
    <div id="whois-result" style="max-height:50vh;overflow:auto;padding:0.5rem;"></div>
</section>

@push('scripts')
<script>
document.getElementById('whois-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    const resultEl = document.getElementById('whois-result');
    resultEl.textContent = 'Looking up…';

    try {
        const token = document.querySelector('input[name=_token]').value;
        const res = await fetch('{{ url('/whois/lookup') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: data
        });

        if (!res.ok) {
            const err = await res.json().catch(()=>({error:res.statusText}));
            resultEl.innerHTML = `<pre style="white-space:pre-wrap;padding:1rem;background:#111;color:#fff;border-radius:0.4rem;">${escapeHtml(err.error || JSON.stringify(err, null, 2))}</pre>`;
        } else {
            const json = await res.json();
            resultEl.innerHTML = '';
            const d = document.createElement('details');
            const s = document.createElement('summary');
            s.textContent = 'Raw WHOIS output';
            d.appendChild(s);
            const pre = document.createElement('pre');
            pre.style.whiteSpace = 'pre-wrap';
            pre.style.background = '#071029';
            pre.style.color = '#dbeafe';
            pre.style.padding = '0.75rem';
            pre.style.borderRadius = '0.4rem';
            pre.textContent = json.whois || '(no data)';
            d.appendChild(pre);
            resultEl.appendChild(d);
        }
    } catch (err) {
        resultEl.innerHTML = `<pre style="white-space:pre-wrap;padding:1rem;background:#111;color:#fff;border-radius:0.4rem;">${escapeHtml(String(err))}</pre>`;
    } finally {
        btn.disabled = false;
    }
});

function escapeHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush

@endsection
