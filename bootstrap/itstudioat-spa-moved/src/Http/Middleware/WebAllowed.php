<?php

namespace App\Http\Middleware;

use App\Traits\HasRoleTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WebAllowed
{
    use HasRoleTrait;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles): Response
    {
        if ($request->is('application/error')) {
            return $next($request);
        }

        if (! $request->is('admin') && ! $request->is('admin/*')) {
            return $next($request);
        }

        if (! Auth::check()) {
            return redirect('/admin/login');
        }

        if (! Auth::user()) {
            return redirect('/admin/login');
        }

        if (! empty($allowedRoles) && ! $this->userHasRole($allowedRoles)) {
            return redirect('/application/error?'.http_build_query([
                'status' => 403,
                'message' => 'Sie können auf diese Seite nicht zugreifen',
                'type' => 'error',
            ]));
        }

        return $next($request);
    }
}
