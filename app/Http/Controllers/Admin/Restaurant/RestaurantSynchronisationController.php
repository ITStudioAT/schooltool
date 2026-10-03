<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantSynchronisationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RestaurantSynchronisationController extends Controller
{
    public function preview(RestaurantSynchronisationService $service): JsonResponse
    {
        if (! $actor = $this->userHasRole(['super_admin'])) {
            abort(403, 'Nur Superadmins dürfen Restaurantdaten synchronisieren.');
        }
        abort_unless($service::available(), 403, 'Die Synchronisation ist nur lokal verfügbar.');

        try {
            return response()->json(['data' => $service->preview($actor)], 200, ['Cache-Control' => 'no-store, private']);
        } catch (QueryException) {
            return $this->failed('Die lokalen Beziehungen oder das Datenbankschema passen nicht zum Snapshot. Es wurde nichts übernommen.');
        } catch (ValidationException $exception) {
            return $this->conflicts($exception);
        } catch (RuntimeException $exception) {
            return $this->failed($exception->getMessage());
        }
    }

    public function apply(Request $request, RestaurantSynchronisationService $service): JsonResponse
    {
        if (! $actor = $this->userHasRole(['super_admin'])) {
            abort(403, 'Nur Superadmins dürfen Restaurantdaten synchronisieren.');
        }
        abort_unless($service::available(), 403, 'Die Synchronisation ist nur lokal verfügbar.');
        $validated = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/\A[a-zA-Z0-9]+\z/'], 'confirmed' => ['required', 'accepted']]);

        try {
            $service->apply($actor, $validated['token']);

            return response()->json(['message' => 'Der geprüfte Restaurantstand wurde lokal übernommen.'], 200, ['Cache-Control' => 'no-store, private']);
        } catch (QueryException) {
            return $this->failed('Eine Datenbankbeziehung hat die Übernahme verhindert. Die Datenbankänderungen wurden zurückgerollt.');
        } catch (ValidationException $exception) {
            return $this->conflicts($exception);
        } catch (RuntimeException $exception) {
            return $this->failed($exception->getMessage());
        }
    }

    private function failed(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422, ['Cache-Control' => 'no-store, private']);
    }

    private function conflicts(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => 'Die Prüfung hat mehrere Konflikte gefunden. Es wurde nichts übernommen.',
            'conflicts' => $exception->errors()['synchronisation'],
        ], 422, ['Cache-Control' => 'no-store, private']);
    }
}
