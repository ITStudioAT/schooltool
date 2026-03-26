<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\LicenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ToolLicensed
{
    public function handle(Request $request, Closure $next, string $licenceName, string $schoolSource = 'auto', ...$candidateRoleNames): Response
    {
        $licenceService = app(LicenceService::class);

        $configFlag = $this->configFlagForLicence($licenceName);
        if ($configFlag && ! config($configFlag, false)) {
            return $this->deny($request, 'missing');
        }

        $school = $this->resolveSchool($request, $schoolSource);
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

        if ($user?->selectedSchool) {
            return $user->selectedSchool;
        }

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

        return redirect('/');
    }

    private function configFlagForLicence(string $licenceName): ?string
    {
        return match ($licenceName) {
            'Nachhilfetool' => 'schooltool.tutoring_active',
            'Lehrertool' => 'schooltool.teaching_active',
            'Materialientool' => 'schooltool.materials_active',
            default => null,
        };
    }

    private function normalizeCandidateRoleNames(array $candidateRoleNames): array
    {
        $roles = [];
        foreach ($candidateRoleNames as $roleName) {
            if (! is_string($roleName)) {
                continue;
            }

            $roleName = trim($roleName);
            if ($roleName === '') {
                continue;
            }

            $roles[$roleName] = true;
        }

        return array_keys($roles);
    }
}
