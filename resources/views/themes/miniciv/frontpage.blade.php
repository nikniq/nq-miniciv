@extends('layouts.app')

@section('title', 'MiniCiv')

@section('content')
@push('head')
    <link rel="icon" type="image/svg+xml" href="/favicon-games.svg">
    <link rel="shortcut icon" href="/favicon-games.svg">
@endpush
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
            <p class="arcade-badge">MINICIV</p>
            <h1>MiniCiv — Build Your Tiny Civilization</h1>
            <p class="arcade-sub">Micro strategy — expand your village, defend your walls, and grow from a single settlement.</p>
        </div>
    </section>

    <section class="arcade-grid">
        <div style="grid-column:1/-1; padding:0 1rem 0 1rem;">
            <form method="GET" action="{{ route('games.index') }}" class="games-search-form">
                <input name="q" class="games-search-input" value="{{ old('q', $q ?? request('q')) }}" placeholder="Search scenarios, buildings or civ names..." aria-label="Search MiniCiv">
                @if(!empty($q))
                    <a href="{{ route('games.index') }}" class="games-clear-btn">Clear</a>
                @endif
            </form>
        </div>
        <div id="games-tiles" style="grid-column:1/-1;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;align-items:start;">
            <div class="arcade-card">
                <h3>MiniCiv Starter</h3>
                <p class="lead">A tiny scenario to get your civilisation going — build houses, farms and walls to survive the first ten turns.</p>
                <a class="play-btn" href="{{ route('miniciv.play') }}">Play →</a>
            </div>
            <div class="arcade-card">
                <h3>Skirmish Mode</h3>
                <p class="lead">Quick multiplayer skirmishes against AI or friends (coming soon).</p>
                <a class="play-btn" href="{{ route('miniciv.play') }}">Preview →</a>
            </div>
            <div class="arcade-card">
                <h3>World Builder</h3>
                <p class="lead">Customize maps and civilizations, export and share with the community.</p>
                <a class="play-btn" href="{{ route('miniciv.play') }}">Open →</a>
            </div>
        </div>
    </section>

@include('partials.footer')

@endsection
