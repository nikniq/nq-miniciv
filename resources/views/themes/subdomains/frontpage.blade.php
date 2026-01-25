@extends('layouts.app')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Subdomains</p>
        <h1>Subdomain enumeration</h1>
        <p class="lead">Query Certificate Transparency logs for certificates containing the target host and extract observed subdomains.</p>
    </div>

    <div style="margin-top:1rem;display:flex;justify-content:center;">
        <form id="subdomains-form" style="display:grid;gap:0.5rem;max-width:720px;width:100%;">
            @csrf
            <label>
                <span>Host (e.g. example.com)</span>
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
    <div id="subdomains-result" style="max-height:60vh;overflow:auto;padding:0.5rem;"></div>
</section>

@push('scripts')
<script>
document.getElementById('subdomains-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    const resultEl = document.getElementById('subdomains-result');
    resultEl.innerHTML = 'Looking up…';

    try {
        const token = document.querySelector('input[name=_token]').value;
        const res = await fetch('{{ url('/subdomains/lookup') }}', {
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
            const info = document.createElement('div');
            info.innerHTML = `<p><strong>Found:</strong> ${json.count} subdomains</p>`;
            resultEl.appendChild(info);

            const list = document.createElement('div');
            list.style.display = 'grid';
            list.style.gap = '0.35rem';

            json.subdomains.forEach(s => {
                const el = document.createElement('div');
                el.style.padding = '0.5rem';
                el.style.background = '#071029';
                el.style.color = '#dbeafe';
                el.style.borderRadius = '0.35rem';
                el.textContent = s;
                list.appendChild(el);
            });

            resultEl.appendChild(list);

            if (json.raw) {
                const rawD = document.createElement('details');
                const rawS = document.createElement('summary');
                rawS.textContent = 'Detailed Response';
                rawD.appendChild(rawS);
                const rawPre = document.createElement('pre');
                rawPre.style.whiteSpace = 'pre-wrap';
                rawPre.style.background = '#071029';
                rawPre.style.color = '#dbeafe';
                rawPre.style.padding = '0.75rem';
                rawPre.style.borderRadius = '0.4rem';
                rawPre.textContent = json.raw;
                rawD.appendChild(rawPre);
                resultEl.appendChild(rawD);
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
