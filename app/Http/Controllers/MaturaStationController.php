<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaturaActionRequest;
use App\Models\MaturaAccess;
use App\Services\Matura\MaturaAccessService;
use App\Services\Matura\MaturaReportService;
use App\Services\Matura\MaturaWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaturaStationController extends Controller
{
    public function __construct(
        private MaturaAccessService $access,
        private MaturaReportService $reports,
        private MaturaWorkflowService $workflow,
    ) {}

    public function page(): Response
    {
        return response()->view('matura-station')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48']]);
        abort_unless(Schema::hasTable('matura_accesses'), 503, 'Der 00-Manager ist noch nicht aktiviert.');
        $access = MaturaAccess::query()->whereNull('user_id')->where('token_hash', hash('sha256', $data['token']))->first();
        abort_unless($access?->isValid() && $access->session->status !== 'closed', 422, 'Der Zugang ist ungültig, abgelaufen oder widerrufen.');
        $request->session()->regenerate();
        $request->session()->put('matura_access', ['id' => $access->id, 'fingerprint' => $access->token_hash]);

        return $this->state($request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('matura_access');
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }

    public function state(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $session = $this->access->guestSession($request);

            return response()->json($this->reports->state($session, $this->access->actor($request, $session, true)));
        })->header('Cache-Control', 'no-store');
    }

    public function action(MaturaActionRequest $request): JsonResponse
    {
        $session = $this->access->guestSession($request);
        $this->workflow->execute($request, $session->id, $request->validated(), true);

        return $this->state($request);
    }
}
