<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('direct-messages', function (Request $request) {
            return Limit::perMinute(10)
                ->by('direct-message:'.$request->user()?->id)
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'Limite de mensagens atingido. Aguarde um instante antes de tentar novamente.',
                ], 429, $headers));
        });

        RateLimiter::for('direct-message-likes', function (Request $request) {
            return Limit::perMinute(10)
                ->by('direct-like:'.$request->user()?->id)
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'Muitas curtidas em pouco tempo. Aguarde um instante.',
                ], 429, $headers));
        });
    }
}
