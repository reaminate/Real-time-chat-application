<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'message' => \App\Models\Message::class,
        ]);
        RateLimiter::for('reactions', function(Request $request){
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn(Request $request, array $headers) =>
                    response()->json([
                        'message' => 'too many reactions in 1 minute'
                    ], 429, $headers)
                );
        });
    }
}
