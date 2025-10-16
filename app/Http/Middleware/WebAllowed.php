<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Enums\RouteResult;
use App\Services\RouteService;
use Symfony\Component\HttpFoundation\Response;

class WebAllowed
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        if ($request->is('application/error')) {
            return $next($request); // Middleware umgehen
        }

        $fullPath = '/' . ltrim($request->path(), '/'); // <--- WICHTIG!
        $user = auth()->user();

        $routeService = new RouteService();
        $result = $routeService->checkWebRoles($user, $fullPath);






        switch ($result) {
            case RouteResult::ALLOWED:
                return $next($request);

            case RouteResult::NOT_ALLOWED:
                $status = 403;
                $message = 'Sie können auf diese Seite nicht zugreifen';
                break;

            case RouteResult::NOT_EXISTS:
                $status = 404;
                $message = 'Die Seite konnte nicht gefunden werden';
                break;

            case RouteResult::NOT_FOUND:
                $status = 404;
                $message = 'Die Seite konnte nicht gefunden werden';
                break;

            default:
                $status = 500;
                $message = 'Fehl WebAllowed Middleware';
        }

        return Redirect::to('/application/error?' . http_build_query([
            'status' => $status,
            'message' => $message,
            'type' => 'error',
        ]));
    }


   
}
