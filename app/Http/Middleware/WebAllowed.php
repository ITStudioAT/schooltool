<?php

namespace App\Http\Middleware;

use App\Traits\HasRoleTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebAllowed
{
    use HasRoleTrait;

    protected array $allowed_roles;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function __construct()
    {
        $this->allowed_roles = [];
    }

    public function handle(Request $request, Closure $next, ...$allowed_roles): Response
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

            if (! $this->userHasRole($allowed_roles)) {
                return redirect('/admin/login');
            }
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
