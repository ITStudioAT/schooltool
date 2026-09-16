<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\FeaturePreviewService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

class RequireFeaturePreviewAccess
{
    private const GUEST_GET_PATHS = [
        'admin/login',
        'admin/unknown_password',
        'api/admin/config',
        'api/admin/token',
        'sanctum/csrf-cookie',
    ];

    private const GUEST_POST_PATHS = [
        'api/admin/login_step_email',
        'api/admin/login_step_2',
        'api/admin/login_step_3',
        'api/admin/two-factor-challenge',
        'api/admin/password_unknown_step_email',
        'api/admin/password_unknown_step_school',
        'api/admin/password_unknown_step_token',
        'api/admin/password_unknown_step_token_2',
        'api/admin/password_unknown_step_password',
        'api/admin/execute_logout',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preview = app(FeaturePreviewService::class);
        if (! $preview->isPreview()) {
            return $next($request);
        }

        abort_unless($preview->enabled(), 503, 'Die Vorschau ist derzeit deaktiviert.');
        abort_if($request->bearerToken() !== null, 403, 'Bitte melden Sie sich in der Vorschau an.');

        $user = Auth::guard('web')->user();
        if ($user instanceof User) {
            abort_if(app(ImpersonateManager::class)->isImpersonating(), 403);
            $preview->assertCanEnter($user);
        } elseif (! $this->isGuestRoute($request)) {
            if ($request->expectsJson() || $request->is('api/*', 'broadcasting/*')) {
                abort(401, 'Bitte melden Sie sich in der Vorschau an.');
            }

            return redirect('/admin/login');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    private function isGuestRoute(Request $request): bool
    {
        return (in_array($request->method(), ['GET', 'HEAD'], true)
                && in_array($request->path(), self::GUEST_GET_PATHS, true))
            || ($request->isMethod('POST') && in_array($request->path(), self::GUEST_POST_PATHS, true));
    }
}
