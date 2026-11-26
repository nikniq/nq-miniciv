<?php

namespace App\Providers;

use App\Auth\NikniqProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Socialite::extend('nikniq', fn ($app) => Socialite::buildProvider(NikniqProvider::class, config('services.nikniq'))
            ->scopes(config('services.nikniq.scopes', [])));
    }
}
