@extends('layouts.app')

@section('title', 'Dashboard · GD Login')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Session active</p>
        <h1>Welcome, {{ $user->name }}!</h1>
        <p class="lead">You are signed in with Laravel sessions. Manage your account below.</p>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Log out</button>
    </form>
</header>

@if (session('status'))
    <div class="banner success">
        {{ session('status') }}
    </div>
@endif

@if ($user->is_admin)
    <section class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div>
                <p class="eyebrow" style="margin-bottom:0.35rem;">Admin tools</p>
                <h2 style="margin:0;">Control users & licenses</h2>
            </div>
        </div>
        <div style="margin-top:1rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.5rem;">
            <a class="link" href="{{ route('admin.users.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Manage users</a>
            <a class="link" href="{{ route('admin.products.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Manage products</a>
            @if(config('license.enabled') && config('license.admin_enabled'))
                <a class="link" href="{{ route('admin.licenses.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Manage licenses</a>
            @endif
            @if (config('admin.servers_enabled'))
                <a class="link" href="{{ route('admin.servers.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Manage servers</a>
            @endif
            <a class="link" href="{{ route('admin.event-logs.index') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Logs</a>
            @if(config('license.enabled') && config('license.public_validation'))
                <a class="link" href="{{ route('admin.tools.license-validation') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">License validation</a>
            @endif
            <a class="link" href="{{ route('email.test') }}" style="display:block;text-align:center;padding:0.65rem 0.9rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);font-weight:600;">Send test email</a>
        </div>
    </section>
@endif

<section class="card">
    <h2>Account details</h2>
    <dl class="details">
        <div>
            <dt>Name</dt>
            <dd>{{ $user->name }}</dd>
        </div>
        <div>
            <dt>Email</dt>
            <dd>{{ $user->email }}</dd>
        </div>
        <div>
            <dt>Admin contact</dt>
            <dd>{{ $user->admin_email ?? '—' }}</dd>
        </div>
        <div>
            <dt>Member since</dt>
            <dd>{{ $user->created_at->format('F j, Y') }}</dd>
        </div>
    </dl>
</section>

@if(isset($minicivStates) && $minicivStates->isNotEmpty())
<section class="card">
    <h2>Saved Civilisations</h2>
    <p style="color:var(--muted);margin-top:0">Your saved MiniCiv civilisations.</p>
    <div style="margin-top:1rem;display:grid;gap:0.75rem;">
        @foreach($minicivStates as $state)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem;border-radius:0.75rem;background:var(--bg);">
                <div>
                    <strong>Saved</strong>
                    <div style="font-size:0.9rem;color:var(--muted);">{{ $state->updated_at->diffForHumans() }} · [Turn {{ data_get($state->state, 'turn', '—') }}]</div>
                    <div style="margin-top:0.35rem;font-size:0.95rem;color:var(--muted);">
                        Pop: {{ data_get($state->state, 'population', 0) }} · Food: {{ data_get($state->state, 'food', 0) }} · Wood: {{ data_get($state->state, 'wood', 0) }} · Stone: {{ data_get($state->state, 'stone', 0) }}
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <button type="button" class="link" data-action="restore" data-state='@json($state->state)'>Restore</button>
                    <form method="POST" action="{{ route('miniciv.delete') }}" style="margin:0;">
                        @csrf
                        <input type="hidden" name="id" value="{{ $state->id }}">
                        <button type="submit" class="link" style="color:var(--error);">Delete</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

@if (config('license.enabled') && config('license.purchase_enabled'))
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div>
            <p class="eyebrow" style="margin-bottom:0.35rem;">Purchase access</p>
            <h2 style="margin:0;">Add another license</h2>
        </div>
        <span style="font-size:0.9rem;color:var(--muted);">Licenses assign directly to your account</span>
    </div>

    @if ($errors->any())
        <div class="banner error" style="margin-top:1rem;">
            <ul style="margin:0;padding-left:1.25rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($products->isEmpty())
        <p style="margin-top:1.25rem;color:var(--muted);">No products are available for purchase right now. Please check back later.</p>
    @else
        @if ($paypalEnabled)
        <form method="POST" action="{{ route('licenses.store') }}" style="display:grid;gap:1rem;margin-top:1.5rem;" id="purchase-form">
            @csrf
            <label>
                <span>Product</span>
                <select name="product_id" id="product-select" required style="width:100%;border:1px solid rgba(15,23,42,0.15);border-radius:0.9rem;padding:0.85rem 1rem;font-size:1rem;">
                    <option value="" disabled {{ old('product_id') ? '' : 'selected' }}>Choose a product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-price="{{ number_format($product->price, 2, '.', '') }}"
                                data-duration="{{ $product->duration_months }}"
                                {{ (int) old('product_id') === $product->id ? 'selected' : '' }}>
                            {{ $product->name }} ({{ $product->product_code }}) · ${{ number_format($product->price, 2) }}/seat
                        </option>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="seats_total" id="seats-input" value="1">
            <label>
                <span>Primary domain (optional)</span>
                <input type="text" name="domain" placeholder="acme.com" value="{{ old('domain') }}">
            </label>
            <div style="padding:0.75rem 1rem;background:var(--bg);border-radius:0.75rem;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
                <span>
                    Estimated total
                    <small style="display:block;font-weight:400;color:var(--muted);">Renews every <span id="duration-text">0</span> months</small>
                </span>
                <span id="purchase-total">$0.00</span>
            </div>
            <input type="hidden" name="paypal_order_id" id="paypal-order-id">
            <p style="margin:0;color:var(--muted);font-size:0.95rem;">Checkout is powered by PayPal. Each purchase provides a single-seat license tied to your account.</p>
            <div style="padding:0.5rem;border:1px solid rgba(15,23,42,0.08);border-radius:0.85rem;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,0.06);">
                <div id="paypal-buttons-dashboard"></div>
                <p style="margin:0.35rem 0 0;color:var(--muted);font-size:0.9rem;">Pay with PayPal</p>
            </div>
            <p id="paypal-errors-dashboard" style="display:none;color:var(--error);font-weight:600;"></p>
            @error('payment')
                <p style="color:var(--error);font-weight:600;">{{ $message }}</p>
            @enderror
            @if (! $paypalClientId)
                <p style="color:var(--error);font-weight:600;">Set PAYPAL_CLIENT_ID and PAYPAL_SECRET in your environment file to enable checkout.</p>
            @endif
        </form>
            @endif

            @if ($stripeEnabled)
        <div style="margin-top:1.5rem;padding:1rem;border:1px solid rgba(15,23,42,0.08);border-radius:0.9rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.08);">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                <div>
                    <p class="eyebrow" style="margin-bottom:0.35rem;">Card checkout</p>
                    <h3 style="margin:0;">Pay with Stripe</h3>
                </div>
                <span style="font-size:0.9rem;color:var(--muted);">Secured by Stripe Elements</span>
            </div>

            <form method="POST" action="{{ route('stripe.complete') }}" id="stripe-purchase-form" style="display:grid;gap:0.85rem;margin-top:1rem;">
                @csrf
                <input type="hidden" name="product_id" id="stripe-product-id" value="">
                <input type="hidden" name="payment_intent_id" id="stripe-payment-intent-id">
                <label>
                    <span>Product</span>
                    <select id="stripe-product-select" required style="width:100%;border:1px solid rgba(15,23,42,0.15);border-radius:0.9rem;padding:0.85rem 1rem;font-size:1rem;">
                        <option value="" disabled selected>Choose a product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                    data-price="{{ number_format($product->price, 2, '.', '') }}"
                                    data-duration="{{ $product->duration_months }}">
                                {{ $product->name }} ({{ $product->product_code }}) · ${{ number_format($product->price, 2) }}/seat
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Primary domain (optional)</span>
                    <input type="text" name="domain" id="stripe-domain" placeholder="acme.com" value="{{ old('domain') }}">
                </label>
                <div style="padding:0.75rem 1rem;background:var(--bg);border-radius:0.75rem;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
                    <span>
                        Estimated total
                        <small style="display:block;font-weight:400;color:var(--muted);">Renews every <span id="stripe-duration-text">0</span> months</small>
                    </span>
                    <span id="stripe-total">$0.00</span>
                </div>
                <div id="stripe-card-element" style="padding:0.75rem 1rem;border:1px solid rgba(15,23,42,0.12);border-radius:0.75rem;background:#fff;"></div>
                <button type="submit" id="stripe-submit" style="padding:0.75rem 1rem;border:none;border-radius:0.75rem;background:var(--primary);color:#fff;font-weight:700;cursor:pointer;">Pay with card</button>
                <p id="stripe-errors" style="display:none;color:var(--error);font-weight:600;"></p>
                @if (! config('stripe.public_key'))
                    <p style="color:var(--error);font-weight:600;">Set STRIPE_PUBLIC_KEY and STRIPE_SECRET in your environment file to enable Stripe checkout.</p>
                @endif
            </form>
        </div>
        @endif
    @endif
    </section>

    @endif

    @if(config('games.enabled'))
    <section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div>
            <p class="eyebrow" style="margin-bottom:0.35rem;">Favorites</p>
            <h2 style="margin:0;">Favourite games</h2>
        </div>
        <span style="font-size:0.9rem;color:var(--muted);">Quick access to your saved games</span>
    </div>

    <div style="margin-top:1rem;">
        <div id="dashboard-games-tiles" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;align-items:start;">
            @if(isset($favorites) && $favorites->count())
                @foreach($favorites as $fav)
                    @php
                        $product = $fav;
                        $thumb = null;
                        if (! empty($product->media) && ! empty($product->media->path)) {
                            try { $thumb = \Illuminate\Support\Facades\Storage::disk($product->media->disk)->url($product->media->path); } catch (\Exception $e) { $thumb = null; }
                        }
                        $thumb = $thumb ?: asset('assets/games/joystick.svg');
                        $href = $product->url ?? url('/shop/' . ($product->product_code ?? ''));
                        $isExternal = ! empty($product->url);
                    @endphp
                    <a href="{{ $href }}" @if($isExternal) target="_blank" rel="noopener" @endif class="arcade-tile" style="background-image:url('{{ $thumb }}');">
                        <div class="arcade-tile-overlay">
                            <h3>{{ $product->name }}</h3>
                            @if(!empty($product->description))
                                <p>{{ \Illuminate\Support\Str::limit($product->description, 100) }}</p>
                            @endif
                            <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.35rem;">
                                <span class="play-btn">Play</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            @else
                <div style="color:var(--muted);">You haven't favourited any games yet.</div>
            @endif
        </div>
    </div>
</section>

@endif

@if (config('license.enabled'))
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div>
            <p class="eyebrow" style="margin-bottom:0.35rem;">Your licenses</p>
            <h2 style="margin:0;">Assigned entitlements</h2>
        </div>
        <span style="font-weight:600;color:var(--primary);">{{ $licenses->count() }} active</span>
    </div>

    <div style="overflow-x:auto;margin-top:1.5rem;">
        <table style="width:100%;border-collapse:separate;border-spacing:0 0.5rem;">
            <thead>
                <tr style="text-align:left;color:var(--muted);font-size:0.85rem;text-transform:uppercase;letter-spacing:0.1em;">
                    <th style="padding:0 0.75rem;">License</th>
                    <th style="padding:0 0.75rem;">Domain</th>
                    <th style="padding:0 0.75rem;">Identifier</th>
                    <th style="padding:0 0.75rem;">Expires</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($licenses as $license)
                    <tr style="background:var(--bg);">
                        <td style="padding:0.9rem 0.75rem;font-weight:600;color:var(--text);">
                            <a href="{{ $license->inspect_uri }}" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="vertical-align:middle;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656m-1.414-1.414a2 2 0 010 2.828m-2.828-2.828a6 6 0 018.485 8.485m-1.414-1.414a4 4 0 01-5.656 0m1.414-1.414a2 2 0 01-2.828 0" />
                                </svg>
                                <span>License #{{ $license->id }}</span>
                                <span style="font-size:0.8rem;color:var(--muted);">View details →</span>
                            </a>
                            <a href="{{ $license->public_validator_uri }}" target="_blank" rel="noopener" style="display:inline-flex;margin-top:0.35rem;font-size:0.8rem;color:var(--primary);font-weight:600;align-items:center;gap:0.3rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="vertical-align:middle;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3v4a1 1 0 001 1h4m-5 8v4a1 1 0 001 1h4m-5-8V3a1 1 0 00-1-1H5a1 1 0 00-1 1v16a1 1 0 001 1h8a1 1 0 001-1v-4m-5-8h4" />
                                </svg>
                                External validator ↗
                            </a>
                        </td>
                        <td style="padding:0.9rem 0.75rem;">
                            @php
                                $domains = $license->domains->pluck('domain')->filter()->take(2);
                            @endphp
                            {{ $domains->isNotEmpty() ? $domains->join(', ') : '—' }}
                            @if ($license->domains->count() > 2)
                                <span style="display:block;font-size:0.75rem;color:var(--muted);">+{{ $license->domains->count() - 2 }} more</span>
                            @endif
                        </td>
                        <td style="padding:0.9rem 0.75rem;font-family:monospace;">
                            {{ $license->identifier ?? '—' }}
                        </td>
                        <td style="padding:0.9rem 0.75rem;">
                            {{ $license->expires_at ? $license->expires_at->format('M j, Y') : 'No expiry' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding:1rem 0.75rem;text-align:center;color:var(--muted);">
                            No licenses have been assigned to you yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection

@push('scripts')
@if ($paypalEnabled && $paypalClientId)
    <script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency={{ $paypalCurrency ?? 'USD' }}" data-sdk-integration-source="button-factory"></script>
@endif
@if ($stripeEnabled && config('stripe.public_key'))
    <script src="https://js.stripe.com/v3/"></script>
@endif
<script>
(function () {
    const select = document.getElementById('product-select');
    const seats = document.getElementById('seats-input');
    const total = document.getElementById('purchase-total');
    const durationText = document.getElementById('duration-text');
    const form = document.getElementById('purchase-form');
    const domainInput = form ? form.querySelector('input[name="domain"]') : null;
    const orderInput = document.getElementById('paypal-order-id');
    const paypalErrors = document.getElementById('paypal-errors-dashboard');
    const paypalEnabled = {{ $paypalEnabled && $paypalClientId ? 'true' : 'false' }};

    const clearOrder = () => {
        if (orderInput) {
            orderInput.value = '';
        }
        if (form) {
            form.dataset.paypalReady = 'false';
        }
    };

    const updateTotal = () => {
        if (!select || !total || !durationText) {
            return;
        }

        const price = parseFloat(select.selectedOptions[0]?.dataset.price || '0');
        const duration = parseInt(select.selectedOptions[0]?.dataset.duration || '0', 10);
        const amount = price;
        total.textContent = amount > 0 ? `$${amount.toFixed(2)}` : '$0.00';
        durationText.textContent = duration > 0 ? duration : '—';
    };

    if (select) {
        select.addEventListener('change', () => {
            clearOrder();
            updateTotal();
        });
    }

    updateTotal();

    const showError = (message) => {
        if (!paypalErrors) {
            return;
        }

        paypalErrors.textContent = message;
        paypalErrors.style.display = 'block';
    };

    if (form) {
        form.dataset.paypalReady = 'false';
        form.addEventListener('submit', (event) => {
            if (form.dataset.paypalReady !== 'true') {
                event.preventDefault();
            }
        });
    }

    const renderButtons = () => {
        const paypalContainer = document.getElementById('paypal-buttons-dashboard');

        if (!paypalEnabled || !window.paypal) {
            if (paypalEnabled) {
                showError('Unable to load the PayPal SDK. Verify PAYPAL_CLIENT_ID.');
            }
            return;
        }

        const options = {
            style: {
                layout: 'horizontal',
                label: 'pay',
                color: 'gold',
                shape: 'rect',
            },
            createOrder: async () => {
                clearOrder();
                if (paypalErrors) {
                    paypalErrors.style.display = 'none';
                }

                if (!select || !select.value) {
                    throw new Error('Select a product before checking out.');
                }

                const payload = {
                    product_id: select.value,
                    seats_total: 1,
                    domain: domainInput ? domainInput.value : null,
                };

                const response = await fetch('{{ route('paypal.orders.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Unable to create a PayPal order.');
                }

                if (orderInput) {
                    orderInput.value = data.order_id;
                }

                return data.order_id;
            },
            onApprove: (data) => {
                if (!form) {
                    return;
                }

                if (orderInput) {
                    orderInput.value = data.orderID;
                }

                if (paypalErrors) {
                    paypalErrors.style.display = 'none';
                }
                form.dataset.paypalReady = 'true';
                form.submit();
            },
            onCancel: () => {
                showError('Checkout was cancelled.');
                clearOrder();
            },
            onError: (err) => {
                showError(err?.message || 'Payment reported an unexpected error.');
                clearOrder();
            },
        };

        if (paypalContainer) {
            window.paypal.Buttons(options).render('#paypal-buttons-dashboard');
        }
    };

    renderButtons();
})();
</script>

@if ($stripeEnabled && config('stripe.public_key'))
<script>
(function () {
    const stripeKey = '{{ config('stripe.public_key') }}';
    const stripe = Stripe(stripeKey);
    const elements = stripe.elements();
    const card = elements.create('card');
    const form = document.getElementById('stripe-purchase-form');
    const select = document.getElementById('stripe-product-select');
    const total = document.getElementById('stripe-total');
    const durationText = document.getElementById('stripe-duration-text');
    const productIdInput = document.getElementById('stripe-product-id');
    const domainInput = document.getElementById('stripe-domain');
    const intentInput = document.getElementById('stripe-payment-intent-id');
    const errorEl = document.getElementById('stripe-errors');
    const submitBtn = document.getElementById('stripe-submit');

    card.mount('#stripe-card-element');

    const showError = (message) => {
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
    };

    const clearError = () => {
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.style.display = 'none';
        }
    };

    const updateTotal = () => {
        if (!select || !total || !durationText) {
            return;
        }
        const price = parseFloat(select.selectedOptions[0]?.dataset.price || '0');
        const duration = parseInt(select.selectedOptions[0]?.dataset.duration || '0', 10);
        total.textContent = price > 0 ? `$${price.toFixed(2)}` : '$0.00';
        durationText.textContent = duration > 0 ? duration : '—';
        if (productIdInput) {
            productIdInput.value = select.value || '';
        }
    };

    if (select) {
        select.addEventListener('change', updateTotal);
    }
    updateTotal();

    const setLoading = (loading) => {
        if (!submitBtn) return;
        submitBtn.disabled = loading;
        submitBtn.textContent = loading ? 'Processing…' : 'Pay with card';
    };

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError();

            if (!select || !select.value) {
                showError('Select a product before checking out.');
                return;
            }

            setLoading(true);

            try {
                const payload = {
                    product_id: select.value,
                    domain: domainInput ? domainInput.value : null,
                };

                const response = await fetch('{{ route('stripe.intents.create') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Unable to create a Stripe payment.');
                }

                const clientSecret = data.client_secret;
                const intentId = data.payment_intent_id;

                const result = await stripe.confirmCardPayment(clientSecret, {
                    payment_method: {
                        card,
                    },
                });

                if (result.error) {
                    throw new Error(result.error.message || 'Card payment failed.');
                }

                if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    if (intentInput) {
                        intentInput.value = intentId;
                    }
                    form.submit();
                } else {
                    throw new Error('Stripe did not complete the payment.');
                }
            } catch (err) {
                showError(err && err.message ? err.message : 'Card payment failed.');
            } finally {
                setLoading(false);
            }
        });
    }
})();
</script>
@endif
@endpush
