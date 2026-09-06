<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
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
        if ($this->app->environment('local')) {
            DevCommands::artisan('queue:listen --queue=default,ably,ably-messages,ably-notifications-messages,ably-notifications --tries=3 --timeout=60', 'queue');
            DevCommands::artisan('schedule:work', 'scheduler');
        }
        RateLimiter::for('account-access', fn (Request $request) => [
            Limit::perMinute(20)->by('access-ip:'.$request->ip()),
            Limit::perMinute(5)->by('access-account:'.hash('sha256', strtolower((string) $request->input('email', $request->session()->get('two_factor_user_id', $request->ip()))))),
        ]);
        RateLimiter::for('email-delivery', fn (Request $request) => Limit::perMinute(2)->by('email:'.$request->ip()));
        RateLimiter::for('community-writes', fn (Request $request) => Limit::perMinute(10)->by('community:'.$request->user()?->id));
        RateLimiter::for('image-uploads', fn (Request $request) => Limit::perMinute(10)->by('upload:'.$request->user()?->id));
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
