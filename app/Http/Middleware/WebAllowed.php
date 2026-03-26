<?php

namespace App\Http\Middleware;

use App\Traits\HasRoleTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

class WebAllowed
{
    use HasRoleTrait;

    /**
     * @param  array<int, string>  $allowedRoles
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles): Response
    {
        if ($request->is('admin/login')) {
            return $next($request);
        }

        if ($request->is('admin') || $request->is('admin/*')) {

            if (! Auth::check()) {
                return redirect('/admin/login');
            }

            if (! $user = Auth::user()) {
                return redirect('/admin/login');
            }

            /** @var ImpersonateManager $impersonateManager */
            $impersonateManager = app(ImpersonateManager::class);
            if ($impersonateManager->isImpersonating()) {
                return $next($request);
            }

            if (! $this->userHasRole($allowedRoles)) {
                return redirect('/admin/login');
            }
        }

        return $next($request);
    }
}
