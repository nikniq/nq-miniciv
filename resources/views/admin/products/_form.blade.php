@csrf
<div class="stack" style="display:grid;gap:1rem;">
    <label>
        <span>Name</span>
        <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required>
    </label>
    <label>
        <span>Product code</span>
        <input type="text" name="product_code" value="{{ old('product_code', $product->product_code ?? '') }}" required>
    </label>
    <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        <label>
            <span>Vendor</span>
            <input type="text" name="vendor" value="{{ old('vendor', $product->vendor ?? '') }}">
        </label>
        <label>
            <span>Product URL (admin only)</span>
            <input type="url" name="url" value="{{ old('url', $product->url ?? '') }}" placeholder="https://example.com/product-page">
        </label>
        <label>
            <span>Category</span>
            <input type="text" name="category" value="{{ old('category', $product->category ?? '') }}">
        </label>
        <label>
            <span>Price (USD per seat)</span>
            <input type="number" name="price" min="0" step="0.01" value="{{ old('price', $product->price ?? '0.00') }}" required>
        </label>
        <label>
            <span>License duration (months)</span>
            <input type="number" name="duration_months" min="1" max="60" value="{{ old('duration_months', $product->duration_months ?? 12) }}" required>
        </label>
    </div>
    <label>
        <span>Description</span>
        <textarea name="description" rows="4" style="width:100%;border:1px solid rgba(15,23,42,0.15);border-radius:0.9rem;padding:0.85rem 1rem;font-size:1rem;">{{ old('description', $product->description ?? '') }}</textarea>
    </label>

    <label>
        <span>Product image</span>
        <div style="display:flex;gap:1rem;align-items:flex-start;">
            <div style="min-width:260px;">
                <input id="media-search" type="text" placeholder="Search media by filename..." style="width:100%;padding:0.5rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.6rem;" />

                <div id="media-results" style="margin-top:0.5rem;max-height:240px;overflow:auto;border:1px solid rgba(15,23,42,0.06);border-radius:0.6rem;background:var(--bg);"></div>

                <select name="media_id" id="media-select" style="display:none;">
                    <option value="">-- none --</option>
                    @foreach(($media ?? []) as $m)
                        <option value="{{ $m->id }}" data-url="{{ Storage::disk($m->disk)->url($m->path) }}" {{ (int)old('media_id', $product->media_id ?? 0) === $m->id ? 'selected' : '' }}>{{ $m->filename }}</option>
                    @endforeach
                </select>
            </div>

            <div style="width:84px;height:84px;border:1px solid rgba(15,23,42,0.06);display:flex;align-items:center;justify-content:center;background:var(--bg);">
                <img id="media-preview" src="{{ optional($product->media)->path ? Storage::disk(optional($product->media)->disk)->url(optional($product->media)->path) : '' }}" alt="preview" style="max-width:100%;max-height:100%;object-fit:contain;" />
            </div>

            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <a class="link" href="{{ route('admin.media.create') }}">Upload new</a>
                <button type="button" id="media-clear" class="link" style="color:var(--muted);border:none;background:none;padding:0;">Clear</button>
            </div>
        </div>
    </label>
</div>

<div style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;">
    <button type="submit">{{ $submitLabel }}</button>
    <a class="link" href="{{ route('admin.products.index') }}" style="display:inline-flex;align-items:center;justify-content:center;padding:0.65rem 1rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);">Cancel</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('media-select');
    var preview = document.getElementById('media-preview');
    var search = document.getElementById('media-search');
    var results = document.getElementById('media-results');
    var clearBtn = document.getElementById('media-clear');

    if (!select || !results || !search) return;

    // Build an in-memory list of media items from the hidden select
    var items = [];
    Array.from(select.options).forEach(function (opt) {
        if (!opt.value) return;
        items.push({ id: opt.value, filename: opt.text, url: opt.dataset.url || '' });
    });

    function renderList(list) {
        results.innerHTML = '';
        if (list.length === 0) {
            results.innerHTML = '<div style="padding:0.75rem;color:var(--muted);">No matches</div>';
            return;
        }
        list.forEach(function (it) {
            var el = document.createElement('div');
            el.className = 'media-result';
            el.style.display = 'flex';
            el.style.gap = '0.5rem';
            el.style.padding = '0.5rem';
            el.style.alignItems = 'center';
            el.style.cursor = 'pointer';
            el.style.borderBottom = '1px solid rgba(15,23,42,0.03)';

            var thumb = document.createElement('img');
            thumb.src = it.url || '';
            thumb.alt = it.filename;
            thumb.style.width = '40px';
            thumb.style.height = '40px';
            thumb.style.objectFit = 'cover';
            thumb.style.border = '1px solid rgba(15,23,42,0.06)';
            thumb.style.borderRadius = '4px';

            var txt = document.createElement('div');
            txt.style.fontSize = '0.9rem';
            txt.style.color = 'var(--muted)';
            txt.textContent = it.filename;

            el.appendChild(thumb);
            el.appendChild(txt);

            el.addEventListener('click', function () {
                select.value = it.id;
                // trigger change
                var ev = new Event('change'); select.dispatchEvent(ev);
                preview.src = it.url || '';
                // highlight selection
                Array.from(results.children).forEach(function(c){ c.style.background=''; });
                el.style.background = 'rgba(15,23,42,0.03)';
            });

            results.appendChild(el);
        });
    }

    function filter(q) {
        q = (q || '').trim().toLowerCase();
        if (!q) return items.slice(0, 50);
        return items.filter(function (it) {
            return it.filename.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 200);
    }

    // initial render (first 50)
    renderList(items.slice(0,50));

    search.addEventListener('input', function (e) {
        var list = filter(e.target.value);
        renderList(list);
    });

    select.addEventListener('change', function () {
        var opt = select.options[select.selectedIndex];
        var url = opt ? opt.dataset.url : '';
        if (url) preview.src = url; else preview.src = '';
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            select.value = '';
            preview.src = '';
            Array.from(results.children).forEach(function(c){ c.style.background=''; });
        });
    }
});
</script>
@endpush
