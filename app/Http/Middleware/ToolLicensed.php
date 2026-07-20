<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\AccessScopeService;
use App\Services\LicenceService;
use App\Services\ParentStudentAccessService;
use App\Services\SchoolToolModuleStatusService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ToolLicensed
{
    public function handle(Request $request, Closure $next, string $licenceName, string $schoolSource = 'auto', ...$candidateRoleNames): Response
    {
        $licenceService = app(LicenceService::class);
        $moduleStatusService = app(SchoolToolModuleStatusService::class);

        $school = $this->resolveSchool($request, $schoolSource);
        $moduleKey = $moduleStatusService->moduleKeyForLicence($licenceName);
        if ($moduleKey) {
            if ($request->is('admin/*') || $request->is('api/admin/*')) {
                if (! $moduleStatusService->adminVisibleForModule($moduleKey, $school)) {
                    return $this->deny($request, SchoolToolModuleStatusService::INACTIVE);
                }
            } else {
                $moduleStatus = $moduleStatusService->userStatusForModule($moduleKey, $school);
                if (! $moduleStatusService->allowsUserAccess($moduleStatus)) {
                    return $this->deny($request, $moduleStatus);
                }
            }
        }

        $candidateRoleNames = $this->normalizeCandidateRoleNames($candidateRoleNames);
        $status = $licenceService->toolAccessStatusForUser(Auth::user(), $school, $licenceName, $candidateRoleNames);
        if ($status !== 'active') {
            return $this->deny($request, $status);
        }

        return $next($request);
    }

    private function resolveSchool(Request $request, string $schoolSource): ?School
    {
        $user = Auth::user();

        if ($schoolSource === 'auth') {
            return $user?->selectedSchool;
        }

        $schoolFromRequest = $this->resolveSchoolFromRequest($request);

        if ($request->is('homepage/*') || $request->is('api/homepage/*')) {
            $parentAccessSchool = $request->is('api/homepage/student/*')
                ? app(ParentStudentAccessService::class)->school()
                : null;

            return $schoolFromRequest ?? $parentAccessSchool ?? $user?->selectedSchool;
        }

        return $user?->selectedSchool ?? $schoolFromRequest;
    }

    private function resolveSchoolFromRequest(Request $request): ?School
    {
        $schoolId = $request->input('school_id') ?? $request->input('data.school_id');
        if ($schoolId) {
            $school = School::find((int) $schoolId);
            if ($school) {
                return $school;
            }
        }

        $shortName = $request->query('school')
            ?? $request->input('school')
            ?? $request->input('school_name')
            ?? $request->input('data.school_name');

        if (is_string($shortName) && trim($shortName) !== '') {
            $school = School::where('short_name', trim($shortName))->first();
            if ($school) {
                return $school;
            }
        }

        return null;
    }

    private function deny(Request $request, string $status): Response
    {
        $message = match ($status) {
            'expired' => 'Lizenz abgelaufen.',
            'comming_soon' => 'Dieses Modul ist bald verfügbar.',
            'inactive' => 'Dieses Modul ist derzeit nicht verfügbar.',
            default => 'Lizenz nicht vorhanden.',
        };

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        if ($request->is('homepage/*')) {
            return redirect('/homepage/error?msg='.urlencode($message));
        }

        if ($request->is('admin/*')) {
            return redirect('/admin');
        }

        return redirect('/');
    }

    private function normalizeCandidateRoleNames(array $candidateRoleNames): array
    {
        return app(AccessScopeService::class)->resolveRoleNames($candidateRoleNames);
    }
}
