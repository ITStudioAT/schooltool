<?php

namespace App\Http\Middleware;

use App\Traits\HasRoleTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiAllowed
{
    use HasRoleTrait;

    /**
     * @param  array<int, string>  $allowedRoles
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles): Response
    {
        if (! Auth::check()) {
            abort(401, 'Nicht authorisiert');
        }
        if (! $user = Auth::user()) {
            abort(401, 'Nicht authorisiert');
        }

        if (! $this->userHasRole($allowedRoles)) {
            abort(403, 'Unzulässig');
        }

        return $next($request);
    }
}
