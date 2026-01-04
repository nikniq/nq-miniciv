@extends('layouts.app')

@section('title', 'Games')

@section('content')
    <style>
        .arcade-hero {
            background: linear-gradient(135deg,#0f172a 0%, #0b1020 40%, #24123b 100%);
            color: #fff;
            padding: 3rem 1rem;
            text-align: center;
            border-bottom: 6px solid #ff3b81;
        }
        .arcade-hero h1 { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; letter-spacing: 2px; font-weight:800; font-size:2.25rem; margin:0.5rem 0; }
        .arcade-sub { color: #ffd6e8; opacity:0.95; margin-bottom:1rem; }
        .arcade-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; padding:1rem; }
        .arcade-card { background: linear-gradient(180deg,#0b1220, #071028); border:2px solid rgba(255,59,129,0.08); padding:1rem; border-radius:8px; box-shadow: 0 6px 20px rgba(0,0,0,0.5); color:#e6f7ff; }
        .arcade-card h3 { color:#fff; margin:0 0 0.5rem 0; font-weight:700; }
        .arcade-badge { display:inline-block; padding:0.25rem 0.5rem; background:#ff3b81; color:#fff; border-radius:4px; font-size:0.8rem; font-weight:700; }
        .play-btn { display:inline-block; margin-top:0.75rem; background:#00d1ff; color:#021122; padding:0.5rem 0.75rem; border-radius:6px; text-decoration:none; font-weight:700; }

        /* Prominent search bar */
        .games-search-form { display:flex; gap:0.5rem; align-items:center; max-width:960px; margin:0 auto; }
        .games-search-input { flex:1; padding:0.9rem 1rem; border-radius:10px; border:3px solid rgba(10,10,10,0.92); background:#ffffff; color:#0b1220; font-size:1.05rem; box-shadow:none; }
        .games-search-input::placeholder { color:rgba(11,18,32,0.45); }
        .games-clear-btn { background:#ff3b81; color:#fff; padding:0.5rem 0.8rem; border-radius:8px; text-decoration:none; font-weight:800; border:2px solid rgba(11,18,32,0.92); }

        /* Tile with fixed aspect ratio (16:9). Uses ::before to reserve space. */
        .arcade-tile { display:block; position:relative; width:100%; border-radius:12px; background-size:cover; background-position:center; background-repeat:no-repeat; background-color:#000; overflow:hidden; transition:transform .18s cubic-bezier(.2,.9,.2,1), box-shadow .18s cubic-bezier(.2,.9,.2,1); aspect-ratio:16/9; align-self:start; }
        .arcade-tile::before { content: ""; display:block; padding-top:56.25%; /* 16:9 */ }
        .arcade-tile::after { content:""; position:absolute; inset:0; border-radius:12px; pointer-events:none; transition:opacity .18s ease, box-shadow .18s ease; opacity:0; box-shadow:0 10px 30px rgba(0,0,0,0.35) inset; }
        .arcade-tile:focus-visible { outline:2px solid rgba(0,209,255,0.9); outline-offset:4px; transform:translateY(-4px) scale(1.01); }
        .arcade-tile:hover { transform:translateY(-6px) scale(1.02); box-shadow:0 18px 60px rgba(2,6,23,0.6); }
        .arcade-tile:hover::after { opacity:1; box-shadow: inset 0 10px 30px rgba(0,0,0,0.35), 0 0 28px rgba(0,209,255,0.22), 0 0 64px rgba(0,209,255,0.14); }

        .arcade-tile-overlay { position:absolute; inset:0; display:flex; flex-direction:column; justify-content:flex-end; padding:1rem; background:linear-gradient(180deg, rgba(0,0,0,0.06) 35%, rgba(0,0,0,0.42) 100%); color:#fff; transition:background .18s ease, transform .18s ease; }
        .arcade-tile:hover .arcade-tile-overlay { background:linear-gradient(180deg, rgba(0,0,0,0.12) 10%, rgba(0,0,0,0.6) 100%); }

        .arcade-tile-overlay h3 { margin:0 0 0.35rem 0; font-size:1.15rem; font-weight:800; transform:translateY(0); transition:transform .18s ease; }
        .arcade-tile:hover .arcade-tile-overlay h3 { transform:translateY(-4px); }

        .arcade-tile-overlay p { margin:0 0 0.5rem 0; color:rgba(255,255,255,0.95); font-size:0.9rem; opacity:0.95; transition:opacity .18s ease, transform .18s ease; }
        .arcade-tile:hover .arcade-tile-overlay p { transform:translateY(-2px); opacity:1; }

        .arcade-tile .play-btn { display:inline-block; background:#00d1ff;color:#021122;padding:0.5rem 0.65rem;border-radius:8px;font-weight:800;text-decoration:none; transition:transform .15s ease, box-shadow .15s ease; box-shadow:0 6px 18px rgba(0,209,255,0.12); }
        .arcade-tile:hover .play-btn { transform:translateY(-3px) scale(1.02); box-shadow:0 12px 34px rgba(0,209,255,0.22); }

        @media (min-width:768px){ .arcade-hero h1 { font-size:3rem; } }
    </style>

    <section class="arcade-hero">
        <div class="container">
            <p class="arcade-badge">ARCADE</p>
            <h1>Retro Arcade — Play &amp; Collect</h1>
            <p class="arcade-sub">Fast, free, and nostalgic mini-games. Browse the collection below and launch from the store.</p>
        </div>
    </section>

    {{-- Primary games grid: prefer $products (DB) then fallback to config $games --}}
    <section class="arcade-grid">
        <div style="grid-column:1/-1; padding:0 1rem 0 1rem;">
            <form method="GET" action="{{ route('games.index') }}" class="games-search-form">
                <input name="q" class="games-search-input" value="{{ old('q', $q ?? request('q')) }}" placeholder="Search games by name, description or code..." aria-label="Search games">
                @if(!empty($q))
                    <a href="{{ route('games.index') }}" class="games-clear-btn">Clear</a>
                @endif
            </form>
            @if(isset($products) )
                <p id="games-results-count" style="color:rgba(255,255,255,0.8);margin-top:0.5rem;font-size:0.95rem;">Showing {{ $products->count() }} result{{ $products->count() === 1 ? '' : 's' }}@if(!empty($q)) for "{{ e($q) }}"@endif</p>
            @endif
        </div>
        <div id="games-tiles" style="grid-column:1/-1;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;align-items:start;">
        @if(!empty($products ?? []) && count($products))
            @foreach($products as $product)
                @php
                    // determine image source (prefer attached media)
                    $tileImg = null;
                    if (! empty($product->media) && ! empty($product->media->path)) {
                        try { $tileImg = \Illuminate\Support\Facades\Storage::disk($product->media->disk)->url($product->media->path); } catch (\Exception $e) { $tileImg = null; }
                    }
                    if (! $tileImg) {
                        $desc = strtolower($product->description ?? '');
                        if (\Illuminate\Support\Str::contains($desc, ['space','asteroid','rocket','satellite','cosmic','galaxy'])) { $file = 'rocket.svg'; }
                        elseif (\Illuminate\Support\Str::contains($desc, ['puzz','puzzle','match','brain'])) { $file = 'puzzle.svg'; }
                        elseif (\Illuminate\Support\Str::contains($desc, ['race','racer','racing','car','drive'])) { $file = 'racer.svg'; }
                        else { $file = 'joystick.svg'; }
                        $m = \App\Models\Media::where('filename', $file)->latest()->first();
                        $tileImg = $m ? \Illuminate\Support\Facades\Storage::disk($m->disk)->url($m->path) : asset('assets/games/' . $file);
                    }
                    $href = $product->url ?? url('/shop/' . ($product->product_code ?? ''));
                    $isExternal = ! empty($product->url);
                @endphp
                <a href="{{ $href }}" @if($isExternal) target="_blank" rel="noopener" @endif class="arcade-tile" style="background-image:url('{{ $tileImg }}');">
                    <div class="arcade-tile-overlay">
                        <h3>{{ $product->name }}</h3>
                        @if(!empty($product->description))
                            <p>{{ \Illuminate\Support\Str::limit($product->description, 100) }}</p>
                        @endif
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <span class="play-btn">{{ $product->name }}</span>
                            @auth
                                @php $fav = $product->isFavoritedBy(auth()->user()); @endphp
                                <button type="button" class="link button-reset favorite-btn" data-type="product" data-id="{{ $product->id }}" aria-pressed="{{ $fav ? 'true' : 'false' }}" title="Toggle favourite">
                                    <svg class="icon" width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M12 21s-7-4.35-9.5-7.02C-1 9.92 4 4 8.5 7.5 12 10.5 12 10.5 12 10.5s0 0 3.5-3c4.5-3.5 9.5 2.42 5.5 6.48C19 16.65 12 21 12 21z" />
                                    </svg>
                                </button>
                            @endauth
                        </div>
                    </div>
                </a>
            @endforeach
        @elseif(!empty($games ?? []) && count($games))
            @foreach($games as $game)
                <div class="arcade-card">
                    @php
                        $gdesc = '';
                        if (is_array($game)) { $gdesc = strtolower($game['description'] ?? ''); }
                        elseif (is_object($game)) { $gdesc = strtolower($game->description ?? ''); }
                        if (\Illuminate\Support\Str::contains($gdesc, ['space','asteroid','rocket','satellite','cosmic','galaxy'])) {
                            $gfile = 'rocket.svg';
                        } elseif (\Illuminate\Support\Str::contains($gdesc, ['puzz','puzzle','match','brain'])) {
                            $gfile = 'puzzle.svg';
                        } elseif (\Illuminate\Support\Str::contains($gdesc, ['race','racer','racing','car','drive'])) {
                            $gfile = 'racer.svg';
                        } else {
                            $gfile = 'joystick.svg';
                        }
                        $gmedia = \App\Models\Media::where('filename', $gfile)->latest()->first();
                        if ($gmedia) {
                            $gicon = \Illuminate\Support\Facades\Storage::url($gmedia->path);
                        } else {
                            $gicon = asset('assets/games/' . $gfile);
                        }
                    @endphp
                    <img src="{{ $gicon }}" alt="{{ $game['title'] ?? $game['name'] }}" style="width:64px;height:64px;display:block;margin-bottom:0.5rem;">
                    <h3>{{ $game['title'] ?? $game['name'] }}</h3>
                    @if(!empty($game['description']))
                        <p class="lead">{{ \Illuminate\Support\Str::limit($game['description'], 120) }}</p>
                    @endif
                    <div>
                        @php
                            $link = '#';
                            $isExternal = false;
                            if (is_object($game) && !empty($game->url)) {
                                $link = $game->url;
                                $isExternal = true;
                            } elseif (is_array($game) && !empty($game['url'])) {
                                $link = $game['url'];
                                $isExternal = true;
                            } elseif (is_array($game) && !empty($game['product_code'])) {
                                $link = url('/shop/' . $game['product_code']);
                            }
                        @endphp
                        <a class="play-btn" href="{{ $link }}" @if($isExternal) target="_blank" rel="noopener" @endif>{{ $game['title'] ?? $game['name'] }}</a>
                    </div>
                </div>
            @endforeach
        @else
            <div class="arcade-card">
                <h3>No games found</h3>
                <p class="lead">There are no games available right now. Check back soon or add games in the admin panel.</p>
            </div>
        @endif
        </div>

        <script>
            (function(){
                const input = document.querySelector('input[name="q"]');
                if (!input) return;
                const tiles = document.getElementById('games-tiles');
                const resultsCount = document.getElementById('games-results-count');
                let debounceTimer = null;

                function renderTile(p){
                    const a = document.createElement('a');
                    a.className = 'arcade-tile';
                    a.href = p.url || '#';
                    if (p.isExternal) { a.setAttribute('target','_blank'); a.setAttribute('rel','noopener'); }
                    a.style.backgroundImage = `url('${p.thumbnail}')`;

                    const overlay = document.createElement('div');
                    overlay.className = 'arcade-tile-overlay';
                    const h3 = document.createElement('h3'); h3.textContent = p.name || 'Untitled';
                    overlay.appendChild(h3);
                    if (p.description) {
                        const ptag = document.createElement('p'); ptag.textContent = p.description.substring(0,100); overlay.appendChild(ptag);
                    }
                    const btn = document.createElement('span'); btn.className = 'play-btn'; btn.textContent = p.name || 'View'; overlay.appendChild(btn);
                    a.appendChild(overlay);
                    return a;
                }

                function renderResults(list){
                    tiles.innerHTML = '';
                    if (!list || !list.length) {
                        const empty = document.createElement('div'); empty.className = 'arcade-card';
                        empty.innerHTML = '<h3>No games found</h3><p class="lead">Try a different search or check back later.</p>';
                        tiles.appendChild(empty);
                        if (resultsCount) resultsCount.textContent = 'Showing 0 results';
                        return;
                    }
                    list.forEach(item => tiles.appendChild(renderTile(item)));
                    if (resultsCount) resultsCount.textContent = `Showing ${list.length} result${list.length===1? '':'s'}${input.value? ' for "'+input.value+'"':''}`;
                }

                async function doSearch(){
                    const q = input.value.trim();
                    const url = `{{ route('games.index') }}` + (q ? '?q=' + encodeURIComponent(q) : '');
                    try {
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) { return; }
                        const data = await res.json();
                        renderResults(data || []);
                    } catch (e) {
                        console.error('Live search error', e);
                    }
                }

                input.addEventListener('input', function(){
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(doSearch, 300);
                });
            })();
        </script>
            <script>
                (function () {
                    async function toggleFav(btn) {
                        const type = btn.dataset.type;
                        const id = btn.dataset.id;
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]')?.value;
                        try {
                            const res = await fetch('{{ route('favorites.toggle') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ type: type, id: id })
                            });
                            if (!res.ok) throw new Error('Request failed');
                            const json = await res.json();
                            btn.setAttribute('aria-pressed', json.favorited ? 'true' : 'false');
                            // visual handled via CSS on [aria-pressed]
                        } catch (e) {
                            console.error('Favourite toggle error', e);
                        }
                    }

                    document.addEventListener('click', function (e) {
                        const btn = e.target.closest && e.target.closest('.favorite-btn');
                        if (!btn) return;
                        e.preventDefault();
                        toggleFav(btn);
                    });
                })();
            </script>
    </section>

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-34RWEG01MV"></script>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-34RWEG01MV');
        </script>



@include('partials.footer')

@endsection
