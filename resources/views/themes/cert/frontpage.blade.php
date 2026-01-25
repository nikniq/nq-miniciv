@extends('layouts.app')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Certificate</p>
        <h1>TLS Certificate lookup</h1>
        <p class="lead">Fetch the peer TLS certificate for a host and display parsed details.</p>
    </div>

    <div style="margin-top:1rem;display:flex;justify-content:center;">
        <form id="cert-form" style="display:grid;gap:0.5rem;max-width:720px;width:100%;">
            @csrf
            <label>
                <span>Host (or IP)</span>
                <input type="text" name="host" placeholder="example.com" required>
            </label>
            
            <div style="display:flex;gap:0.5rem;justify-content:flex-start;">
                <button type="submit">Lookup</button>
                @if(config('shop.enabled') && config('products.enabled'))
                    <a class="link" href="{{ url('/shop') }}">Browse products</a>
                @endif
            </div>
        </form>
    </div>
</header>

<section class="card">
    <h2>Result</h2>
    <div id="cert-result" style="max-height:60vh;overflow:auto;padding:0.5rem;"></div>
</section>

@push('scripts')
<script>
document.getElementById('cert-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    const resultEl = document.getElementById('cert-result');
    resultEl.textContent = 'Looking up…';

    try {
        const token = document.querySelector('input[name=_token]').value;
        const res = await fetch('{{ url('/cert/lookup') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: data
        });

        if (!res.ok) {
            const err = await res.json().catch(()=>({error:res.statusText}));
            resultEl.innerHTML = `<pre style="white-space:pre-wrap;padding:1rem;background:#111;color:#fff;border-radius:0.4rem;">${escapeHtml(err.error || JSON.stringify(err, null, 2))}</pre>`;
        } else {
            const json = await res.json();
            // Build structured view
            resultEl.innerHTML = '';
            if (json.crt_sh) {
                const container = document.createElement('div');
                const list = document.createElement('div');
                list.style.display = 'grid';
                list.style.gap = '0.5rem';
                json.crt_sh.forEach((item, idx) => {
                    const d = document.createElement('details');
                    const s = document.createElement('summary');
                    s.textContent = item.name_value || item.common_name || (`Entry ${idx+1}`);
                    d.appendChild(s);
                    const pre = document.createElement('pre');
                    pre.style.whiteSpace = 'pre-wrap';
                    pre.style.background = '#071029';
                    pre.style.color = '#dbeafe';
                    pre.style.padding = '0.75rem';
                    pre.style.borderRadius = '0.4rem';
                    pre.textContent = JSON.stringify(item, null, 2);
                    d.appendChild(pre);
                    list.appendChild(d);
                });
                container.appendChild(list);
                // raw dump toggle
                if (json.raw) {
                    const rawD = document.createElement('details');
                    const rawS = document.createElement('summary');
                    rawS.textContent = 'Raw crt.sh response';
                    rawD.appendChild(rawS);
                    const rawPre = document.createElement('pre');
                    rawPre.style.whiteSpace = 'pre-wrap';
                    rawPre.style.background = '#071029';
                    rawPre.style.color = '#dbeafe';
                    rawPre.style.padding = '0.75rem';
                    rawPre.style.borderRadius = '0.4rem';
                    rawPre.textContent = json.raw;
                    rawD.appendChild(rawPre);
                    container.appendChild(rawD);
                }
                resultEl.appendChild(container);
            } else if (json.pem) {
                const pemD = document.createElement('details');
                const pemS = document.createElement('summary');
                pemS.textContent = 'Peer certificate (PEM)';
                pemD.appendChild(pemS);
                const pemPre = document.createElement('pre');
                pemPre.style.whiteSpace = 'pre-wrap';
                pemPre.style.background = '#071029';
                pemPre.style.color = '#dbeafe';
                pemPre.style.padding = '0.75rem';
                pemPre.style.borderRadius = '0.4rem';
                pemPre.textContent = json.pem;
                pemD.appendChild(pemPre);
                resultEl.appendChild(pemD);
                if (json.parsed) {
                    const parsedD = document.createElement('details');
                    const parsedS = document.createElement('summary');
                    parsedS.textContent = 'Parsed certificate fields';
                    parsedD.appendChild(parsedS);
                    const parsedPre = document.createElement('pre');
                    parsedPre.style.whiteSpace = 'pre-wrap';
                    parsedPre.style.background = '#071029';
                    parsedPre.style.color = '#dbeafe';
                    parsedPre.style.padding = '0.75rem';
                    parsedPre.style.borderRadius = '0.4rem';
                    parsedPre.textContent = JSON.stringify(json.parsed, null, 2);
                    parsedD.appendChild(parsedPre);
                    resultEl.appendChild(parsedD);
                }
            } else {
                resultEl.innerHTML = `<pre style="white-space:pre-wrap;padding:1rem;background:#111;color:#fff;border-radius:0.4rem;">${escapeHtml(JSON.stringify(json, null, 2))}</pre>`;
            }
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
