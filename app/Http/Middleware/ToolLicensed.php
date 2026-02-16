<?php

namespace App\Http\Middleware;

use App\Services\LicenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ToolLicensed
{
    public function handle(Request $request, Closure $next, string $licenceName): Response
    {
        $user = Auth::user();
        $school = $user?->selectedSchool;

        $configFlag = $this->configFlagForLicence($licenceName);
        if ($configFlag && !config($configFlag, false)) {
            return $this->deny($request);
        }

        if (!$school || !app(LicenceService::class)->isLicenceValid($school, $licenceName)) {
            return $this->deny($request);
        }

        return $next($request);
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, 'Lizenz fehlt oder ist abgelaufen.');
        }

        return redirect('/');
    }

    private function configFlagForLicence(string $licenceName): ?string
    {
        return match ($licenceName) {
            'Nachhilfetool' => 'schooltool.tutoring_active',
            'Lehrertool' => 'schooltool.teaching_active',
            default => null,
        };
    }
}
