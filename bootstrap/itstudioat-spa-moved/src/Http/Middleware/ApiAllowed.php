<?php

namespace App\Http\Middleware;

use App\Traits\HasRoleTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAllowed
{
    use HasRoleTrait;

    protected array $allowed_roles;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function __construct()
    {
        $this->allowed_roles = [];
    }

    public function handle(Request $request, Closure $next, ...$allowed_roles): Response
    {

        if (! auth()->check()) {
            abort(401, 'Nicht authorisiert');
        }
        if (! $user = auth()->user()) {
            abort(401, 'Nicht authorisiert');
        }

        if (! $this->userHasRole($allowed_roles)) {
            abort(403, 'Unzulässig');
        }

        return $next($request);
    }

    private function error($status, $message): Response
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'type' => 'error',
        ], $status);
    }
}
