<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\FeaturePreviewService;
use App\Services\ParentStudentAccessService;
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
        'api/homepage/config',
        'api/homepage/login_schools',
        'api/homepage/load_schools_for_tool',
        'api/homepage/student/config',
        'api/homepage/student/user',
        'api/homepage/students-timetables/config',
        'api/homepage/students-timetables/user',
        'api/homepage/tutoring/config',
        'api/homepage/tutoring/load_offer_config',
        'api/homepage/tutoring/load_offers',
        'api/homepage/restaurant/menu-plans',
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
        'api/homepage/login_step_email',
        'api/homepage/login_step_password',
        'api/homepage/login_step_2fa',
        'api/homepage/logout',
        'api/homepage/student/login_step_email',
        'api/homepage/student/login_step_code',
        'api/homepage/student/login_step_password',
        'api/homepage/student/login_step_parent_student',
        'api/homepage/students-timetables/login_step_email',
        'api/homepage/students-timetables/login_step_code',
        'api/homepage/students-timetables/login_step_password',
        'api/homepage/restaurant/check_email',
        'api/homepage/restaurant/send_login_code',
        'api/homepage/restaurant/login_with_code',
        'api/homepage/restaurant/login_with_password',
        'api/homepage/tutoring/check_email',
        'api/homepage/tutoring/confirm_email',
        'api/homepage/tutoring/unknown_password',
        'api/homepage/tutoring/login_with_token',
        'api/homepage/tutoring/login_with_password',
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
        abort_if(app(ImpersonateManager::class)->isImpersonating(), 403);

        if (! $user && $request->hasSession() && $request->session()->has('student_parent_access.verified_at')
            && $request->is('student', 'student/*', 'homepage/student', 'api/homepage/student/*')) {
            $user = app(ParentStudentAccessService::class)->currentStudent();
        }

        if ($user instanceof User) {
            $preview->assertCanEnter($user);

            if ($request->hasSession() && (int) $request->session()->get(RestrictRestaurantParentSession::SESSION_KEY, 0) === (int) $user->id) {
                $parentEmail = (string) $request->session()->get('restaurant.parent_email', '');
                abort_unless($parentEmail !== '' && $preview->authenticationRecipientAllowed($user, $parentEmail, 'restaurant_parent'), 403);
            }

            if ($request->is('admin', 'admin/*', 'api/admin/*') && ! $this->isGuestRoute($request)) {
                abort_unless($user->hasAdminShellAccess(), 403);
            }
        } elseif (! $this->isGuestRoute($request)) {
            if ($request->expectsJson() || $request->is('api/*', 'broadcasting/*')) {
                abort(401, 'Bitte melden Sie sich in der Vorschau an.');
            }

            return redirect($request->is('admin', 'admin/*') ? '/admin/login' : '/');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    private function isGuestRoute(Request $request): bool
    {
        return (in_array($request->method(), ['GET', 'HEAD'], true)
                && (in_array($request->path(), self::GUEST_GET_PATHS, true)
                    || $request->is('/', 'homepage', 'homepage/*', 'student', 'student/*', 'students-timetables', 'students-timetables/*', 'api/homepage/restaurant/menu-plans/*/print')))
            || ($request->isMethod('POST') && in_array($request->path(), self::GUEST_POST_PATHS, true));
    }
}
