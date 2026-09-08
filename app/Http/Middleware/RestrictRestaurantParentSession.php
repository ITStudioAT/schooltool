<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictRestaurantParentSession
{
    public const SESSION_KEY = 'restaurant.parent_user_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $parentUserId = (int) $request->session()->get(self::SESSION_KEY, 0);
        if ($parentUserId < 1) {
            return $next($request);
        }

        if ((int) Auth::guard('web')->id() !== $parentUserId) {
            $request->session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        if (! $request->is('api/homepage/restaurant/change_password') && $request->is(
            'homepage/restaurant',
            'homepage/restaurant/*',
            'api/homepage/restaurant/*',
            'api/homepage/config',
            'api/homepage/load_schools_for_tool',
            'api/homepage/logout',
            'api/admin/execute_logout',
            'api/admin/token',
            'sanctum/csrf-cookie',
        )) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, 'Dieser Elternzugang ist auf das Restaurant beschränkt.');
        }

        return redirect('/homepage/restaurant');
    }
}
