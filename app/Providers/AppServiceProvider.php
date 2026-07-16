<?php

namespace App\Providers;

use App\Models\User;
use App\Services\EmailAliasResolver;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        $loader = AliasLoader::getInstance();
        if (config('app.env') === 'local') {
            $loader->alias('Debugbar', Debugbar::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        View::addNamespace('spa', resource_path('views/vendor/spa'));
        Route::model('user', User::class);
        $this->app['router']->pushMiddlewareToGroup('web', StartSession::class);

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('spa.api_throttle', 60))->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('web', function (Request $request) {
            return Limit::perMinute(config('spa.web_throttle', 60))->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(config('spa.global_throttle', 1000))->by($request->ip());
        });

        RateLimiter::for('authentication', function (Request $request) {
            $identity = data_get($request->all(), 'data.email')
                ?? $request->input('email')
                ?? data_get($request->all(), 'data.user_id')
                ?? $request->input('user_id')
                ?? 'unknown';
            $identity = is_scalar($identity) ? $identity : 'invalid';
            $identity = Str::lower(trim((string) $identity));
            $ip = (string) $request->ip();
            $endpoint = $request->path();

            return [
                Limit::perMinute(10)->by("authentication:{$endpoint}|{$identity}|{$ip}"),
                Limit::perMinute(60)->by("authentication-ip:{$ip}"),
            ];
        });

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            app(EmailAliasResolver::class)->rewriteMessageRecipients($event->message);
        });

        Event::listen(NotificationSending::class, function (NotificationSending $event): void {
            if ($event->channel === 'mail') {
                app(EmailAliasResolver::class)->rewriteNotificationMailRoute($event->notifiable);
            }
        });
    }
}
