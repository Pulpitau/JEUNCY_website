<?php

namespace App\Providers;

use App\Auth\JwtGuard;
use App\Services\JwtService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        Auth::extend('jwt', fn ($app, $name, array $config) => new JwtGuard(
            $app['request'],
            $app->make(JwtService::class),
        ));

        // Limite generale sur le groupe API (audit de securite du
        // 2026-10-01, section D1) : sans elle, aucune route n'avait de
        // plafond sauf les trois deja ciblees (login, mot de passe oublie,
        // contact). Par compte authentifie quand il y en a un, sinon par IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
