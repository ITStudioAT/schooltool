<?php

use App\Http\Middleware\AbaAccess;
use App\Http\Middleware\ApiAllowed;
use App\Http\Middleware\RestrictRestaurantParentSession;
use App\Http\Middleware\RestrictStudentsTimetablesImpersonation;
use App\Http\Middleware\ToolLicensed;
use App\Http\Middleware\WebAllowed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->appendToGroup('web', RestrictRestaurantParentSession::class);
        $middleware->appendToGroup('api', RestrictRestaurantParentSession::class);
        $middleware->appendToPriorityList(StartSession::class, RestrictRestaurantParentSession::class);
        $middleware->alias([
            'aba-access' => AbaAccess::class,
            'web-allowed' => WebAllowed::class,
            'api-allowed' => ApiAllowed::class,
            'students-timetables-impersonation' => RestrictStudentsTimetablesImpersonation::class,
            'tool-licensed' => ToolLicensed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
