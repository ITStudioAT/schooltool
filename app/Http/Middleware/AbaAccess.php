<?php

namespace App\Http\Middleware;

use App\Services\LicenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AbaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect('/admin/login');
        }

        $user = Auth::user();
        if (! $user || ! $user->hasRole('aba_teacher')) {
            return $this->deny($request, 'Sie haben keine Berechtigung für den ABA-Bereich.');
        }

        $licenceStatus = app(LicenceService::class)->toolAccessStatusForUser(
            $user,
            $user->selectedSchool,
            'ABA',
            ['aba_teacher']
        );

        if ($licenceStatus !== 'active') {
            $message = $licenceStatus === 'expired'
                ? 'ABA-Lizenz abgelaufen.'
                : 'ABA-Lizenz nicht vorhanden.';

            return $this->deny($request, $message);
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}
