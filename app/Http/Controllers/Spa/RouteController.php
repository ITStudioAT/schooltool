<?php

namespace App\Http\Controllers\Spa;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Enums\RouteResult;
use App\Http\Requests\Spa\RouteAllowedRequest;
use App\Services\RouteService;

class RouteController extends Controller
{
    public function isRouteAllowed(RouteAllowedRequest $request)
    {
        // Getting the route request from routes/admin.js
        // Checking, if the user is permitted to go the route
        // return ok, or abort


        $data = $request->validated()['data'];
        $fullPath = $data['to'];
        $user = auth()->user();

        $routeService = new RouteService();
        $result = $routeService->checkWebRoles($user, $fullPath);

        if ($result == RouteResult::ALLOWED) {
            return response()->json(['message' => 'Success'], 200);
        }
        if ($result == RouteResult::NOT_ALLOWED) {
            abort(403, 'Sie können auf diese Seite nicht zugreifen');
        }
        if ($result == RouteResult::NOT_EXISTS) {
            abort(404, 'Die Seite konnte nicht gefunden werden');
        }
        if ($result == RouteResult::NOT_FOUND) {
            abort(404, 'Die Seite ist nicht registriert: Programmierer verständigen');
        }

        abort(500, 'Fehler in RouteController/isRouteAllowed');
    }
}
