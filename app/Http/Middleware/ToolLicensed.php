<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\LicenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ToolLicensed
{
    public function handle(Request $request, Closure $next, string $licenceName, string $schoolSource = 'auto'): Response
    {
        $licenceService = app(LicenceService::class);

        $configFlag = $this->configFlagForLicence($licenceName);
        if ($configFlag && !config($configFlag, false)) {
            return $this->deny($request, 'missing');
        }

        $school = $this->resolveSchool($request, $schoolSource);
        $allowedRoles = $this->resolveAllowedRolesFromRoute($request);
        $status = $licenceService->toolAccessStatusForUser(Auth::user(), $school, $licenceName, $allowedRoles);
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
            return redirect('/homepage/error?msg=' . urlencode($message));
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

    private function resolveAllowedRolesFromRoute(Request $request): array
    {
        $route = $request->route();
        if (! $route || ! method_exists($route, 'gatherMiddleware')) {
            return [];
        }

        $roles = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (! Str::startsWith($middleware, ['api-allowed:', 'web-allowed:'])) {
                continue;
            }

            [, $list] = array_pad(explode(':', $middleware, 2), 2, '');
            foreach (explode(',', (string) $list) as $roleName) {
                $roleName = trim($roleName);
                if ($roleName === '') {
                    continue;
                }
                $roles[$roleName] = true;
            }
        }

        return array_keys($roles);
    }
}
