<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Product;
use App\Models\Favourite;
use App\Models\MiniCivState;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $licenseEnabled = (bool) config('license.enabled');
        $purchaseEnabled = $licenseEnabled && (bool) config('license.purchase_enabled');

        $favorites = collect();
        if ($user = Auth::user() && config('games.enabled')) {
            $favorites = Favourite::with('favoritable.media')
                ->where('user_id', $user->id)
                ->where('favoritable_type', Product::class)
                ->get()
                ->map(fn($f) => $f->favoritable)
                ->filter();
        }

        return view('dashboard', [
            'user' => Auth::user(),
            'minicivStates' => Auth::check() ? MiniCivState::where('user_id', Auth::id())->orderBy('updated_at', 'desc')->get() : collect(),
            'licenses' => $licenseEnabled
                ? License::with(['product', 'domains'])->where('user_id', Auth::id())->orderBy('expires_at')->get()
                : collect(),
            'products' => $purchaseEnabled ? Product::orderBy('name')->get() : collect(),
            'paypalClientId' => config('paypal.client_id'),
            'paypalCurrency' => config('paypal.currency', 'USD'),
            'stripePublicKey' => config('stripe.public_key'),
            'stripeCurrency' => config('stripe.currency', 'USD'),
            'paypalEnabled' => $purchaseEnabled && (bool) (config('payment.providers.paypal.enabled') && config('paypal.client_id')),
            'stripeEnabled' => $purchaseEnabled && (bool) (config('payment.providers.stripe.enabled') && config('stripe.public_key') && config('stripe.secret')),
            'favorites' => $favorites,
        ]);
    }
}
