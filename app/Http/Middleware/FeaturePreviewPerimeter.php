<?php

namespace App\Http\Middleware;

use App\Services\FeaturePreviewService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeaturePreviewPerimeter
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preview = app(FeaturePreviewService::class);

        if (! $preview->isPreview()) {
            return $next($request);
        }

        $expectedHost = strtolower((string) config('schooltool.preview.expected_host', ''));
        abort_unless($expectedHost !== '' && $request->getHost() === $expectedHost, 404);

        abort_unless($preview->enabled(), 503, 'Die Vorschau ist derzeit deaktiviert.');

        abort_unless($request->is(
            '/', 'admin', 'admin/*', 'api/admin/*',
            'homepage', 'homepage/*', 'api/homepage/*',
            'student', 'student/*', 'students-timetables', 'students-timetables/*',
            'sanctum/csrf-cookie', 'broadcasting/auth',
        ), 404);

        abort_if($request->is(
            'admin/register',
            'admin/email_verification',
            'api/admin/register_step_*',
            'api/admin/new_teacher_step_*',
            'api/admin/impersonation/*',
            'api/admin/students-timetables/robot/students/impersonate',
            'api/admin/restart_queues',
            'api/admin/health/test-queue*',
            'api/admin/materials/storage-audit',
            'api/admin/materials/storage-audit/*',
            'homepage/register',
            'homepage/register2',
            'api/homepage/register/*',
            'api/homepage/restaurant/register',
            'api/homepage/restaurant/confirm_email',
            'homepage/restaurant/confirm-user',
            'homepage/restaurant/reject-user',
        ), 403, 'Diese Aktion steht nur in der Hauptanwendung zur Verfügung.');

        return $next($request);
    }
}
