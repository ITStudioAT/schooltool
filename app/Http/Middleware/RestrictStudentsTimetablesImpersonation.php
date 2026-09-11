<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

class RestrictStudentsTimetablesImpersonation
{
    public const SESSION_KEY = 'students_timetables.restricted_impersonation';

    public const RETURN_URL_SESSION_KEY = 'students_timetables.impersonation_return_url';

    public function __construct(private readonly ImpersonateManager $impersonateManager) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! (bool) $request->session()->get(self::SESSION_KEY, false)) {
            return $next($request);
        }

        if (! $this->impersonateManager->isImpersonating()) {
            $request->session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, 'Diese Benutzer-Übernahme ist auf SEPP beschränkt.');
        }

        return redirect('/students-timetables/overview');
    }
}
