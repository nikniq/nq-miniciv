<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('site.name') }}</title>
    <style>
    :root{
            --muted: #6b7280;
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --error: #dc2626;
            --success: #16a34a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(37, 99, 235, 0.18), transparent 45%),
                radial-gradient(circle at 80% 0%, rgba(22, 163, 74, 0.15), transparent 40%),
                var(--bg);
            color: var(--text);
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 4rem 1.5rem 2.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .hero h1 {
            margin: 0.4rem 0;
            font-size: clamp(2.4rem, 4vw, 3.6rem);
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-size: 0.78rem;
            color: var(--muted);
        }
        .lead { color: var(--muted); }
        .card {
            background: var(--panel);
            border-radius: 1.2rem;
            padding: 2rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }
        .card.alt {
            background: var(--panel-alt);
            color: #fff;
            box-shadow: none;
        }
        .site-nav {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(12px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }
        .site-nav a {
            text-decoration: none;
            font-weight: 600;
            color: var(--text);
        }
        .site-nav a.brand {
            font-size: 1.1rem;
            color: var(--primary);
        }
        .site-nav .nav-links {
            display: flex;
            gap: 0.75rem;
            margin-left: auto;
            flex-wrap: wrap;
        }
            .site-nav .nav-links a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.55rem 0.9rem;
                border: 1px solid rgba(15, 23, 42, 0.12);
                border-radius: 0.9rem;
                background: #fff;
                box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
                transition: transform 120ms ease, box-shadow 120ms ease;
            }
            .site-nav .nav-links a.nav-active {
                background: var(--primary);
                color: #fff;
                border-color: var(--primary-dark);
                box-shadow: 0 10px 24px rgba(37, 99, 235, 0.25);
            }
            .site-nav .nav-links a:hover {
                transform: translateY(-1px);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            }
        .nav-toggle {
            display: none;
            margin-left: auto;
            border-radius: 0.9rem;
            padding: 0.55rem 0.9rem;
            background: #0f172a;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18);
        }
        @media (max-width: 720px) {
            .site-nav {
                flex-wrap: wrap;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .nav-toggle {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                font-weight: 700;
            }
            .site-nav .nav-links {
                width: 100%;
                display: none;
                flex-direction: column;
                margin-left: 0;
            }
            .site-nav .nav-links[data-open="true"] {
                display: flex;
            }
            .site-nav .nav-links a {
                width: 100%;
                justify-content: flex-start;
            }
        }
        form { display: flex; flex-direction: column; gap: 1rem; }
        label span { display: block; margin-bottom: 0.35rem; font-size: 0.9rem; }
        input {
            width: 100%;
            border: 1px solid rgba(15, 23, 42, 0.15);
            border-radius: 0.9rem;
            padding: 0.85rem 1rem;
            font-size: 1rem;
        }
        .card.alt input {
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        button {
            border: none;
            border-radius: 999px;
            padding: 0.9rem 1.2rem;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.35);
        }
        .link.button-reset {
            background: none;
            border: none;
            padding: 0;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
        }
        .link.button-reset:hover { text-decoration: underline; }
        .favorite-btn {
            background: none;
            border: none;
            padding: 0.15rem 0.35rem;
            font-size: 1.05rem;
            color: #ff3b81;
            cursor: pointer;
            border-radius: 6px;
        }
        .favorite-btn[aria-pressed="true"] { color: #ff3860; font-weight:700; }
        .favorite-btn .icon { width:18px; height:18px; display:inline-block; vertical-align:middle; }
        .favorite-btn .icon path { fill: transparent; stroke: #ff3b81; stroke-width:1.4; transition:fill .12s ease, stroke .12s ease; }
        .favorite-btn[aria-pressed="true"] .icon path { fill: #ff3860; stroke: #ff1540; }
        .banner {
            border-radius: 1rem;
            padding: 1rem 1.25rem;
        }
        .banner.success {
            background: rgba(22, 163, 74, 0.12);
            color: var(--success);
        }
        .banner.error {
            background: rgba(220, 38, 38, 0.12);
            color: var(--error);
        }
        .banner.info {
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
        }
        .banner ul { margin: 0; padding-left: 1rem; }
        .status { margin: 0; }
        .link { color: var(--primary); font-weight: 600; text-decoration: none; }
        .link:hover { text-decoration: underline; }
        .stack { display: grid; gap: 1.5rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; }
        .details { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
        .details dt {
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-size: 0.7rem;
            color: rgba(15, 23, 42, 0.65);
        }
        .details dd { margin: 0.25rem 0 0; font-size: 1.1rem; }
        .admin-nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.5rem;
        }
        .admin-nav a {
            display: block;
            text-align: center;
            padding: 0.65rem 0.9rem;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 0.9rem;
            background: #fff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            font-weight: 600;
            text-decoration: none;
            color: var(--text);
            transition: transform 120ms ease, box-shadow 120ms ease;
        }
        .admin-nav a:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); }
        .admin-nav a.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary-dark);
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.25);
        }
        /* Pagination (Laravel paginator default uses Tailwind-like classes) */
        nav[aria-label] ul, nav[aria-label] .pagination { display:flex; gap:0.5rem; list-style:none; padding:0; margin:0; align-items:center; }
        nav[aria-label] a, nav[aria-label] span { display:inline-flex; align-items:center; justify-content:center; padding:0.55rem 0.85rem; border-radius:0.8rem; text-decoration:none; font-weight:700; min-width:44px; }
        nav[aria-label] a { background:#fff; border:1px solid rgba(15,23,42,0.08); color:var(--text); box-shadow:0 6px 16px rgba(15,23,42,0.06); }
        nav[aria-label] a:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(15,23,42,0.09); }
        nav[aria-label] .active span, nav[aria-label] span[aria-current] { background:var(--primary); color:#fff; border-color:var(--primary-dark); box-shadow:0 10px 24px rgba(37,99,235,0.18); }
        @media (max-width:540px) { nav[aria-label] ul { gap:0.25rem; } nav[aria-label] a, nav[aria-label] span { padding:0.45rem 0.6rem; font-size:0.95rem; min-width:36px; } }
        /* Shared arcade tile styles (used by games frontpage and dashboard favorites) */
        .arcade-tile { display:block; position:relative; width:100%; border-radius:12px; background-size:cover; background-position:center; background-repeat:no-repeat; background-color:#000; overflow:hidden; transition:transform .18s cubic-bezier(.2,.9,.2,1), box-shadow .18s cubic-bezier(.2,.9,.2,1); aspect-ratio:16/9; align-self:start; }
        .arcade-tile::before { content: ""; display:block; padding-top:56.25%; }
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
    </style>
        @stack('head')
</head>
<body>
    <div class="page">
        <nav class="site-nav">
            <a href="{{ route('home') }}" class="brand">{{ config('site.name') }}</a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
                <span>Menu</span>
                <span aria-hidden="true">☰</span>
            </button>
            <div class="nav-links" id="primary-nav" data-open="false">
                <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'nav-active' : '' }}">Home</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'nav-active' : '' }}">Dashboard</a>
                    <a href="{{ route('profile.show') }}" class="{{ request()->routeIs('profile.show') ? 'nav-active' : '' }}">Profile</a>
                    @if(config('shop.enabled'))
                        <a href="{{ url('/shop') }}" class="{{ request()->routeIs('shop') || request()->routeIs('shop.products.show') ? 'nav-active' : '' }}">Shop</a>
                    @endif
                        @if(config('apilab.enabled') && Route::has('api.lab'))
                            <a href="{{ route('api.lab') }}" class="{{ request()->routeIs('api.lab') ? 'nav-active' : '' }}">API Lab</a>
                        @endif
                        @if(config('games.enabled') && Route::has('games.index'))
                            <a href="{{ route('games.index') }}" class="{{ request()->routeIs('games.index') ? 'nav-active' : '' }}">Games</a>
                        @endif
                        @if(config('posts.enabled') && Route::has('posts.index'))
                            <a href="{{ route('posts.index') }}" class="{{ request()->routeIs('posts.index') || request()->routeIs('posts.show') ? 'nav-active' : '' }}">Posts</a>
                        @endif
                @else
                    @if(config('shop.enabled'))
                        <a href="{{ url('/shop') }}" class="{{ request()->routeIs('shop') || request()->routeIs('shop.products.show') ? 'nav-active' : '' }}">Shop</a>
                    @endif
                        @if(config('apilab.enabled') && Route::has('api.lab'))
                            <a href="{{ route('api.lab') }}" class="{{ request()->routeIs('api.lab') ? 'nav-active' : '' }}">API Lab</a>
                        @endif
                        @if(config('games.enabled') && Route::has('games.index'))
                            <a href="{{ route('games.index') }}" class="{{ request()->routeIs('games.index') ? 'nav-active' : '' }}">Games</a>
                        @endif
                        @if(config('posts.enabled') && Route::has('posts.index'))
                            <a href="{{ route('posts.index') }}" class="{{ request()->routeIs('posts.index') || request()->routeIs('posts.show') ? 'nav-active' : '' }}">Posts</a>
                        @endif
                    <a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'nav-active' : '' }}">Login</a>
                    <a href="{{ route('register') }}" class="{{ request()->routeIs('register') ? 'nav-active' : '' }}">Register</a>
                @endauth
            </div>
        </nav>

        @if (session('status'))
            <div class="banner success">
                <p class="status">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="banner error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
    <script>
    (function () {
        const toggle = document.querySelector('.nav-toggle');
        const links = document.getElementById('primary-nav');
        if (!toggle || !links) return;

        toggle.addEventListener('click', () => {
            const open = links.dataset.open === 'true';
            links.dataset.open = open ? 'false' : 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
    })();
    </script>
    @stack('scripts')
</body>
</html>
