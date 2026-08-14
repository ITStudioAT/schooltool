<?php

namespace App\Providers;

use App\Listeners\StudentTimetableV3SessionSubscriber;
use App\Listeners\TwoFactorSecuritySubscriber;
use App\Models\User;
use App\Services\EmailAliasResolver;
use App\Services\SchoolUserLicenceAssignmentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->singleton(SchoolUserLicenceAssignmentService::class);
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

        RateLimiter::for('uploads', function (Request $request) {
            $actor = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perMinute(180)->by("uploads-minute:{$actor}"),
                Limit::perHour(2000)->by("uploads-hour:{$actor}"),
            ];
        });

        RateLimiter::for('health-queue-tests', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'missing');

            return [
                Limit::perMinute(3)->by("health-queue-tests-user:{$userId}"),
                Limit::perMinute(10)->by('health-queue-tests-ip:'.$request->ip()),
            ];
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

        RateLimiter::for('two-factor-challenge', function (Request $request) {
            $userId = $request->hasSession()
                ? (string) $request->session()->get('login.id', 'missing')
                : 'missing:'.$request->ip();

            return [
                Limit::perMinute(5)->by("two-factor-challenge-user:{$userId}"),
                Limit::perMinute(30)->by('two-factor-challenge-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor-management', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'missing');

            return [
                Limit::perMinute(5)->by("two-factor-management-user:{$userId}"),
                Limit::perMinute(30)->by('two-factor-management-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor-confirmation', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'missing');

            return [
                Limit::perMinute(5)->by("two-factor-confirmation-user:{$userId}"),
                Limit::perMinute(30)->by('two-factor-confirmation-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-confirmation', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'missing');

            return [
                Limit::perMinute(5)->by("password-confirmation-user:{$userId}"),
                Limit::perMinute(30)->by('password-confirmation-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('sepa-flow', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'missing');
            $flowUuid = (string) data_get($request->input('data'), 'flow_uuid', 'missing');
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(10)->by("sepa-flow:{$userId}|{$flowUuid}|{$ip}"),
                Limit::perMinute(30)->by("sepa-flow-ip:{$ip}"),
            ];
        });

        Event::subscribe(TwoFactorSecuritySubscriber::class);
        Event::subscribe(StudentTimetableV3SessionSubscriber::class);

        Gate::define('viewPulse', fn (User $user): bool => $user->hasRole('super_admin'));

        Pulse::user(fn (User $user): array => [
            'name' => "User #{$user->getAuthIdentifier()}",
            'extra' => '',
            'avatar' => '',
        ]);

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
