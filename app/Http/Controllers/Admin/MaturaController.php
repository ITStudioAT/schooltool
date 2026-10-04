<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaturaSetupRequest;
use App\Http\Requests\MaturaActionRequest;
use App\Models\Import116;
use App\Models\MaturaSession;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Matura\MaturaAccessService;
use App\Services\Matura\MaturaReportService;
use App\Services\Matura\MaturaSetupService;
use App\Services\Matura\MaturaWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class MaturaController extends Controller
{
    public function __construct(
        private MaturaAccessService $access,
        private MaturaSetupService $setup,
        private MaturaWorkflowService $workflow,
        private MaturaReportService $reports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->access->admin($request);
        if (! Schema::hasTable('matura_sessions')) {
            return response()->json(['ready' => false, 'sessions' => []]);
        }
        $sessions = MaturaSession::query()->where('school_id', $user->school_id)
            ->when(! $user->hasAnyRole(['admin', 'super_admin']), fn ($query) => $query->where(fn ($query) => $query
                ->where('created_by', $user->id)->orWhereHas('accesses', fn ($access) => $access
                ->where('user_id', $user->id)->whereNull('revoked_at')->where('expires_at', '>', now()))))
            ->withCount('rooms', 'students')->orderByDesc('exam_date')->orderByDesc('id')->get();

        return response()->json(['ready' => true, 'sessions' => $sessions->map(fn (MaturaSession $session): array => $session->toArray() + ['can_manage' => $this->access->canManage($session, $user)])]);
    }

    public function roster(Request $request): JsonResponse
    {
        $user = $this->access->admin($request);
        $data = $request->validate(['schoolyear_id' => ['required', 'integer']]);
        Schoolyear::query()->where('school_id', $user->school_id)->findOrFail($data['schoolyear_id']);

        return response()->json([
            'students' => Import116::query()->where('school_id', $user->school_id)
                ->where('schoolyear_id', $data['schoolyear_id'])->whereNotNull('exists_date')
                ->orderBy('class')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'class']),
            'supervisors' => User::query()->where('school_id', $user->school_id)->where('is_active', true)->with('roles')
                ->get(['id', 'first_name', 'last_name'])->filter(fn (User $user): bool => $user->hasAdminShellAccess())
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => trim($user->first_name.' '.$user->last_name)])->values(),
        ]);
    }

    public function store(MaturaSetupRequest $request): JsonResponse
    {
        $session = $this->setup->save($this->access->admin($request), $request->validated());

        return response()->json(['id' => $session->id], 201);
    }

    public function update(MaturaSetupRequest $request, MaturaSession $matura): JsonResponse
    {
        $this->setup->save($this->access->manager($request, $matura), $request->validated(), $matura);

        return response()->json(['id' => $matura->id]);
    }

    public function show(Request $request, MaturaSession $matura): JsonResponse
    {
        return DB::transaction(fn (): JsonResponse => response()->json($this->reports->state(
            $matura, $this->access->actor($request, $matura)
        )))->header('Cache-Control', 'no-store');
    }

    public function action(MaturaActionRequest $request, MaturaSession $matura): JsonResponse
    {
        $this->workflow->execute($request, $matura->id, $request->validated());

        return $this->show($request, $matura->fresh());
    }

    public function lifecycle(Request $request, MaturaSession $matura): JsonResponse
    {
        $user = $this->access->manager($request, $matura);
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'closed'])],
            'waiting_places' => ['required', 'integer', 'between:0,10'],
        ]);
        $this->setup->lifecycle($matura, $user, $data['status'], $data['waiting_places']);

        return $this->show($request, $matura->fresh());
    }

    public function invite(Request $request, MaturaSession $matura): JsonResponse
    {
        $user = $this->access->manager($request, $matura);
        $data = $request->validate([
            'room_id' => ['nullable', 'integer'], 'user_id' => ['nullable', 'integer'],
            'name' => ['required_without:user_id', 'nullable', 'string', 'max:180'],
            'hours' => ['required', 'integer', 'between:1,72'],
        ]);
        $result = $this->setup->invite($matura, $user, $data);

        return response()->json([
            'access' => $result['access'],
            'url' => $result['token'] ? route('matura.station').'#'.$result['token'] : null,
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function revoke(Request $request, MaturaSession $matura, int $access): JsonResponse
    {
        $this->setup->revoke($matura, $this->access->manager($request, $matura), $access);

        return $this->show($request, $matura->fresh());
    }

    public function report(Request $request, MaturaSession $matura): JsonResponse
    {
        return response()->json($this->reportData($request, $matura))->header('Cache-Control', 'no-store');
    }

    public function pdf(Request $request, MaturaSession $matura): PdfBuilder
    {
        return Pdf::view('pdfs.matura-report', $this->reportData($request, $matura))
            ->driver('dompdf')->format('a4')->landscape()->name('00-Manager-'.$matura->exam_date->format('Y-m-d').'.pdf')->download();
    }

    /** @return array<string, mixed> */
    private function reportData(Request $request, MaturaSession $matura): array
    {
        $this->access->manager($request, $matura);
        $filters = $request->validate([
            'room_id' => ['nullable', 'integer'], 'student_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(MaturaReportService::StatusLabels))],
        ]);

        return $this->reports->report($matura, array_filter($filters, fn ($value): bool => $value !== null && $value !== ''));
    }
}
