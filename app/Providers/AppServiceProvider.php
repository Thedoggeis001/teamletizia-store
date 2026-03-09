<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ? LOGIN: 5 tentativi/min per email + IP
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(strtolower($email).'|'.$request->ip());
        });

        // ? CHECKOUT: 10 richieste/min per utente (o IP se guest)
        RateLimiter::for('checkout', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by('checkout|'.$key);
        });

        // ? API GENERALE: 60 richieste/min per utente (o IP)
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinute(60)->by('api|'.$key);
        });
    }
}
