<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Services\TeachingTestEnvironmentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeachingTestEnvironmentController extends Controller
{
    public function index(TeachingTestEnvironmentService $service): JsonResponse
    {
        if (! $user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $this->respond(fn (): array => $service->status($user));
    }

    public function store(Request $request, TeachingTestEnvironmentService $service): JsonResponse
    {
        if (! $user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->validateConfirmation($request);

        return $this->respond(
            fn (): array => $service->setup($user),
            'Die Test-Umgebung 2026/27 wurde eingerichtet.'
        );
    }

    public function destroy(Request $request, TeachingTestEnvironmentService $service): JsonResponse
    {
        if (! $user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->validateConfirmation($request);

        return $this->respond(
            fn (): array => $service->destroy($user),
            'Die Test-Umgebung 2026/27 wurde vollständig gelöscht.'
        );
    }

    private function respond(callable $callback, ?string $message = null): JsonResponse
    {
        try {
            $data = $callback();
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(array_filter([
            'message' => $message,
            'data' => $data,
        ], fn (mixed $value): bool => $value !== null));
    }

    private function validateConfirmation(Request $request): void
    {
        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([TeachingTestEnvironmentService::TARGET_SCHOOLYEAR])],
        ], [
            'confirmation.in' => 'Zur Bestätigung muss 2026/27 eingegeben werden.',
        ]);
    }
}
